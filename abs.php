<?php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/semester.php';
require_once __DIR__ . '/includes/maquette_loader.php';
require_once __DIR__ . '/includes/absences.php';
require_once __DIR__ . '/includes/track_visit.php';

$user = require_login($pdo);

// Semestre courant (S1..S6)
$currentSemesterId   = getCurrentSemesterNumberForUser($user);
$currentSemesterCode = 'S' . $currentSemesterId;

// Maquette du semestre : sert uniquement à proposer la liste des ressources
$maquette    = loadSemesterMaquetteForUser($pdo, $user, $currentSemesterId);
$hasMaquette = !empty($maquette);

// Liste unique des modules pour le <select>
$moduleOptions = [];
foreach ($maquette as $row) {
    $mid = (int)$row['module_id'];
    if (!isset($moduleOptions[$mid])) {
        $moduleOptions[$mid] = $row;
    }
}

// ========= GESTION DES FORMULAIRES =========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ajout / modification d'une absence
    if ($action === 'add_absence') {
        $absenceId  = !empty($_POST['absence_id']) ? (int)$_POST['absence_id'] : null;
        $moduleId   = (int)($_POST['module_id'] ?? 0);
        $hours      = isset($_POST['hours']) ? (float)$_POST['hours'] : 1.0;
        if ($hours <= 0) $hours = 1.0;
        $justified  = !empty($_POST['justified']) ? 1 : 0;
        $comment    = trim($_POST['comment'] ?? '');

        // Date au format d/m/Y -> Y-m-d
$dateStr = trim($_POST['absence_date'] ?? '');

if ($dateStr) {
    // input type="date" => format HTML5 "YYYY-MM-DD"
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt) {
        $errors['absence_date'] = 'Date invalide';
    } else {
        // format déjà compatible MySQL DATE
        $dateSql = $dt->format('Y-m-d');
    }
}


        if ($moduleId > 0) {
            if ($absenceId) {
                // Edition
                $stmt = $pdo->prepare("
                    UPDATE absences
                    SET module_id    = :mid,
                        absence_date = :d,
                        hours        = :h,
                        justified    = :j,
                        comment      = :c,
                        updated_at   = NOW()
                    WHERE id = :id
                      AND user_id = :uid
                ");
                $stmt->execute([
                    ':mid' => $moduleId,
                    ':d'   => $dateSql,
                    ':h'   => $hours,
                    ':j'   => $justified,
                    ':c'   => $comment !== '' ? $comment : null,
                    ':id'  => $absenceId,
                    ':uid' => $user['id'],
                ]);
            } else {
                // Nouvelle absence
                $stmt = $pdo->prepare("
                    INSERT INTO absences
                        (user_id, semester_id, module_id, absence_date, hours, justified, comment)
                    VALUES
                        (:uid, :sid, :mid, :d, :h, :j, :c)
                ");
                $stmt->execute([
                    ':uid' => $user['id'],
                    ':sid' => $currentSemesterId,
                    ':mid' => $moduleId,
                    ':d'   => $dateSql,
                    ':h'   => $hours,
                    ':j'   => $justified,
                    ':c'   => $comment !== '' ? $comment : null,
                ]);
            }
        }

        header('Location: mmi-abs');
        exit;
    }

    // Bascule justifiée / injustifiée
    if ($action === 'toggle_justified') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("
                UPDATE absences
                SET justified  = 1 - justified,
                    updated_at = NOW()
                WHERE id = :id
                  AND user_id = :uid
            ")->execute([
                ':id'  => $id,
                ':uid' => $user['id'],
            ]);
        }
        header('Location: mmmi-abs');
        exit;
    }

    // Suppression
    if ($action === 'delete_absence') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("
                DELETE FROM absences
                WHERE id = :id
                  AND user_id = :uid
            ")->execute([
                ':id'  => $id,
                ':uid' => $user['id'],
            ]);
        }
        header('Location: mmi-abs');
        exit;
    }
}

// ========= DONNÉES POUR L'AFFICHAGE =========
$absSummary = get_absence_summary($pdo, $user['id'], $currentSemesterId);
$absences   = get_user_absences($pdo, $user['id'], $currentSemesterId);

