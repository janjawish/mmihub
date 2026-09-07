<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/track_visit.php';

// Si l'utilisateur est déjà connecté, on le redirige vers le tableau de bord
if (!empty($_SESSION['user_id'])) {
    header('Location: /mmihub/accueil');
    exit;
}

// ... reste de ton code de connexion

// Liste de domaines de mails jetables à bloquer
$DISPOSABLE_DOMAINS = [
    '10mail.org','10minutemail.com','10minutemail.net','10minutesemail.net',
    '20minutemail.com','33mail.com',
    'anonymbox.com','anonmails.de',
    'bccto.me','binkmail.com',
    'courrieltemporaire.com','cool.fr.nf','courriel.fr.nf',
    'deadaddress.com','dayrep.com','discard.email','discardmail.com',
    'dispostable.com','dodgeit.com',
    'emailondeck.com','emailtemporanea.com','emailtemporanea.net','emailtemporar.ro',
    'fakeinbox.com','fakeinbox.org','fakemailgenerator.com',
    'getairmail.com','getnada.com','guerrillamail.com','guerrillamail.de',
    'guerrillamail.net','guerrillamail.org','guerrillamail.biz','sharklasers.com',
    'hottempmail.com',
    'jetable.org','jetable.fr.nf','emailjetable.com',
    'mailcatch.com','maildrop.cc','mail-temporaire.fr','mail-temporaire.com',
    'mailinator.com','mailinator.net','mailinator.org','mailinator2.com',
    'mailnesia.com','mohmal.com','mvrht.com',
    'mytemp.email','mytrashmailer.com',
    'nospam.ze.tc','nowmymail.com','nada.email',
    'o2stk.org','one-time.email',
    'outmail.win','owlpic.com',
    'pookmail.com','privacy.net',
    'shut.name','sofimail.com','spam4.me','spamgourmet.com','spambog.com',
    'temporary-email.com','temporarymail.com','temp-mail.org','temp-mail.io',
    'tempmail.com','tempmail.net','tempmailaddress.com','tempinbox.com',
    'throwawaymail.com','trash-mail.com','trashmail.com','trashmail.de',
    'trashmailer.com','trashymail.com',
    'yopmail.com','yopmail.fr','yopmail.net',
    'zybermail.com'
    // tu peux rajouter d'autres domaines ici si tu en découvres
];

// renvoie true si l'email est jetable
function is_disposable_email(string $email, array $blacklist): bool {
    $parts = explode('@', strtolower(trim($email)));
    if (count($parts) !== 2) {
        return true; // pas un email valide => on bloque
    }
    $domain = $parts[1];
    return in_array($domain, $blacklist, true);
}

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName   = trim($_POST['first_name'] ?? '');
    $lastName    = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $birthDate   = trim($_POST['birth_date'] ?? '');
    $butYear     = (int)($_POST['but_year'] ?? 0);
    $parcours    = $_POST['parcours'] ?? 'none';
    $formation   = $_POST['formation_type'] ?? 'none';
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password_confirm'] ?? '';

    // Validations basiques
    if ($firstName === '' || $lastName === '') {
        $errors[] = "Merci de renseigner ton nom et prénom.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse e-mail invalide.";
    } elseif (is_disposable_email($email, $DISPOSABLE_DOMAINS)) {
        $errors[] = "Les adresses e-mail jetables ne sont pas autorisées.";
    }

    if ($birthDate === '') {
        $errors[] = "Merci d'indiquer ta date de naissance.";
    }

    if (!in_array($butYear, [1, 2, 3], true)) {
        $errors[] = "Merci de choisir une année de BUT valide.";
    }

// Parcours : obligatoire en BUT 2 et BUT 3
if (in_array($butYear, [2, 3], true) && !in_array($parcours, ['crea','dev'], true)) {
    $errors[] = "Merci de choisir ton parcours (Création graphique ou Développement Web).";
}
if ($butYear === 1) {
    $parcours = 'none';
}

// Type de formation : obligatoire seulement en BUT 3
if ($butYear === 3 && !in_array($formation, ['fi','fa'], true)) {
    $errors[] = "Merci de préciser si tu es en Formation initiale ou en Alternance.";
}
if ($butYear !== 3) {
    $formation = 'none';
}


    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit faire au moins 8 caractères.";
    } elseif ($password !== $password2) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    // Acceptation des mentions légales / politique de confidentialité
    $acceptedLegal = !empty($_POST['accept_legal']);
    if (!$acceptedLegal) {
        $errors[] = "Tu dois accepter les mentions légales et la politique de confidentialité pour créer un compte.";
    }

