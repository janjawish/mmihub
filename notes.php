<?php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/semester.php';
require_once __DIR__ . '/includes/maquette_loader.php';
require_once __DIR__ . '/includes/notes_stats.php';
require_once __DIR__ . '/includes/absences.php';
require_once __DIR__ . '/includes/track_visit.php';

$user = require_login($pdo);

// ====== Semestre courant (S1..S6) ======
$currentSemesterId   = getCurrentSemesterNumberForUser($user);
$currentSemesterCode = 'S' . $currentSemesterId;

// Résumé absences du semestre + malus par module
$absSummary        = get_absence_summary($pdo, $user['id'], $currentSemesterId);
$penaltiesByModule = $absSummary['penalties'] ?? [];   // [module_id => points retirés]
// Liste détaillée des absences de l'utilisateur pour ce semestre
$absences = get_user_absences($pdo, $user['id'], $currentSemesterId);

// Impact détaillé par ressource (uniquement absences INJUSTIFIÉES)
$absenceImpact = []; // [module_id => info]

foreach ($absences as $a) {
    if ((int)$a['justified'] === 1) {
        // on ignore les absences justifiées pour le malus
        continue;
    }

    $mid = (int)$a['module_id'];

    if (!isset($absenceImpact[$mid])) {
        $absenceImpact[$mid] = [
            'module_code' => $a['module_code'],
            'module_name' => $a['module_name'],
            'hours'       => 0.0,
            'dates'       => [],
            'penalty'     => $penaltiesByModule[$mid] ?? 0.0, // points perdus au total sur cette ressource
        ];
    }

    $absenceImpact[$mid]['hours']  += (float)$a['hours'];
    $absenceImpact[$mid]['dates'][] = $a['absence_date']; // format Y-m-d venant de la BDD
}

// Maquette (UE + modules + coefs) selon BUT / parcours / formation
$maquette    = loadSemesterMaquetteForUser($pdo, $user, $currentSemesterId);
$hasMaquette = !empty($maquette);

// Map module_id => type (ressource / sae)
$moduleTypes = [];
foreach ($maquette as $row) {
    $mid = (int)$row['module_id'];
    if (!isset($moduleTypes[$mid])) {
        $moduleTypes[$mid] = $row['module_type'];
    }
}

/**
 * Recalcule module_grades à partir de module_assessments
 * pour un user + semestre + maquette donnée.
 */
