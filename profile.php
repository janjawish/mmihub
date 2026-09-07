<?php
// profile.php à la racine
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/track_visit.php';

if (empty($_SESSION['user_id'])) {
    header('Location: connexion');
    exit;
}
// Exemple : à adapter à ton code
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.profile_image,
        p.headline,
        p.about,
        p.skills,
        p.is_anonymous,
        p.show_year,
        p.show_parcours,
        p.show_photo
    FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Si aucun profil n’existe encore, on en crée un par défaut
if (!$profile) {
    $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)")->execute([$userId]);

    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

$userId = (int) $_SESSION['user_id'];

$successMessage = '';
$errorMessage   = '';

// Charger les infos utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // session incohérente
    session_destroy();
    header('Location: connexion');
    exit;
}

// Si l'année de BUT n'est pas 3, on force le type de formation à "none" dans l'affichage
if ((int)$user['but_year'] !== 3) {
    $user['formation_type'] = 'none';
}

require_once __DIR__ . '/includes/semester.php';
require_once __DIR__ . '/includes/absences.php';
function generate_profile_slug(PDO $pdo): string {
    while (true) {
        // 16 caractères hexa → genre 9s5dg9s5g95s (mais en hexa)
        $slug = bin2hex(random_bytes(8));
        $check = $pdo->prepare("SELECT id FROM profiles WHERE public_slug = ?");
        $check->execute([$slug]);
        if (!$check->fetchColumn()) {
            return $slug;
        }
    }
}

// Semestre courant (numéro + label)
$currentSemesterNumber = getCurrentSemesterNumberForUser($user);
$currentSemesterLabel  = 'S' . $currentSemesterNumber;

// Id de semestre pour les stats / absences
$currentSemesterId = (int)($user['current_semester_id'] ?? 0);
if (!$currentSemesterId && $currentSemesterNumber) {
    // fallback simple : S1->1, S2->2, etc.
    $currentSemesterId = (int)$currentSemesterNumber;
}

// Nombre de compétences validées (stats de semestre)
$validatedSkillsCount = null;
if ($currentSemesterId) {
    $stmt = $pdo->prepare("
        SELECT competences_validated
        FROM user_semester_stats
        WHERE user_id = ? AND semester_id = ?
    ");
    $stmt->execute([$userId, $currentSemesterId]);
    $validatedSkillsCount = $stmt->fetchColumn();
    if ($validatedSkillsCount === false) {
        $validatedSkillsCount = null;
    }
}

// Récap des absences pour ce semestre (VRAIES données)
$totalJustifiedHours   = 0;
$totalUnjustifiedHours = 0;

if ($currentSemesterId) {
    $absSummary = get_absence_summary($pdo, $userId, $currentSemesterId);
    // les clés dans includes/absences.php sont bien 'unjustified_hours' / 'justified_hours'
    $totalUnjustifiedHours = isset($absSummary['unjustified_hours']) ? (float)$absSummary['unjustified_hours'] : 0;
    $totalJustifiedHours   = isset($absSummary['justified_hours'])   ? (float)$absSummary['justified_hours']   : 0;
}

// Gestion des différents formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if ($profile && empty($profile['public_slug'])) {
    $slug = generate_profile_slug($pdo);
    $upd  = $pdo->prepare("UPDATE profiles SET public_slug = :s WHERE id = :id");
    $upd->execute([
        ':s'  => $slug,
        ':id' => $profile['id'],
    ]);
    $profile['public_slug'] = $slug;
}

