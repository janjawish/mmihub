<?php
// ============================================================================
// config.php - MMI HUB
// ============================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

require_once __DIR__ . '/vendor/autoload.php';

/** Return an environment variable, with an optional non-sensitive default. */
function app_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

// ============================================================================
// ⚙️ Connexion BDD
// ============================================================================
$host = app_env('DB_HOST', 'localhost');
$db   = app_env('DB_NAME', 'mmihub');
$user = app_env('DB_USER', 'root');
$pass = app_env('DB_PASSWORD', '');

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// ============================================================================
// ✉️ Helper PHPMailer commun
// ============================================================================
function createBaseMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $smtpUsername = app_env('SMTP_USERNAME');
    $smtpPassword = app_env('SMTP_PASSWORD');
    if (!$smtpUsername || !$smtpPassword) {
        throw new RuntimeException('La configuration SMTP est absente. Renseignez les variables SMTP_USERNAME et SMTP_PASSWORD.');
    }

    // 🔐 Configuration SMTP (variables d'environnement uniquement)
    $mail->isSMTP();
    $mail->Host       = app_env('SMTP_HOST', 'ssl0.ovh.net');
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) app_env('SMTP_PORT', '587');
    $mail->CharSet    = 'UTF-8';

    // Expéditeur
    $mail->setFrom(app_env('SMTP_FROM_EMAIL', $smtpUsername), 'MMI HUB');

    return $mail;
}

// URL RAW du logo MMI
const MMI_LOGO_URL = 'https://raw.githubusercontent.com/janjawish/logo_banniere/refs/heads/main/logo_mmi.png';