$unjustified = $absSummary['unjustified_hours'];
$justified   = $absSummary['justified_hours'];
$threshold   = $absSummary['threshold'];
$excess      = $absSummary['excess_hours'];
$penalties   = $absSummary['penalties'];
$details     = $absSummary['details'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title>MMI ABS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="abs-body">
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
      <a href="/mmihub/mmi-edt" class="nav-link nav-services" >MMI EDT</a>
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
    <section class="abs-hero">
        <div class="abs-hero-kicker">MMI HUB · MMI ABS</div>
        <h1>MMI ABS</h1>
        <p>
            Suis tes <strong>absences</strong> (justifiées / injustifiées)
            pour le semestre
            <span class="abs-hero-badge"><?= htmlspecialchars($currentSemesterCode) ?></span>.
        </p>

        <div class="notes-actions" style="margin-top:0.75rem;">
            <a href="export_absences.php" class="btn btn-ghost">
                Exporter mes absences
            </a>
        </div>
    </section>

    <section class="notes-grid">
        <!-- Résumé -->
        <div class="abs-card">
            <p><strong>Absences injustifiées :</strong>
                <?= number_format($unjustified, 1, ',', ' ') ?> h / <?= number_format($threshold, 1, ',', ' ') ?> h
            </p>
            <p><strong>Absences justifiées :</strong>
                <?= number_format($justified, 1, ',', ' ') ?> h
            </p>

            <p style="margin-top:1rem;font-size:.9rem;">
                <?php if ($excess <= 0): ?>
                    Pour l’instant, tu n’as pas dépassé les 10h d’absences injustifiées.
                <?php else: ?>
                    Tu as dépassé les 10h d’absences injustifiées (<?= number_format($excess,1,',',' ') ?> h au-delà).
                    Des malus sont appliqués sur certaines ressources.
                <?php endif; ?>
            </p>

            <?php if ($details): ?>
                <p style="margin-top:1.2rem;font-size:.9rem;"><strong>Détails des malus appliqués :</strong></p>
                <ul style="margin:0;padding-left:1.2rem;font-size:.85rem;">
                    <?php foreach ($details as $line): ?>
                        <li><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div style="margin-top:1.4rem;">
                <?php if ($excess <= 0): ?>
                    <span class="abs-summary-ok-chip">Dans la limite des 10h</span>
                <?php else: ?>
                    <span class="abs-summary-bad-chip">Seuil dépassé</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique -->
        <div class="abs-card">
            <h2 class="notes-section-title">Historique des absences</h2>
            <p class="notes-help">
                Tes absences déclarées pour ce semestre. Tu peux les basculer en
                <strong>justifiée / injustifiée</strong> ou les <strong>supprimer</strong>.
            </p>

            <?php if (!$absences): ?>
                <p style="font-size:.85rem;opacity:.8;">Bien joué, aucune absence déclarée pour l’instant 👀</p>
            <?php else: ?>
                <div class="notes-modules-table-wrapper" style="max-height:none;">
                    <table class="notes-modules-table abs-history-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ressource</th>
                            <th>Heures</th>
                            <th>Justifiée</th>
                            <th>Commentaire</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($absences as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['absence_date']) ?></td>
                                <td>
                                    <span class="notes-module-code"><?= htmlspecialchars($a['module_code']) ?></span>
                                    <span class="notes-module-name"><?= htmlspecialchars($a['module_name']) ?></span>
                                </td>
                                <td><?= number_format($a['hours'], 1, ',', ' ') ?></td>
                                <td>
                                    <?php if ($a['justified']): ?>
                                        <span class="abs-chip-yes">Oui</span>
                                    <?php else: ?>
                                        <span class="abs-chip-no">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $a['comment'] ? htmlspecialchars($a['comment']) : '—' ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_justified">
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button class="abs-action-link" type="submit">
                                            <?= $a['justified'] ? 'Marquer injustifiée' : 'Marquer justifiée' ?>
                                        </button>
                                    </form>
                                    &nbsp;|&nbsp;
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette absence ?');">
                                        <input type="hidden" name="action" value="delete_absence">
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button class="abs-action-link" type="submit">Supprimer</button>
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
    <button class="abs-fab" id="absFabBtn" aria-label="Ajouter une absence">+</button>

    <!-- Panel ajout / édition -->
    <div class="abs-add-panel" id="absAddPanel">
        <div class="abs-add-inner abs-card">
            <div class="notes-add-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                <h2>Ajouter une absence</h2>
                <button type="button" class="notes-add-close" id="absAddClose" style="border:none;background:none;color:#ffffff;font-size:1.4rem;cursor:pointer;">×</button>
            </div>

            <form method="post" class="auth-form">
                <input type="hidden" name="action" value="add_absence">
                <input type="hidden" name="absence_id" id="absId" value="">

                <div>
                    <label class="auth-label">Date</label>
                    <input
  type="date"
  name="absence_date"
  class="auth-input"
  value="<?= htmlspecialchars($formData['absence_date'] ?? '') ?>"
  required
>
                </div>

                <div>
                    <label class="auth-label">Nombre d'heures</label>
                    <input type="number" step="0.5" min="0.5" max="24" class="abs-input" name="hours" value="1">
                </div>

                <div>
                    <label class="auth-label">Ressource / SAÉ</label>
                    <select name="module_id" class="abs-select">
                        <?php foreach ($moduleOptions as $row): ?>
                            <option value="<?= (int)$row['module_id'] ?>">
                                <?= htmlspecialchars($row['module_code'] . ' · ' . $row['module_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;">
                    <input type="checkbox" id="absJustified" name="justified" value="1">
                    <label for="absJustified">Absence <strong>justifiée</strong></label>
                </div>

                <div>
                    <label class="auth-label">Commentaire (optionnel)</label>
                    <textarea class="abs-textarea" name="comment" placeholder="ex : RDV médical, certificat à fournir…"></textarea>
                </div>

                <button type="submit" class="abs-btn-primary">Enregistrer l'absence</button>
            </form>
        </div>
    </div>
</main>

<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> MMI HUB · Projet étudiant de Jan Jawish – BUT MMI.</p>
    <p class="footer-sub">Respect du RGPD · Tes données servent uniquement à ton suivi personnel.</p>
    <a href="mentions.php">Mentions legales</a><br>
    <a href="politique.php">Politique de confidentialité</a>
</footer>

<script>
    // Ouverture / fermeture du panel avec le bouton +
    const absFabBtn   = document.getElementById('absFabBtn');
    const absAddPanel = document.getElementById('absAddPanel');
    const absAddClose = document.getElementById('absAddClose');

    if (absFabBtn && absAddPanel && absAddClose) {
        absFabBtn.addEventListener('click', () => {
            absAddPanel.classList.add('open');
        });
        absAddClose.addEventListener('click', () => {
            absAddPanel.classList.remove('open');
        });
        absAddPanel.addEventListener('click', (e) => {
            if (e.target === absAddPanel) {
                absAddPanel.classList.remove('open');
            }
        });
    }
</script>
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
</body>
</html>
