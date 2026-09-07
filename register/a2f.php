<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/track_visit.php';

$errors = [];

if (empty($_SESSION['pending_user_id']) || !array_key_exists('pending_ip', $_SESSION)) {
    // pas de tentative 2FA en cours
    header('Location: login.php');
    exit;
}

$userId    = (int) $_SESSION['pending_user_id'];
$pendingIp = $_SESSION['pending_ip'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code        = trim($_POST['twofa_code'] ?? '');
    $trustDevice = !empty($_POST['trust_device']);

    if ($code === '') {
        $errors[] = "Merci d'entrer le code reçu par e-mail.";
    } else {
        $now = date('Y-m-d H:i:s');

        // Vérification du code 2FA
        $stmt = $pdo->prepare("
            SELECT id, first_name, email
            FROM users
            WHERE id = :id
              AND BINARY twofa_temp_code = BINARY :code
              AND (twofa_temp_expires_at IS NOT NULL AND twofa_temp_expires_at >= :now)
            LIMIT 1
        ");
        $stmt->execute([
            ':id'   => $userId,
            ':code' => $code,
            ':now'  => $now,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = "Code invalide ou expiré. Réessaie.";
        } else {
            // 1) IP de confiance (comme avant)
            if (!empty($pendingIp)) {
                $upIp = $pdo->prepare("
                    INSERT INTO user_trusted_ips (user_id, ip)
                    VALUES (:uid, :ip)
                    ON DUPLICATE KEY UPDATE last_used_at = NOW()
                ");
                $upIp->execute([
                    ':uid' => $userId,
                    ':ip'  => $pendingIp,
                ]);
            }

            // 2) Enregistrer l'appareil comme "de confiance" si demandé
            if ($trustDevice) {
                $rawToken  = bin2hex(random_bytes(32));  // token pour le cookie
                $tokenHash = hash('sha256', $rawToken);  // ce qu'on stocke en BDD

                $insDev = $pdo->prepare("
                    INSERT INTO user_trusted_devices (user_id, device_token_hash, user_agent)
                    VALUES (:uid, :hash, :ua)
                ");
                $insDev->execute([
                    ':uid'  => $userId,
                    ':hash' => $tokenHash,
                    ':ua'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]);

                // Cookie valable 1 an
                setcookie(
                    'mh_trusted_device',
                    $rawToken,
                    [
                        'expires'  => time() + 60 * 60 * 24 * 365,
                        'path'     => '/',
                        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );
            }

            // 3) Nettoyage du code 2FA + infos de dernière connexion
            $update = $pdo->prepare("
                UPDATE users
                SET twofa_temp_code = NULL,
                    twofa_temp_expires_at = NULL,
                    last_login_ip = :ip,
                    last_login_ua = :ua,
                    last_login_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                ':ip' => $pendingIp,
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':id' => $userId,
            ]);

            // 4) Connexion de l'utilisateur
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['first_name'];
            $_SESSION['user_email'] = $user['email'];

            // 5) Nettoyage de la session 2FA
            unset($_SESSION['pending_user_id'], $_SESSION['pending_ip']);

            // Redirection finale
            $redirect = !empty($_SESSION['pending_redirect'])
                ? $_SESSION['pending_redirect']
                : '/mmihub/accueil';

            unset($_SESSION['pending_redirect']);

            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="/mmihub/favicon.ico">
  <meta charset="UTF-8">
  <title>Validation de connexion – MMI HUB</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/mmihub/style.css">
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
    <h2>Confirmer la connexion</h2>
    <p class="auth-intro">
      Nous avons détecté une connexion inhabituelle à ton compte.
      Un code de confirmation t’a été envoyé par e-mail.
    </p>

    <?php if (!empty($errors)): ?>
      <div class="auth-errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

<form method="post" class="auth-form">
  <div>
    <label class="auth-label" for="twofa_code">Code reçu par e-mail</label>
    <input
      class="auth-input"
      type="text"
      id="twofa_code"
      name="twofa_code"
      inputmode="numeric"
      pattern="[0-9]*"
      maxlength="6"
      required
    >
  </div>

  <div class="auth-remember-device" style="margin-top:0.6rem;">
    <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
      <input type="checkbox" name="trust_device" value="1">
      <span>Ne plus me demander de code sur cet appareil</span>
    </label>
  </div>

  <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.6rem;">
    Valider la connexion
  </button>
</form>

  </section>
</main>
</body>
</html>
