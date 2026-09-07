<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/track_visit.php';

// Si l'utilisateur est déjà connecté, on le redirige vers le tableau de bord
if (!empty($_SESSION['user_id'])) {
    header('Location: /mmihub/accueil');
    exit;
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = "Merci de remplir tous les champs correctement.";
    } else {
        // Récupération de l'utilisateur par e-mail
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = "Adresse mail ou mot de passe incorrect.";
        } else {
            // IP + User-Agent actuels
            $currentIp = $_SERVER['REMOTE_ADDR']     ?? '';
            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // ============================================================
            // 1) Vérifier si l'appareil est déjà de confiance (cookie)
            // ============================================================
            $trustedDevice = false;

            if (!empty($_COOKIE['mh_trusted_device'])) {
                $rawToken  = $_COOKIE['mh_trusted_device'];
                $tokenHash = hash('sha256', $rawToken);

                $stmtDev = $pdo->prepare("
                    SELECT id
                    FROM user_trusted_devices
                    WHERE user_id = :uid
                      AND device_token_hash = :hash
                    LIMIT 1
                ");
                $stmtDev->execute([
                    ':uid'  => $user['id'],
                    ':hash' => $tokenHash,
                ]);

                if ($stmtDev->fetchColumn()) {
                    $trustedDevice = true;

                    // mise à jour de last_used_at
                    $updDev = $pdo->prepare("
                        UPDATE user_trusted_devices
                        SET last_used_at = NOW()
                        WHERE user_id = :uid
                          AND device_token_hash = :hash
                    ");
                    $updDev->execute([
                        ':uid'  => $user['id'],
                        ':hash' => $tokenHash,
                    ]);
                }
            }

            // ============================================================
            // 2) Vérifier la logique de 2FA basée sur l'IP
            // ============================================================
            $needTwofa = false;

            if ($user['twofa_method'] === 'email') {
                // 1) Combien d'IP déjà connues pour cet utilisateur ?
                $stmtCount = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM user_trusted_ips
                    WHERE user_id = :uid
                ");
                $stmtCount->execute([':uid' => $user['id']]);
                $trustedCount = (int) $stmtCount->fetchColumn();

                // 2) Cette IP est-elle déjà dans la liste des IP de confiance ?
                $isTrustedIp = false;
                if ($currentIp !== '') {
                    $stmtTrusted = $pdo->prepare("
                        SELECT 1
                        FROM user_trusted_ips
                        WHERE user_id = :uid AND ip = :ip
                        LIMIT 1
                    ");
                    $stmtTrusted->execute([
                        ':uid' => $user['id'],
                        ':ip'  => $currentIp,
                    ]);
                    $isTrustedIp = (bool) $stmtTrusted->fetchColumn();
                }

                // 3) Faut-il déclencher la 2FA ?
                // - 2FA e-mail activée
                // - au moins 1 IP déjà connue
                // - IP actuelle nouvelle
                // - appareil PAS déjà de confiance
                if (
                    !$trustedDevice &&
                    $trustedCount > 0 &&
                    $currentIp !== '' &&
                    !$isTrustedIp
                ) {
                    $needTwofa = true;
                }
            }

            // ============================================================
            // 3) CAS : 2FA REQUISE -> on envoie un code et on redirige vers a2f.php
            // ============================================================
            if ($needTwofa) {
                // Générer un code 2FA à 6 chiffres
                $code = (string) random_int(100000, 999999);

                // Stocker le code et son expiration (10 minutes)
                $expiresAt = date('Y-m-d H:i:s', time() + 600);

                $update = $pdo->prepare("
                    UPDATE users
                    SET twofa_temp_code = :code,
                        twofa_temp_expires_at = :exp
                    WHERE id = :id
                ");
                $update->execute([
                    ':code' => $code,
                    ':exp'  => $expiresAt,
                    ':id'   => $user['id'],
                ]);

                // Envoi du mail 2FA / connexion inhabituelle
                sendTwofaAlertEmail($user['email'], $code);

                // On met l'utilisateur et l'IP en \"pending\" pour la 2FA
                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_ip']      = $currentIp;

                // Redirection à appliquer APRÈS la 2FA
                if (!empty($_GET['redirect'])) {
                    $_SESSION['pending_redirect'] = $_GET['redirect'];
                } else {
                    $_SESSION['pending_redirect'] = '/mmihub/accueil';
                }

                // Redirection vers la page a2f.php (même dossier)
header('Location: /mmihub/register/a2f.php');
exit;


            }

            // ============================================================
            // 4) CAS : PAS de 2FA -> connexion directe
            // ============================================================
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['first_name'];
            $_SESSION['user_email'] = $user['email'];

            // Ajoute / met à jour l'IP dans la liste des IP de confiance
            if ($currentIp !== '') {
                $upIp = $pdo->prepare("
                    INSERT INTO user_trusted_ips (user_id, ip)
                    VALUES (:uid, :ip)
                    ON DUPLICATE KEY UPDATE last_used_at = NOW()
                ");
                $upIp->execute([
                    ':uid' => $user['id'],
                    ':ip'  => $currentIp,
                ]);
            }

            // Mise à jour des infos de dernière connexion
            $updateLogin = $pdo->prepare("
                UPDATE users
                SET last_login_ip = :ip,
                    last_login_ua = :ua,
                    last_login_at = NOW()
                WHERE id = :id
            ");
            $updateLogin->execute([
                ':ip' => $currentIp,
                ':ua' => $currentUA,
                ':id' => $user['id'],
            ]);

            // Redirection : soit paramètre ?redirect=..., soit accueil
            $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : '/mmihub/accueil';
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
  <title>Connexion – MMI HUB</title>
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
  <div class="auth-grid">
    <section class="auth-intro">
      <h1>Connexion</h1>
      <p>
        Retrouve ton tableau de bord MMI Notes, MMI ABS et les services
        associés à ton compte.
      </p>
    </section>

    <section class="auth-card">
      <h2>Se connecter</h2>

      <?php if ($errors): ?>
        <div class="auth-error">
          <?php foreach ($errors as $err): ?>
            <p><?= htmlspecialchars($err) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="auth-form" method="post" novalidate>
        <div>
          <label class="auth-label" for="email">E-mail</label>
          <input class="auth-input" type="email" id="email" name="email"
                 value="<?= htmlspecialchars($email ?? '') ?>" required>
        </div>

        <div>
          <label class="auth-label" for="password">Mot de passe</label>
          <input class="auth-input" type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.6rem;">
          Se connecter
        </button>

        <p class="auth-footer">
          Pas encore de compte ? <a href="creation-de-compte" class="btn-link">Créer un compte</a>
        </p>
      </form>
    </section>
  </div>
</main>
</body>
</html>