// Upload photo (optionnel)
$profileImagePath = null;
if (!empty($_FILES['profile_image']['name'])) {
    $file = $_FILES['profile_image'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($file['tmp_name']) ?: '';

        if (!isset($allowedTypes[$mime])) {
            $errors[] = "La photo de profil doit faire en JPG, PNG ou WEBP.";
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = "La photo de profil doit faire moins de 3 Mo.";
        } else {
            $ext      = $allowedTypes[$mime];
            $fileName = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            // Dossier /uploads/profile à la *racine* du projet
            $destDir = dirname(__DIR__) . '/uploads/profile';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $destPath = $destDir . '/' . $fileName;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Chemin utilisé dans les <img>, depuis la racine /mmihub
                $profileImagePath = 'uploads/profile/' . $fileName;
            } else {
                $errors[] = "Impossible d'enregistrer la photo de profil.";
            }
        }
    } else {
        $errors[] = "Erreur lors de l'upload de la photo (code " . $file['error'] . ").";
    }
}



    if (!$errors) {
        // Vérifier si email déjà utilisé dans users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Un compte existe déjà avec cette adresse e-mail.";
        } else {
            // Vérifier si email déjà en attente dans temp_users
            $stmt = $pdo->prepare("SELECT id FROM temp_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Un compte en attente de vérification existe déjà avec cette adresse e-mail.";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                // Code lisible pour l'utilisateur (6 chiffres)
                $token = (string) random_int(100000, 999999);

                // Insertion dans temp_users (compte en attente)
                $stmt = $pdo->prepare("
                    INSERT INTO temp_users
                        (first_name, last_name, email, password_hash, birth_date,
                         profile_image, but_year, parcours, formation_type,
                         verification_token, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
                ");

                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $passwordHash,
                    $birthDate,
                    $profileImagePath,
                    $butYear,
                    $parcours,
                    $formation,
                    $token
                ]);

// Envoi e-mail de vérification
if (sendVerificationEmail($email, $token)) {
    $_SESSION['pending_email'] = $email;

    // redirection vers la page de saisie du code
    header('Location: verification');
    exit;
} else {
    $errors[] = "Compte créé, mais l'e-mail de vérification n'a pas pu être envoyé. Contacte l'admin.";
}


            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="/mmihub/favicon.ico">
    <meta charset="UTF-8">
    <title>Créer un compte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mmihub/style.css">
        <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<?php /* header identique à l'index */ ?>
<header class="site-header">
  <div class="header-inner">
    <a href="/mmihub/accueil#top" class="logo">
      <span class="logo-mark">MMI</span>
      <span class="logo-word">HUB</span>
    </a>

  </div>
</header>

<main class="auth-page">
  <div class="auth-grid">
    <section class="auth-intro">
      <h1>Créer mon compte</h1>
      <p>
        Un compte MMI HUB te permet de suivre tes <strong>notes</strong>,
        tes <strong>absences</strong>, et de profiter des outils MMI Chat
        et MMI Services.
      </p>
      <p class="auth-help">
        Les données demandées restent privées et servent uniquement à ton
        suivi personnel, dans le respect du RGPD.
      </p>
    </section>

    <section class="auth-card">
      <h2>Inscription</h2>

      <?php if ($errors): ?>
        <div class="auth-error">
          <?php foreach ($errors as $err): ?>
            <p><?= htmlspecialchars($err) ?></p>
          <?php endforeach; ?>
        </div>
      <?php elseif ($successMessage): ?>
        <div class="auth-success">
          <p><?= htmlspecialchars($successMessage) ?></p>
        </div>
      <?php endif; ?>

      <form class="auth-form" method="post" enctype="multipart/form-data" novalidate>
        <div class="auth-row-inline">
          <div>
            <label class="auth-label" for="last_name">Nom</label>
            <input class="auth-input" type="text" id="last_name" name="last_name"
                   value="<?= htmlspecialchars($lastName ?? '') ?>" required>
          </div>
          <div>
            <label class="auth-label" for="first_name">Prénom</label>
            <input class="auth-input" type="text" id="first_name" name="first_name"
                   value="<?= htmlspecialchars($firstName ?? '') ?>" required>
          </div>
        </div>

        <div>
          <label class="auth-label" for="email">E-mail étudiant</label>
          <input class="auth-input" type="email" id="email" name="email"
                 value="<?= htmlspecialchars($email ?? '') ?>" required>
          <p class="auth-help">Les mails jetables sont refusés.</p>
        </div>

        <div>
          <label class="auth-label" for="birth_date">Date de naissance</label>
          <input class="auth-input" type="date" id="birth_date" name="birth_date"
                 value="<?= htmlspecialchars($birthDate ?? '') ?>" required>
        </div>

        <div>
          <label class="auth-label" for="profile_image">Photo de profil (optionnel)</label>
          <input class="auth-input" type="file" id="profile_image" name="profile_image" accept="image/*">
        </div>

        <div>
          <label class="auth-label" for="but_year">Année de BUT</label>
          <select class="auth-select" id="but_year" name="but_year" required>
            <option value="">Choisir...</option>
            <option value="1" <?= (isset($butYear) && $butYear === 1) ? 'selected' : '' ?>>BUT 1</option>
            <option value="2" <?= (isset($butYear) && $butYear === 2) ? 'selected' : '' ?>>BUT 2</option>
            <option value="3" <?= (isset($butYear) && $butYear === 3) ? 'selected' : '' ?>>BUT 3</option>
          </select>
        </div>

        <div id="parcours-field" style="display:none;">
          <label class="auth-label" for="parcours">Parcours (à partir de BUT 2)</label>
          <select class="auth-select" id="parcours" name="parcours">
            <option value="">Choisir...</option>
            <option value="crea" <?= (isset($parcours) && $parcours === 'crea') ? 'selected' : '' ?>>Création graphique</option>
            <option value="dev"  <?= (isset($parcours) && $parcours === 'dev')  ? 'selected' : '' ?>>Développement Web</option>
          </select>
        </div>

        <div id="formation-field" style="display:none;">
          <label class="auth-label" for="formation_type">Type de formation (BUT 3)</label>
          <select class="auth-select" id="formation_type" name="formation_type">
            <option value="">Choisir...</option>
            <option value="fi" <?= (isset($formation) && $formation === 'fi') ? 'selected' : '' ?>>Formation initiale</option>
            <option value="fa" <?= (isset($formation) && $formation === 'fa') ? 'selected' : '' ?>>Alternance (FA)</option>
          </select>
        </div>

        <div class="auth-row-inline">
          <div>
            <label class="auth-label" for="password">Mot de passe</label>
            <input class="auth-input" type="password" id="password" name="password" required>
          </div>
          <div>
            <label class="auth-label" for="password_confirm">Confirmation</label>
            <input class="auth-input" type="password" id="password_confirm" name="password_confirm" required>
          </div>
        </div>

        <div class="auth-legal" style="margin-top:0.6rem;">
          <label style="display:flex; align-items:flex-start; gap:0.5rem; cursor:pointer;">
            <input
              type="checkbox"
              name="accept_legal"
              value="1"
              required
              <?= !empty($_POST['accept_legal']) ? 'checked' : '' ?>
            >
            <span style="font-size:0.9rem; line-height:1.4;">
              En créant un compte, je reconnais avoir lu et accepté les
              <a href="/mmihub/mentions-legales" target="_blank">mentions légales</a>
              et la
              <a href="/mmihub/politique-confidentialite" target="_blank">politique de confidentialité</a>.
            </span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.6rem;">
          Créer mon compte
        </button>

        <p class="auth-footer">
          Tu as déjà un compte ? <a href="connexion" class="btn-link">Se connecter</a>
        </p>
      </form>

    </section>
  </div>
</main>

<script>
  // Affichage dynamique des champs selon l'année de BUT
  const butSelect = document.getElementById('but_year');
  const parcoursField = document.getElementById('parcours-field');
  const formationField = document.getElementById('formation-field');

function updateFields() {
  const year = parseInt(butSelect.value || '0', 10);

  // Parcours visible en BUT 2 et BUT 3
  parcoursField.style.display = (year >= 2) ? 'block' : 'none';

  // Type de formation visible seulement en BUT 3
  formationField.style.display = (year === 3) ? 'block' : 'none';
}


  butSelect.addEventListener('change', updateFields);
  updateFields();
</script>
</body>
</html>