// 1) Mise à jour infos perso (on NE TOUCHE PLUS à but_year / parcours / formation_type ici)
if (isset($_POST['action']) && $_POST['action'] === 'update_personal') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');

    // On garde la PP existante par défaut
    $profileImagePath = $user['profile_image'] ?? null;

    // Gestion upload PP (optionnel)
    if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['profile_image']['tmp_name'];
            $size    = (int)$_FILES['profile_image']['size'];

            // limite ~3 Mo
            if ($size > 3 * 1024 * 1024) {
                $errorMessage = "Ta photo est trop lourde (max 3 Mo).";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($tmpPath);

                $ext = null;
                if ($mime === 'image/jpeg') $ext = 'jpg';
                elseif ($mime === 'image/png') $ext = 'png';
                elseif ($mime === 'image/webp') $ext = 'webp';

                if ($ext === null) {
                    $errorMessage = "Format d'image non supporté (utilise jpg, png ou webp).";
                } else {
                    $uploadDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }

                    $fileName = 'u' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $destPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($tmpPath, $destPath)) {
                        // chemin relatif pour le site
                        $profileImagePath = 'uploads/avatars/' . $fileName;
                    } else {
                        $errorMessage = "Impossible d'enregistrer ta photo de profil.";
                    }
                }
            }
        } else {
            $errorMessage = "Erreur lors de l'upload de l'image.";
        }
    }

    if ($firstName === '' || $lastName === '') {
        $errorMessage = "Nom et prénom sont obligatoires.";
    } elseif ($birthDate === '') {
        $errorMessage = "Merci de renseigner ta date de naissance.";
    }

    if ($errorMessage === '') {
        $upd = $pdo->prepare("
            UPDATE users
            SET first_name    = :fn,
                last_name     = :ln,
                birth_date    = :bd,
                profile_image = :img
            WHERE id = :id
        ");
        $upd->execute([
            ':fn'  => $firstName,
            ':ln'  => $lastName,
            ':bd'  => $birthDate,
            ':img' => $profileImagePath,
            ':id'  => $userId,
        ]);

        $_SESSION['user_name'] = $firstName;
        $successMessage = "Tes informations personnelles ont été mises à jour.";
    }
}


    // 2) Mise à jour profil public
    if (isset($_POST['action']) && $_POST['action'] === 'update_public') {
        $headline     = trim($_POST['headline'] ?? '');
        $about        = trim($_POST['about'] ?? '');
        $skills       = trim($_POST['skills'] ?? '');
        $isAnonymous  = isset($_POST['is_anonymous']) ? 1 : 0;
        $showYear     = isset($_POST['show_year']) ? 1 : 0;
        $showParcours = isset($_POST['show_parcours']) ? 1 : 0;
        $showPhoto    = isset($_POST['show_photo']) ? 1 : 0;

        $upd = $pdo->prepare("
            UPDATE profiles
            SET headline = :headline,
                about    = :about,
                skills   = :skills,
                is_anonymous = :anon,
                show_year    = :sy,
                show_parcours= :sp,
                show_photo   = :sh
            WHERE user_id = :id
        ");
        $upd->execute([
            ':headline' => $headline,
            ':about'    => $about,
            ':skills'   => $skills,
            ':anon'     => $isAnonymous,
            ':sy'       => $showYear,
            ':sp'       => $showParcours,
            ':sh'       => $showPhoto,
            ':id'       => $userId,
        ]);

        $successMessage = "Ton profil public a été mis à jour.";
    }

    // 3) Activation / désactivation 2FA e-mail
    if (isset($_POST['action']) && $_POST['action'] === 'update_2fa') {
        $method = $_POST['twofa_method'] ?? 'none';
        if (!in_array($method, ['none','email'], true)) {
            $method = 'none';
        }

        $upd = $pdo->prepare("
            UPDATE users
            SET twofa_method = :m
            WHERE id = :id
        ");
        $upd->execute([
            ':m'  => $method,
            ':id' => $userId,
        ]);

        $successMessage = ($method === 'email')
            ? "La double authentification par e-mail est activée."
            : "La double authentification est désactivée.";
    }

    // 4) Changement de mot de passe
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $errorMessage = "L'ancien mot de passe est incorrect.";
        } elseif (strlen($new) < 8) {
            $errorMessage = "Le nouveau mot de passe doit faire au moins 8 caractères.";
        } elseif ($new !== $confirm) {
            $errorMessage = "Les nouveaux mots de passe ne correspondent pas.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password_hash = :ph WHERE id = :id");
            $upd->execute([
                ':ph' => $hash,
                ':id' => $userId,
            ]);
            $successMessage = "Ton mot de passe a été mis à jour.";
        }
    }

    // 5) Suppression de compte
    if (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
        $reason = trim($_POST['delete_reason'] ?? '');

        // Copier dans supp_users
        $ins = $pdo->prepare("
            INSERT INTO supp_users
                (original_user_id, first_name, last_name, email, birth_date,
                 profile_image, but_year, parcours, formation_type, delete_reason)
            VALUES
                (:uid, :fn, :ln, :em, :bd, :img, :by, :pc, :ft, :dr)
        ");
        $ins->execute([
            ':uid' => $user['id'],
            ':fn'  => $user['first_name'],
            ':ln'  => $user['last_name'],
            ':em'  => $user['email'],
            ':bd'  => $user['birth_date'],
            ':img' => $user['profile_image'],
            ':by'  => $user['but_year'],
            ':pc'  => $user['parcours'],
            ':ft'  => $user['formation_type'],
            ':dr'  => $reason,
        ]);

        // Supprimer profil + user (profiles est en ON DELETE CASCADE)
        $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $del->execute([':id' => $userId]);

        session_destroy();
        header('Location: accueil');
        exit;
    }

    // recharger les données après update
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // remettre formation_type à none si BUT != 3 pour l'affichage
    if ((int)$user['but_year'] !== 3) {
        $user['formation_type'] = 'none';
    }

    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
} else {
    // Chargement initial du profil public (ou création entrée vide)
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();

    if (!$profile) {
        $insert = $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)");
        $insert->execute([$userId]);
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title>Mon profil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
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

<main class="auth-page">
  <div class="profile-layout">

    <!-- COLONNE GAUCHE -->
    <section class="auth-intro profile-side">
      <h1>MON ESPACE MMI HUB</h1>
      <p>
        Ici tu gères ton <strong>profil public</strong>, ta <strong>sécurité (A2F)</strong>
        et tes <strong>informations personnelles</strong>.
      </p>

      <a href="export_profile.php" class="btn btn-ghost" style="margin-top:0.75rem;">
        Exporter mes données (profil)
      </a>

      <div class="profile-side-grid">
        <div class="profile-pill">
          <p class="profile-pill-label">Année</p>
          <p class="profile-pill-value">
            <?= 'BUT ' . htmlspecialchars($user['but_year']) ?>
          </p>
        </div>

        <?php if ((int)$user['but_year'] >= 2): ?>
        <div class="profile-pill">
          <p class="profile-pill-label">Parcours</p>
          <p class="profile-pill-value">
            <?php
              if ($user['parcours'] === 'crea') {
                  echo 'Créa';
              } elseif ($user['parcours'] === 'dev') {
                  echo 'Dev Web';
              } else {
                  echo 'Tronc commun';
              }
            ?>
          </p>
        </div>
        <?php endif; ?>

        <div class="profile-pill">
          <p class="profile-pill-label">Semestre actuel</p>
          <p class="profile-pill-value">
            <?= htmlspecialchars($currentSemesterLabel) ?>
          </p>
        </div>

        <div class="profile-pill">
          <p class="profile-pill-label">Compétences validées</p>
          <p class="profile-pill-value" style="color:#8405e7;">
            <?= $validatedSkillsCount !== null
                    ? htmlspecialchars($validatedSkillsCount) . ' / 5'
                    : '-- / 5' ?>
          </p>
        </div>

        <div class="profile-pill">
          <p class="profile-pill-label">Absences injustifiées</p>
          <p class="profile-pill-value" style="color:#e81652;">
            <?= htmlspecialchars($totalUnjustifiedHours) ?> h
          </p>
        </div>

        <div class="profile-pill">
          <p class="profile-pill-label">Absences justifiées</p>
          <p class="profile-pill-value" style="color:#1a03f4;">
            <?= htmlspecialchars($totalJustifiedHours) ?> h
          </p>
        </div>
      </div>
    </section>

    <!-- COLONNE DROITE -->
    <section class="profile-main">
      <!-- INFOS PERSO (pleine largeur) -->
      <div class="auth-card profile-section-card">
        <h2>Profil & sécurité</h2>
        <h3 style="margin-top:1rem;">Infos personnelles</h3>

        <?php if ($errorMessage): ?>
          <div class="auth-error"><p><?= htmlspecialchars($errorMessage) ?></p></div>
        <?php elseif ($successMessage): ?>
          <div class="auth-success"><p><?= htmlspecialchars($successMessage) ?></p></div>
        <?php endif; ?>

        <form method="post" class="auth-form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_personal">
<div class="profile-avatar-row">
  <div class="profile-avatar-wrapper">
    <?php if (!empty($user['profile_image'])): ?>
      <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Photo de profil"
           class="profile-avatar-img">
    <?php else: ?>
      <div class="profile-avatar-placeholder">
        <?= htmlspecialchars(strtoupper(mb_substr($user['first_name'], 0, 1, 'UTF-8'))) ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="profile-avatar-actions">
    <label class="auth-label">Photo de profil</label>
    <input class="auth-input" type="file" name="profile_image" accept="image/png,image/jpeg,image/webp">
    <p class="auth-help">Formats : JPG, PNG ou WebP · max ~3 Mo.</p>
  </div>
</div>

          <div class="auth-row-inline">
            <div>
              <label class="auth-label">Nom</label>
              <input class="auth-input" type="text" name="last_name"
                     value="<?= htmlspecialchars($user['last_name']) ?>">
            </div>
            <div>
              <label class="auth-label">Prénom</label>
              <input class="auth-input" type="text" name="first_name"
                     value="<?= htmlspecialchars($user['first_name']) ?>">
            </div>
          </div>

          <div>
            <label class="auth-label">Date de naissance</label>
            <input class="auth-input" type="date" name="birth_date"
                   value="<?= htmlspecialchars($user['birth_date']) ?>">
          </div>

          <!-- Champs verrouillés : année / parcours / formation ne sont plus modifiables ici -->
          <div>
            <label class="auth-label">Année de BUT</label>
            <input class="auth-input" type="text"
                   value="<?= 'BUT ' . htmlspecialchars($user['but_year']) ?>"
                   readonly>
          </div>

          <?php if ((int)$user['but_year'] >= 2): ?>
          <div>
            <label class="auth-label">Parcours</label>
            <input class="auth-input" type="text"
                   value="<?php
                        if ($user['parcours'] === 'crea') {
                            echo 'Création graphique';
                        } elseif ($user['parcours'] === 'dev') {
                            echo 'Développement Web';
                        } else {
                            echo 'Tronc commun';
                        }
                   ?>"
                   readonly>
          </div>
          <?php endif; ?>

          <?php if ((int)$user['but_year'] === 3): ?>
          <div>
            <label class="auth-label">Type de formation</label>
            <input class="auth-input" type="text"
                   value="<?php
                        if ($user['formation_type'] === 'fi') {
                            echo 'Formation initiale';
                        } elseif ($user['formation_type'] === 'fa') {
                            echo 'Alternance (FA)';
                        } else {
                            echo 'Non défini';
                        }
                   ?>"
                   readonly>
          </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">
            Mettre à jour
          </button>
        </form>
      </div>

      <!-- LES 4 CARTES DU BAS EN GRILLE 2 COLONNES -->
      <div class="profile-main-grid">

        <!-- PROFIL PUBLIC -->
        <div class="auth-card profile-section-card">
          <h3>Profil public</h3>
          <?php if (!empty($profile['public_slug'])): ?>
  <p class="auth-help" style="margin-bottom:0.6rem;">
    Lien de ton profil public :
    <a href="public_profile.php?u=<?= htmlspecialchars($profile['public_slug']) ?>" target="_blank">
      public_profile.php?u=<?= htmlspecialchars($profile['public_slug']) ?>
    </a>
  </p>
<?php endif; ?>

          <form method="post" class="auth-form">
            <input type="hidden" name="action" value="update_public">

            <div>
              <label class="auth-label">Phrase d'accroche</label>
              <input class="auth-input" type="text" name="headline" data-anon-lock
                     value="<?= htmlspecialchars($profile['headline'] ?? '') ?>">
            </div>
            <div>
              <label class="auth-label">À propos</label>
              <textarea class="auth-input" name="about" rows="3" data-anon-lock
                        style="border-radius:18px;resize:vertical;"><?= htmlspecialchars($profile['about'] ?? '') ?></textarea>
            </div>
            <div>
              <label class="auth-label">Domaines où tu es fort</label>
              <input class="auth-input" type="text" name="skills" data-anon-lock
                     placeholder="ex : front-end, UX, montage vidéo"
                     value="<?= htmlspecialchars($profile['skills'] ?? '') ?>">
            </div>

            <div style="display:flex;flex-direction:column;gap:0.35rem;margin-top:0.4rem;">
              <label>
                <input type="checkbox" id="is_anonymous_checkbox" name="is_anonymous"
                       <?= !empty($profile['is_anonymous']) ? 'checked' : '' ?>>
                Profil anonyme
              </label>
              <label>
                <input type="checkbox" name="show_year" data-anon-lock
                       <?= !empty($profile['show_year']) ? 'checked' : '' ?>>
                Afficher mon année de BUT
              </label>
              <label>
                <input type="checkbox" name="show_parcours" data-anon-lock
                       <?= !empty($profile['show_parcours']) ? 'checked' : '' ?>>
                Afficher mon parcours
              </label>
              <label>
                <input type="checkbox" name="show_photo" data-anon-lock
                       <?= !empty($profile['show_photo']) ? 'checked' : '' ?>>
                Afficher ma photo de profil
              </label>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">
              Mettre à jour le profil public
            </button>
          </form>
        </div>

        <!-- A2F -->
        <div class="auth-card profile-section-card">
          <h3>Sécurité (A2F)</h3>
          <form method="post" class="auth-form">
            <input type="hidden" name="action" value="update_2fa">
            <div>
              <label class="auth-label">Double authentification</label>
              <select class="auth-select" name="twofa_method">
                <option value="none"  <?= $user['twofa_method'] === 'none'  ? 'selected' : '' ?>>Désactivée</option>
                <option value="email" <?= $user['twofa_method'] === 'email' ? 'selected' : '' ?>>Code par e-mail</option>
              </select>
              <p class="auth-help">
                Si une connexion vient d'un nouvel appareil ou d'une nouvelle IP, on t’enverra un
                <strong>code A2F par e-mail</strong> à saisir avant d'accéder à ton compte.
              </p>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">
              Mettre à jour la sécurité
            </button>
          </form>
        </div>

        <!-- MOT DE PASSE -->
        <div class="auth-card profile-section-card">
          <h3>Mot de passe</h3>
          <form method="post" class="auth-form">
            <input type="hidden" name="action" value="update_password">
            <div>
              <label class="auth-label">Mot de passe actuel</label>
              <input class="auth-input" type="password" name="current_password">
            </div>
            <div class="auth-row-inline">
              <div>
                <label class="auth-label">Nouveau mot de passe</label>
                <input class="auth-input" type="password" name="new_password">
              </div>
              <div>
                <label class="auth-label">Confirmation</label>
                <input class="auth-input" type="password" name="new_password_confirm">
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">
              Changer mon mot de passe
            </button>
          </form>
        </div>

        <!-- SUPPRESSION -->
        <div class="auth-card profile-section-card profile-section-danger">
          <h3>Suppression du compte</h3>
          <form method="post" class="auth-form"
                onsubmit="return confirm('Tu es sûr de vouloir supprimer ton compte ? Cette action est définitive.');">
            <input type="hidden" name="action" value="delete_account">
            <div>
              <label class="auth-label">Raison (optionnel)</label>
              <input class="auth-input" type="text" name="delete_reason"
                     placeholder="ex : je n'utilise plus MMI HUB">
            </div>
            <button type="submit" class="btn btn-ghost" style="margin-top:0.5rem;">
              Supprimer mon compte
            </button>
          </form>
        </div>

      </div> <!-- /.profile-main-grid -->

    </section>
  </div>
</main>

<script>
  // Profil anonyme : on grise / désactive les champs marqués data-anon-lock
  const anonCheckbox = document.getElementById('is_anonymous_checkbox');
  const anonTargets  = document.querySelectorAll('[data-anon-lock]');

  function updateAnonState() {
    if (!anonCheckbox) return;
    const disabled = anonCheckbox.checked;
    anonTargets.forEach(el => {
      el.disabled = disabled;
      if (disabled) {
        el.classList.add('is-disabled');
      } else {
        el.classList.remove('is-disabled');
      }
    });
  }

  if (anonCheckbox) {
    updateAnonState();
    anonCheckbox.addEventListener('change', updateAnonState);
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