// ============================================================================
// 📧 Mail de vérification de compte
// ============================================================================
function sendVerificationEmail(string $email, string $code): bool
{
    try {
        $mail = createBaseMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Ton code de vérification – MMI HUB';

        $logoUrl = MMI_LOGO_URL;

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Code de vérification – MMI HUB</title>

  <!-- Fonts MMI HUB -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg,#6b4bff,#ff6ec7);
      font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .outer {
      padding: 24px 0;
    }
    .card {
      width: 100%;
      max-width: 640px;
      border-radius: 18px;
      background: #fff7d9;
      border: 2px solid #f2c94c;
      box-shadow: 0 16px 32px rgba(0,0,0,0.25);
      overflow: hidden;
    }
    .header {
      padding: 16px 22px;
      background: linear-gradient(135deg,#e62055,#5e4293);
      color: #fef6ba;
    }
    .logo-wrap {
      display: inline-flex;
      align-items: center;
      gap: 12px;
    }
    .logo-circle {
      display:inline-block;
      width: 44px;
      height: 44px;
      border-radius: 18px;
      background:#fef6ba;
      text-align:center;
    }
    .logo-circle img {
      display:block;
      margin:6px auto;
      border-radius:12px;
    }
    .brand-title {
      display:flex;
      flex-direction:column;
      line-height:1.2;
    }
    .brand-title span:first-child {
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.08em;
    }
    .brand-sub {
      font-size: 12px;
      opacity: 0.9;
    }
    .body {
      padding: 22px 24px 10px;
      color: #444;
      font-size: 14px;
    }
    .body h1 {
      margin: 0 0 8px;
      font-family: 'Anton', 'Space Grotesk', system-ui, sans-serif;
      font-size: 22px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #e62055;
    }
    .body p {
      margin: 0 0 8px;
      line-height: 1.5;
    }
    .code-block-wrap {
      padding: 8px 24px 20px;
    }
    .code-block {
      border-radius: 28px;
      background: linear-gradient(135deg,#f9d749,#e67121);
      border: 2px solid #ffe98a;
      text-align: center;
      padding: 18px 12px 20px;
    }
    .code-label {
      font-size: 12px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #6b4b00;
      margin-bottom: 6px;
    }
    .code-value {
      font-family: 'Anton', 'Space Grotesk', system-ui, sans-serif;
      font-size: 34px;
      letter-spacing: 0.24em;
      color: #fffdf7;
    }
    .footer-text {
      padding: 0 24px 20px;
      font-size: 13px;
      color: #666;
      line-height: 1.6;
    }
    .minor {
      font-size: 11px;
      color: #777;
      padding: 12px 24px 18px;
      border-top: 1px solid rgba(0,0,0,0.06);
    }
    .minor span {
      font-weight: 600;
    }
  </style>
</head>
<body>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="outer">
    <tr>
      <td align="center">
        <table role="presentation" cellspacing="0" cellpadding="0" class="card">
          <!-- Header -->
          <tr>
            <td class="header">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="left">
                    <span class="logo-wrap">
                      <span class="logo-circle">
                        <img
                          src="{$logoUrl}"
                          alt="MMI HUB"
                          width="32"
                          height="32"
                        >
                      </span>
                      <span class="brand-title">
                        <span>MMI HUB</span>
                        <span class="brand-sub">Vérification du compte</span>
                      </span>
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Intro -->
          <tr>
            <td class="body">
              <p>Salut 👋</p>
              <p>
                Merci de t'être inscrit sur <strong>MMI HUB</strong>.<br>
                Voici ton code pour activer ton compte&nbsp;:
              </p>
            </td>
          </tr>

          <!-- Code -->
          <tr>
            <td class="code-block-wrap">
              <div class="code-block">
                <div class="code-label">Code de vérification</div>
                <div class="code-value">{$code}</div>
              </div>
            </td>
          </tr>

          <!-- Texte bas -->
          <tr>
            <td class="footer-text">
              <p>
                Rends-toi sur la page de vérification de <strong>MMI HUB</strong>
                et entre ce code pour finaliser l'activation de ton compte.
              </p>
              <p style="margin-top:6px;">
                Ce code est personnel, ne le partage pas avec d'autres personnes.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="minor">
              MMI HUB – Projet étudiant du BUT MMI.
              Ce mail a été envoyé automatiquement, merci de ne pas y répondre.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $mail->Body = $htmlBody;
        $mail->AltBody = "Ton code de vérification MMI HUB est : {$code}";

        return $mail->send();
    } catch (Exception $e) {
        error_log('Erreur sendVerificationEmail: ' . $e->getMessage());
        return false;
    }
}


// ============================================================================
// 👤 Slug public pour les profils
// ============================================================================
/**
 * Génère ou récupère un slug public pour un utilisateur.
 *  - si un slug existe déjà -> on le renvoie
 *  - sinon -> on en génère un random et on le stocke dans profiles.public_slug
 */
function ensure_public_slug(PDO $pdo, int $userId): string
{
    // On regarde s'il y a déjà un profil + un slug
    $stmt = $pdo->prepare("SELECT id, public_slug FROM profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['public_slug'])) {
        return $row['public_slug'];
    }

    // S'il n'y a pas encore de ligne dans profiles, on en crée une minimaliste
    if (!$row) {
        $ins = $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)");
        $ins->execute([$userId]);
    }

    // On génère un slug random unique (16 caractères hexa)
    do {
        $slug = bin2hex(random_bytes(8)); // ex: "3fa9c7e2a1b4d8f0"

        $check = $pdo->prepare("SELECT id FROM profiles WHERE public_slug = ?");
        $check->execute([$slug]);
        $exists = $check->fetch(PDO::FETCH_ASSOC);
    } while ($exists);

    // On enregistre ce slug pour ce user
    $upd = $pdo->prepare("UPDATE profiles SET public_slug = ? WHERE user_id = ?");
    $upd->execute([$slug, $userId]);

    return $slug;
}

// ============================================================================
// 🔐 Mail A2F / Connexion inhabituelle
// ============================================================================
function sendTwofaAlertEmail(string $email, string $code): bool
{
    try {
        $mail = createBaseMailer();
        $mail->addAddress($email);

        $subject = 'Connexion inhabituelle sur ton compte MMI HUB';
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $logoUrl = MMI_LOGO_URL;

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Connexion inhabituelle – MMI HUB</title>

  <!-- Fonts MMI HUB -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg,#2030a0,#8b3cf4);
      font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .outer {
      padding: 24px 0;
    }
    .card {
      width: 100%;
      max-width: 640px;
      border-radius: 18px;
      background: #183a9e;
      border: 2px solid #f2c94c;
      box-shadow: 0 18px 34px rgba(0,0,0,0.35);
      overflow: hidden;
      color: #fef6ba;
    }
    .header {
      padding: 16px 22px;
      background: linear-gradient(135deg,#e62055,#5e4293);
    }
    .logo-wrap {
      display: inline-flex;
      align-items: center;
      gap: 12px;
    }
    .logo-circle {
      display:inline-block;
      width: 44px;
      height: 44px;
      border-radius: 18px;
      background:#fef6ba;
      text-align:center;
    }
    .logo-circle img {
      display:block;
      margin:6px auto;
      border-radius:12px;
    }
    .brand-title {
      display:flex;
      flex-direction:column;
      line-height:1.2;
    }
    .brand-title span:first-child {
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.08em;
    }
    .brand-sub {
      font-size: 12px;
      opacity: 0.9;
    }
    .body {
      padding: 22px 24px 10px;
      color: #fef6ba;
      font-size: 14px;
    }
    .body h1 {
      margin: 0 0 8px;
      font-family: 'Anton', 'Space Grotesk', system-ui, sans-serif;
      font-size: 22px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #ffe98a;
    }
    .body p {
      margin: 0 0 8px;
      line-height: 1.5;
    }
    .code-block-wrap {
      padding: 8px 24px 20px;
    }
    .code-block {
      border-radius: 28px;
      background: linear-gradient(135deg,#f9d749,#e67121);
      border: 2px solid #ffe98a;
      text-align: center;
      padding: 18px 12px 20px;
      color:#fffdf7;
    }
    .code-label {
      font-size: 12px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #5d3400;
      margin-bottom: 6px;
    }
    .code-value {
      font-family: 'Anton', 'Space Grotesk', system-ui, sans-serif;
      font-size: 34px;
      letter-spacing: 0.24em;
    }
    .footer-text {
      padding: 0 24px 20px;
      font-size: 13px;
      color: #fef6ba;
      line-height: 1.6;
    }
    .minor {
      font-size: 11px;
      color: #f3e8ff;
      padding: 12px 24px 18px;
      border-top: 1px solid rgba(255,255,255,0.15);
      background: rgba(0,0,0,0.08);
    }
    .minor span {
      font-weight: 600;
    }
  </style>
</head>
<body>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="outer">
    <tr>
      <td align="center">
        <table role="presentation" cellspacing="0" cellpadding="0" class="card">
          <!-- Header -->
          <tr>
            <td class="header">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="left">
                    <span class="logo-wrap">
                      <span class="logo-circle">
                        <img
                          src="{$logoUrl}"
                          alt="MMI HUB"
                          width="32"
                          height="32"
                        >
                      </span>
                      <span class="brand-title">
                        <span>MMI HUB</span>
                        <span class="brand-sub">Connexion inhabituelle</span>
                      </span>
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Intro -->
          <tr>
            <td class="body">
              <p>Salut 👋</p>
              <h1>Tentative de connexion détectée</h1>
              <p>
                Nous avons détecté une tentative de connexion à ton compte <strong>MMI HUB</strong>
                depuis un appareil ou une adresse IP inhabituelle.
              </p>
              <p>
                Si c'est bien toi, utilise le code ci-dessous pour confirmer la connexion :
              </p>
            </td>
          </tr>

          <!-- Code -->
          <tr>
            <td class="code-block-wrap">
              <div class="code-block">
                <div class="code-label">Code de confirmation</div>
                <div class="code-value">{$code}</div>
              </div>
            </td>
          </tr>

          <!-- Texte bas -->
          <tr>
            <td class="footer-text">
              <p>
                Saisis ce code sur la page de validation pour continuer ta connexion.
              </p>
              <p style="margin-top:6px;">
                <strong>❗ Si tu n'es pas à l'origine de cette tentative</strong>,
                connecte-toi à ton compte dès que possible et
                change ton mot de passe dans les paramètres.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="minor">
              MMI HUB – Projet étudiant du BUT MMI.
              Ce mail a été envoyé automatiquement, merci de ne pas y répondre.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $mail->Body = $htmlBody;
        $mail->AltBody = "Tentative de connexion inhabituelle sur ton compte MMI HUB. Code de confirmation : {$code}";

        return $mail->send();
    } catch (Exception $e) {
        error_log('Erreur sendTwofaAlertEmail: ' . $e->getMessage());
        return false;
    }
}