function recomputeModuleGradesFromAssessments(PDO $pdo, array $moduleTypes, int $userId, int $semesterId): void
{
    if (empty($moduleTypes)) {
        return;
    }

    $selectAssess = $pdo->prepare("
        SELECT kind, grade
        FROM module_assessments
        WHERE user_id = :uid
          AND semester_id = :sid
          AND module_id = :mid
    ");

    $upsertGrade = $pdo->prepare("
        INSERT INTO module_grades (user_id, semester_id, module_id, note_ds, note_tp, final_grade)
        VALUES (:uid, :sid, :mid, :nds, :ntp, :final)
        ON DUPLICATE KEY UPDATE
            note_ds     = VALUES(note_ds),
            note_tp     = VALUES(note_tp),
            final_grade = VALUES(final_grade)
    ");

    $deleteGrade = $pdo->prepare("
        DELETE FROM module_grades
        WHERE user_id = :uid
          AND semester_id = :sid
          AND module_id = :mid
    ");

    foreach ($moduleTypes as $mid => $type) {
        $selectAssess->execute([
            ':uid' => $userId,
            ':sid' => $semesterId,
            ':mid' => $mid,
        ]);
        $rows = $selectAssess->fetchAll(PDO::FETCH_ASSOC);

        // Plus aucune note pour ce module => on supprime sa ligne agrégée
        if (!$rows) {
            $deleteGrade->execute([
                ':uid' => $userId,
                ':sid' => $semesterId,
                ':mid' => $mid,
            ]);
            continue;
        }

        $ds  = [];
        $tp  = [];
        $uni = [];

        foreach ($rows as $r) {
            $g = (float)$r['grade'];

            if ($type === 'ressource') {
                // Ressource classique : on distingue DS / TP
                if ($r['kind'] === 'ds') {
                    $ds[] = $g;
                } elseif ($r['kind'] === 'tp') {
                    $tp[] = $g;
                } else {
                    // au cas où une note "unique" traîne sur une ressource
                    $uni[] = $g;
                }
            } else {
                // SAÉ / Portfolio / autre => une seule moyenne globale
                $uni[] = $g;
            }
        }

        $noteDs = null;
        $noteTp = null;
        $final  = null;

        if ($type === 'ressource') {
            if ($ds) {
                $noteDs = array_sum($ds) / count($ds);
            }
            if ($tp) {
                $noteTp = array_sum($tp) / count($tp);
            }

            if ($noteDs !== null && $noteTp !== null) {
                $final = $noteDs * 0.67 + $noteTp * 0.33;
            } elseif ($noteDs !== null) {
                $final = $noteDs;
            } elseif ($noteTp !== null) {
                $final = $noteTp;
            } elseif ($uni) {
                // fallback bizarre, mais au moins ça n'est pas perdu
                $final = array_sum($uni) / count($uni);
            }
        } else {
            // SAÉ / Portfolio => moyenne simple de toutes les notes
            if ($uni) {
                $final = array_sum($uni) / count($uni);
            }
        }

        $upsertGrade->execute([
            ':uid'   => $userId,
            ':sid'   => $semesterId,
            ':mid'   => $mid,
            ':nds'   => $noteDs,
            ':ntp'   => $noteTp,
            ':final' => $final,
        ]);
    }
}


// ====== Gestion des POST (ajout / édition / suppression de notes) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_note') {
        $assessmentId = !empty($_POST['assessment_id']) ? (int)$_POST['assessment_id'] : null;
        $moduleId     = (int)($_POST['module_id'] ?? 0);
        $kind         = $_POST['kind'] ?? 'ds';
        $grade        = isset($_POST['grade']) && $_POST['grade'] !== '' ? (float)$_POST['grade'] : null;

        if ($moduleId && $grade !== null && in_array($kind, ['ds','tp','unique'], true)) {
            if ($assessmentId) {
                // édition d'une note existante
                $stmt = $pdo->prepare("
                    UPDATE module_assessments
                    SET module_id = :mid,
                        kind      = :kind,
                        grade     = :grade
                    WHERE id = :id
                      AND user_id = :uid
                      AND semester_id = :sid
                ");
                $stmt->execute([
                    ':mid'   => $moduleId,
                    ':kind'  => $kind,
                    ':grade' => $grade,
                    ':id'    => $assessmentId,
                    ':uid'   => $user['id'],
                    ':sid'   => $currentSemesterId,
                ]);
            } else {
                // nouvelle note
                $stmt = $pdo->prepare("
                    INSERT INTO module_assessments
                        (user_id, semester_id, module_id, kind, grade)
                    VALUES
                        (:uid, :sid, :mid, :kind, :grade)
                ");
                $stmt->execute([
                    ':uid'   => $user['id'],
                    ':sid'   => $currentSemesterId,
                    ':mid'   => $moduleId,
                    ':kind'  => $kind,
                    ':grade' => $grade,
                ]);
            }
        }

        header('Location: mmi-notes');
        exit;
    }

    if ($action === 'delete_assessment') {
        $assessmentId = (int)($_POST['assessment_id'] ?? 0);
        if ($assessmentId > 0) {
            $stmt = $pdo->prepare("
                DELETE FROM module_assessments
                WHERE id = :id
                  AND user_id = :uid
                  AND semester_id = :sid
            ");
            $stmt->execute([
                ':id'  => $assessmentId,
                ':uid' => $user['id'],
                ':sid' => $currentSemesterId,
            ]);
        }

        header('Location: notes.php');
        exit;
    }
}

// ====== Recalcul des notes agrégées à partir des évaluations ======
recomputeModuleGradesFromAssessments($pdo, $moduleTypes, $user['id'], $currentSemesterId);

// ====== Chargement des notes agrégées pour stats ======
$stmt = $pdo->prepare("
    SELECT module_id, note_ds, note_tp, final_grade
    FROM module_grades
    WHERE user_id = :uid AND semester_id = :sid
");
$stmt->execute([':uid' => $user['id'], ':sid' => $currentSemesterId]);
$gradesByModule = [];
foreach ($stmt as $row) {
    $gradesByModule[(int)$row['module_id']] = $row;
}

// >>> APPLIQUER LES MALUS D'ABSENCES SUR LES NOTES DES MODULES <<<
if (!empty($penaltiesByModule)) {
    foreach ($gradesByModule as $mid => &$g) {
        // malus uniquement si on a déjà une note finale pour ce module
        if (isset($penaltiesByModule[$mid]) && $g['final_grade'] !== null) {
            // on retire le malus, sans descendre en dessous de 0
            $g['final_grade'] = max(0, (float)$g['final_grade'] - (float)$penaltiesByModule[$mid]);
        }
    }
    unset($g); // bonne pratique quand on a utilisé une référence
}

// Statistiques semestre (compétences, UE, global) + sauvegarde en BDD
$stats = computeSemesterStats($currentSemesterId, $maquette, $gradesByModule);
saveSemesterStats($pdo, $user['id'], $currentSemesterId, $stats);

// Sécurité : si jamais $semesterStats n'a pas été rempli (maquette manquante, etc.)
if (!isset($semesterStats) || !is_array($semesterStats)) {
    $semesterStats = [
        'avg_global'      => null,
        'competences'     => [],
        'competences_ok'  => 0,
        'has_eliminatory' => false,
        'semester_valid'  => false,
        'modules'         => [],
    ];
}

// ====== Historique des notes individuelles ======
$assessments = [];
if ($hasMaquette) {
    $st = $pdo->prepare("
        SELECT ma.id,
               ma.module_id,
               ma.kind,
               ma.grade,
               ma.created_at,
               m.code AS module_code,
               m.name AS module_name
        FROM module_assessments ma
        JOIN modules m ON m.id = ma.module_id
        WHERE ma.user_id = :uid
          AND ma.semester_id = :sid
        ORDER BY ma.created_at DESC, ma.id DESC
        LIMIT 40
    ");
    $st->execute([':uid' => $user['id'], ':sid' => $currentSemesterId]);
    $assessments = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ====== Liste unique des modules pour le <select> (évite les doublons) ======
$moduleOptions = [];
foreach ($maquette as $row) {
    $mid = (int)$row['module_id'];
    if (!isset($moduleOptions[$mid])) {
        $moduleOptions[$mid] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="favicon.ico">
  <meta charset="UTF-8">
  <title>MMI NOTES</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="notes-body">
<header class="site-header">
  <div class="header-inner">
    <a href="accueil#top" class="logo">
      <span class="logo-mark">MMI</span>
      <span class="logo-word">HUB</span>
    </a>

    <!-- BOUTON BURGER -->
    <button class="nav-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
    </button>

    <!-- MENU -->
    <nav class="main-nav">
      <a href="mmi-notes" class="nav-link nav-notes" >MMI Notes</a>
      <a href="mmi-abs" class="nav-link nav-abs" >MMI ABS</a>
      <a href="mmi-chat" class="nav-link nav-chat" >MMI Chat</a>
      <a href="mmi-edt" class="nav-link nav-services" >MMI EDT</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="mon-profil" class="nav-cta">Mon profil</a>
        <a href="disconnect.php" class="nav-cta">Se déconnecter</a>
      <?php else: ?>
        <a href="connexion" class="nav-cta">Connexion</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="auth-page notes-page">
  <section class="notes-hero">
    <div class="notes-hero-kicker">MMI HUB · MMI NOTES</div>
    <h1>MMI NOTES</h1>
    <p>
      Suis tes <strong>moyennes</strong>, tes <strong>UE</strong> et tes
      <strong>compétences</strong> pour le semestre
      <span class="notes-semester-pill"><?= htmlspecialchars($currentSemesterCode) ?></span>.
    </p>

    <div class="notes-hero-actions">
      <a href="export_notes.php" class="btn btn-ghost notes-export-btn">
        Exporter toutes mes notes
      </a>
    </div>
  </section>


  <?php if (!$hasMaquette): ?>
    <section class="auth-card notes-summary-card">
      <p>
        La maquette officielle n'est pas encore configurée pour ton profil
        (BUT <?= (int)$user['but_year'] ?>, semestre <?= htmlspecialchars($currentSemesterCode) ?>,
        parcours <?= htmlspecialchars($user['parcours'] ?? '—') ?>).
      </p>
      <p style="margin-top:0.5rem;font-size:0.85rem;opacity:0.8;">
        Dès que les ressources et SAÉ auront été saisies dans la base (tables
        <code>ues</code>, <code>modules</code>, <code>ue_modules</code>),
        elles apparaîtront ici automatiquement.
      </p>
    </section>
  <?php else: ?>

  <section class="notes-grid">
    <!-- Carte résumé -->
    <div class="auth-card notes-summary-card">
      <div class="notes-summary-main">
        <p class="notes-summary-label">Moyenne globale semestre</p>
        <p class="notes-summary-value">
          <?= $stats['avg_global'] !== null ? number_format($stats['avg_global'], 2, ',', ' ') : '--' ?>
        </p>
<?php
$competencesList = $semesterStats['competences'] ?? [];
$totalComps      = count($competencesList);
$competencesOk   = $semesterStats['competences_ok'] ?? 0;
?>



      </div>

      <div class="notes-summary-tags">
        <span class="notes-chip <?= $stats['semester_valid'] ? 'notes-chip-ok' : 'notes-chip-warn' ?>">
          <?= $stats['semester_valid'] ? 'Semestre validé (règles MCC)' : 'Semestre non validé' ?>
        </span>
        <?php if ($stats['has_eliminatory']): ?>
          <span class="notes-chip notes-chip-fail">
            Attention : note éliminatoire &lt; 8
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Compétences -->
    <div class="auth-card notes-comp-card">
      <h2 class="notes-section-title">Compétences</h2>
      <div class="notes-comp-grid">
        <?php
        $labelsComp = [
          'C1' => 'Comprendre',
          'C2' => 'Concevoir',
          'C3' => 'Exprimer',
          'C4' => 'Développer',
          'C5' => 'Entreprendre',
        ];
        foreach ($stats['competences'] as $code => $c):
          $cls = 'notes-comp-status-warn';
          if ($c['status'] === 'ok')   $cls = 'notes-comp-status-ok';
          if ($c['status'] === 'fail') $cls = 'notes-comp-status-fail';
        ?>
          <div class="notes-comp-item <?= $cls ?>">
            <div class="notes-comp-header">
              <span class="notes-comp-code"><?= htmlspecialchars($code) ?></span>
              <span class="notes-comp-name"><?= htmlspecialchars($labelsComp[$code] ?? '') ?></span>
            </div>
            <div class="notes-comp-value">
              <?= $c['avg'] !== null ? number_format($c['avg'], 2, ',', ' ') : '--' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Absences & malus appliqués -->
    <div class="auth-card notes-abs-card">
      <h2 class="notes-section-title">Absences &amp; malus appliqués</h2>
      <p class="notes-help">
        Ici tu vois <strong>les ressources impactées</strong> par tes absences injustifiées
        ce semestre. Les points sont déjà retirés dans ta moyenne.
      </p>

      <?php
      // On ne garde que les ressources qui ont VRAIMENT un malus > 0
      $impactWithPenalty = array_filter($absenceImpact, function ($info) {
          return !empty($info['penalty']) && $info['penalty'] > 0;
      });
      ?>

      <?php if (!$impactWithPenalty): ?>
        <p style="font-size:0.85rem;opacity:0.8;">
          Pour l'instant, aucune ressource n'est pénalisée par tes absences. Continue comme ça 🔥
        </p>
      <?php else: ?>
        <div class="notes-modules-table-wrapper" style="max-height:none;">
          <table class="notes-modules-table">
            <thead>
              <tr>
                <th>Ressource</th>
                <th>Heures injustifiées</th>
                <th>Malus appliqué</th>
                <th>Dates d'absence</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($impactWithPenalty as $info): ?>
              <?php
                // Formatage des dates (Y-m-d -> d/m/Y) + unique
                $uniqueDates = array_values(array_unique($info['dates']));
                $datesLabel  = [];
                foreach ($uniqueDates as $d) {
                    $ts = strtotime($d);
                    if ($ts !== false) {
                        $datesLabel[] = date('d/m/Y', $ts);
                    }
                }
              ?>
              <tr>
                <td>
                  <span class="notes-module-code">
                    <?= htmlspecialchars($info['module_code']) ?>
                  </span>
                  <span class="notes-module-name">
                    <?= htmlspecialchars($info['module_name']) ?>
                  </span>
                </td>
                <td>
                  <?= number_format($info['hours'], 1, ',', ' ') ?> h
                </td>
                <td class="notes-text-bad">
                  −<?= number_format($info['penalty'], 2, ',', ' ') ?> pt
                </td>
                <td>
                  <?php if ($datesLabel): ?>
                    <?= htmlspecialchars(implode(', ', $datesLabel)) ?>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Ressources / SAÉ -->
    <div class="auth-card notes-modules-card">
      <h2 class="notes-section-title">Ressources &amp; SAÉ</h2>
      <p class="notes-help">
        Les éléments en <span class="notes-text-bad">rouge clair</span> sont en dessous de 10.
        Les éléments en <span class="notes-text-crit">rouge foncé</span> sont en dessous de 8.
      </p>
      <div class="notes-modules-table-wrapper">
        <table class="notes-modules-table">
          <thead>
            <tr>
              <th>UE</th>
              <th>Module</th>
              <th>Type</th>
              <th>Coef</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($stats['modules'] as $m):
            $grade = $m['grade'];
            $cls = '';
            if ($grade !== null) {
              if ($grade < 8)      $cls = 'notes-grade-crit';
              elseif ($grade <10 ) $cls = 'notes-grade-bad';
            }
          ?>
            <tr>
              <td><?= htmlspecialchars($m['ue_code']) ?></td>
              <td>
                <span class="notes-module-code"><?= htmlspecialchars($m['module_code']) ?></span>
                <span class="notes-module-name"><?= htmlspecialchars($m['module_name']) ?></span>
              </td>
              <td><?= $m['module_type'] === 'sae' ? 'SAÉ' : 'Ressource' ?></td>
              <td><?= $m['coef'] ?></td>
              <td class="<?= $cls ?>">
                <?= $grade !== null ? number_format($grade, 2, ',', ' ') : '--' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Historique des notes saisies -->
    <div class="auth-card notes-history-card">
      <h2 class="notes-section-title">Historique des notes</h2>
      <p class="notes-help">
        Tu peux <strong>modifier</strong> ou <strong>supprimer</strong> une note déjà saisie.
      </p>
      <?php if (!$assessments): ?>
        <p style="font-size:0.85rem;opacity:0.8;">Aucune note enregistrée pour l’instant.</p>
      <?php else: ?>
        <div class="notes-modules-table-wrapper">
          <table class="notes-modules-table">
            <thead>
              <tr>
                <th>Module</th>
                <th>Type</th>
                <th>Note</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($assessments as $a): ?>
              <tr>
                <td>
                  <span class="notes-module-code"><?= htmlspecialchars($a['module_code']) ?></span>
                  <span class="notes-module-name"><?= htmlspecialchars($a['module_name']) ?></span>
                </td>
                <td><?= $a['kind'] === 'sae' || $a['kind'] === 'unique' ? 'SAÉ' : strtoupper($a['kind']); ?></td>
                <td><?= number_format($a['grade'], 2, ',', ' ') ?></td>
                <td><?= htmlspecialchars(date('d/m H:i', strtotime($a['created_at']))) ?></td>
                <td>
                  <button type="button"
                          class="notes-edit-btn"
                          data-id="<?= (int)$a['id'] ?>"
                          data-module-id="<?= (int)$a['module_id'] ?>"
                          data-kind="<?= htmlspecialchars($a['kind']) ?>"
                          data-grade="<?= htmlspecialchars($a['grade']) ?>">
                    Modifier
                  </button>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette note ?');">
                    <input type="hidden" name="action" value="delete_assessment">
                    <input type="hidden" name="assessment_id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="notes-delete-btn">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Bouton flottant "+" -->
  <button class="notes-fab" id="notesFabBtn" aria-label="Ajouter une note">
    +
  </button>

  <!-- Panel ajout / édition de note -->
  <div class="notes-add-panel" id="notesAddPanel">
    <div class="notes-add-inner auth-card">
      <div class="notes-add-header">
        <h2>Ajouter / modifier une note</h2>
        <button type="button" class="notes-add-close" id="notesAddClose">×</button>
      </div>

      <form method="post" class="auth-form">
        <input type="hidden" name="action" value="add_note">
        <input type="hidden" name="assessment_id" id="notesAssessmentId" value="">

        <div>
          <label class="auth-label">Ressource / SAÉ</label>
<select name="module_id" class="auth-select" id="notesModuleSelect">
  <?php foreach ($moduleOptions as $row): ?>
    <option value="<?= (int)$row['module_id'] ?>"
            data-type="<?= htmlspecialchars($row['module_type']) ?>">
      <?= htmlspecialchars($row['module_code'] . ' · ' . $row['module_name']) ?>
    </option>
  <?php endforeach; ?>
</select>

        </div>

<div id="notesKindRow">
  <label class="auth-label">Type de note</label>
  <div style="display:flex;gap:0.75rem;">
    <label><input type="radio" name="kind" value="ds" checked> DS</label>
    <label><input type="radio" name="kind" value="tp"> TP</label>
    <!-- Utilisé uniquement pour SAÉ / Portfolio (on le coche en JS) -->
    <label style="display:none;"><input type="radio" name="kind" value="unique"> Unique</label>
  </div>
</div>


        <div>
          <label class="auth-label" id="notesGradeLabel">Note</label>
          <input type="number" step="0.01" min="0" max="20"
                 class="auth-input" name="grade" id="notesGradeInput" required>
        </div>

        <button type="submit" class="btn btn-primary btn-big" style="margin-top:0.5rem;">
          Enregistrer
        </button>
      </form>
    </div>
  </div>

  <?php endif; // hasMaquette ?>
</main>

<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> MMI HUB · Projet étudiant de Jan Jawish – BUT MMI.</p>
    <p class="footer-sub">Respect du RGPD · Tes données servent uniquement à ton suivi personnel.</p>
    <a href="mentions.php">Mentions legales</a><br>
    <a href="politique.php">Politique de confidentialité</a>
</footer>
<script>
  (function () {
    const navToggle = document.querySelector('.nav-toggle');
    const mainNav   = document.querySelector('.main-nav');

    if (!navToggle || !mainNav) return;

    navToggle.addEventListener('click', () => {
      const isOpen = navToggle.classList.toggle('is-open');
      document.body.classList.toggle('nav-open', isOpen);
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Quand on clique sur un lien du menu en mobile, on referme
    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navToggle.classList.remove('is-open');
        document.body.classList.remove('nav-open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  })();
</script>
<script src="app.js"></script>
</body>
</html>
