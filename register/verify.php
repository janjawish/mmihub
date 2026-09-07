<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/track_visit.php';

$verified = false;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['verification_code'] ?? '');

    if ($code === '') {
        $errorMsg = "Merci d'entrer le code reçu par e-mail.";
    } else {
        // 1) On cherche dans temp_users (comptes non vérifiés)
        $stmt = $pdo->prepare("
            SELECT * FROM temp_users
            WHERE verification_token = ?
        ");
        $stmt->execute([$code]);
        $tempUser = $stmt->fetch();

        if ($tempUser) {
            // (optionnel) check expiration si tu utilises expires_at
            if (!empty($tempUser['expires_at']) && strtotime($tempUser['expires_at']) < time()) {
                $errorMsg = "Ce code a expiré. Merci de recommencer l'inscription.";
            } else {
                // 2) On insère dans users (compte validé)
                $insert = $pdo->prepare("
                    INSERT INTO users
                        (first_name, last_name, email, password_hash, birth_date,
                         profile_image, but_year, parcours, formation_type,
                         legal_accepted, legal_accepted_at)
                    VALUES
                        (:first_name, :last_name, :email, :password_hash, :birth_date,
                         :profile_image, :but_year, :parcours, :formation_type,
                         :legal_accepted, :legal_accepted_at)
                ");

                $now = date('Y-m-d H:i:s');

                $insert->execute([
                    ':first_name'        => $tempUser['first_name'],
                    ':last_name'         => $tempUser['last_name'],
                    ':email'             => $tempUser['email'],
                    ':password_hash'     => $tempUser['password_hash'],
                    ':birth_date'        => $tempUser['birth_date'],
                    ':profile_image'     => $tempUser['profile_image'],
                    ':but_year'          => $tempUser['but_year'],
                    ':parcours'          => $tempUser['parcours'],
                    ':formation_type'    => $tempUser['formation_type'],
                    ':legal_accepted'    => 1,
                    ':legal_accepted_at' => $now,
                ]);

                $newUserId = $pdo->lastInsertId();

                // 3) On supprime de temp_users
                $del = $pdo->prepare("DELETE FROM temp_users WHERE id = :id");
                $del->execute([':id' => $tempUser['id']]);

                // 4) On connecte l'utilisateur directement
                $_SESSION['user_id']    = $newUserId;
                $_SESSION['user_name']  = $tempUser['first_name'];
                $_SESSION['user_email'] = $tempUser['email'];

                // 5) On marque comme vérifié et on redirige vers le profil public
                $verified = true;
                header('Location: /mmihub/mon-profil'); // profile.php à la racine du projet
                exit;
            }
        } else {
            $errorMsg = "Code invalide. Vérifie ton code ou refais l'inscription.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="../favicon.ico">
    <meta charset="UTF-8">
    <title>Vérification e-mail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mmihub/style.css">
        <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <a href="/mmihub/accueil#top" class="logo">
      <span class="logo-mark">MMI</span>
      <span class="logo-word">HUB</span>
    </a>
  </div>
</header>

<main class="auth-page">
  <section class="auth-card" style="max-width:520px;margin:2rem auto 4rem;">
    <h2>Vérification de l’e-mail</h2>

    <?php if ($verified): ?>
      <div class="auth-success">
        <p>Ton adresse e-mail est maintenant vérifiée 🎉</p>
      </div>
      <p>
        Tu peux maintenant te connecter à ton compte MMI HUB.
      </p>
      <p style="margin-top:1rem;">
        <a href="login.php" class="btn btn-primary">Aller à la connexion</a>
      </p>

    <?php else: ?>
      <?php if ($errorMsg): ?>
        <div class="auth-error">
          <p><?= htmlspecialchars($errorMsg) ?></p>
        </div>
      <?php endif; ?>

      <p class="auth-help">
        Entre le code à 6 chiffres que tu as reçu par e-mail pour activer ton compte MMI HUB.
      </p>

      <form method="post" class="auth-form" novalidate>
        <div>
          <label class="auth-label" for="verification_code">Code de vérification</label>
          <input class="auth-input" type="text" id="verification_code" name="verification_code"
                 inputmode="numeric" pattern="[0-9]*" maxlength="6" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.6rem;">
          Valider mon compte
        </button>
      </form>

    <?php endif; ?>
  </section>
</main>

</body>
</html>
