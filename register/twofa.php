<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/track_visit.php';

$errors = [];

if (empty($_SESSION['pending_user_id'])) {
    // pas de tentative 2FA en cours
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['pending_user_id'];
$pendingIp = $_SESSION['pending_ip'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['twofa_code'] ?? '');

    if ($code === '') {
        $errors[] = "Merci d'entrer le code reçu par e-mail.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = "Session invalide. Merci de te reconnecter.";
        } else {
if (
    !empty($user['twofa_temp_code']) &&
    !empty($user['twofa_temp_expires_at']) &&
    $user['twofa_temp_code'] === $code &&
    strtotime($user['twofa_temp_expires_at']) >= time()
) {
    // IP à utiliser : celle mémorisée lors du login, sinon IP actuelle
    $finalIp = $pendingIp !== '' ? $pendingIp : ($_SERVER['REMOTE_ADDR'] ?? '');
    $finalUa = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 1) On ajoute l'IP validée dans la table des IP de confiance
    if ($finalIp !== '') {
        $stmtIp = $pdo->prepare("
            INSERT INTO user_trusted_ips (user_id, ip)
            VALUES (:uid, :ip)
            ON DUPLICATE KEY UPDATE last_used_at = NOW()
        ");
        $stmtIp->execute([
            ':uid' => $userId,
            ':ip'  => $finalIp,
        ]);
    }

    // 2) On nettoie les champs 2FA temporaires et on met à jour last_login_* 
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
        ':ip' => $finalIp,
        ':ua' => $finalUa,
        ':id' => $userId,
    ]);

    // 3) On nettoie la session 2FA
    unset($_SESSION['pending_user_id'], $_SESSION['pending_ip']);

    // 4) On connecte vraiment l'utilisateur
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['first_name'];
    $_SESSION['user_email'] = $user['email'];

    header('Location: ../profile.php');
    exit;
}
 else {
                $errors[] = "Code invalide ou expiré. Réessaie.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="../favicon.ico">
    <meta charset="UTF-8">
    <title>Vérification 2FA</title>
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
    <h2>Vérification 2FA</h2>

    <?php if ($errors): ?>
      <div class="auth-error">
        <?php foreach ($errors as $err): ?>
          <p><?= htmlspecialchars($err) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="auth-help">
      Nous avons détecté une connexion inhabituelle. Un code 2FA t’a été envoyé par e-mail.
    </p>

    <form method="post" class="auth-form">
      <div>
        <label class="auth-label" for="twofa_code">Code 2FA</label>
        <input class="auth-input" type="text" id="twofa_code" name="twofa_code"
               inputmode="numeric" pattern="[0-9]*" maxlength="6" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.6rem;">
        Valider
      </button>
    </form>
  </section>
</main>
</body>
</html>
