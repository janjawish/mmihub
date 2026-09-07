<?php
// public_profile.php

require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/semester.php';
require_once __DIR__ . '/includes/track_visit.php';

// 1) Récupérer slug ou user_id
$slug      = trim($_GET['u'] ?? '');
$userIdGet = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$profileRow = null;

if ($slug !== '') {
    // Priorité au slug public
    $stmt = $pdo->prepare("
        SELECT u.*, p.*
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE p.public_slug = :slug
        LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);
    $profileRow = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($userIdGet > 0) {
    // Fallback par id (utile tant que tous les slugs ne sont pas en place)
    $stmt = $pdo->prepare("
        SELECT u.*, p.*
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userIdGet]);
    $profileRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si rien trouvé → page 404 propre
if (!$profileRow) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Profil introuvable</title>
        <link rel="stylesheet" href="/mmihub/style.css">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body class="notes-body">
    <header class="site-header">
      <div class="header-inner">
        <a href="accueil#top" class="logo">
          <span class="logo-mark">MMI</span>
          <span class="logo-word">HUB</span>
        </a>
      </div>
    </header>

    <main class="auth-page">
      <div class="auth-card">
        <h1>Profil introuvable</h1>
        <p>Ce profil n’existe pas ou n’est plus disponible.</p>
        <a href="accueil#top" class="btn btn-primary" style="margin-top:1rem;">Retour à l’accueil</a>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

// 2) Normaliser quelques champs (si LEFT JOIN et pas encore d’entrée dans profiles)
$profileRow['is_anonymous']  = (int)($profileRow['is_anonymous']  ?? 0);
$profileRow['show_year']     = (int)($profileRow['show_year']     ?? 1);
$profileRow['show_parcours'] = (int)($profileRow['show_parcours'] ?? 1);
$profileRow['show_photo']    = (int)($profileRow['show_photo']    ?? 1);

// 3) Infos d’affichage
$isAnonymous = $profileRow['is_anonymous'] === 1;

if ($isAnonymous) {
    $displayName = 'Profil anonyme';
} else {
    $displayName = trim(($profileRow['first_name'] ?? '') . ' ' . ($profileRow['last_name'] ?? ''));
    if ($displayName === '') {
        $displayName = 'Étudiant·e MMI';
    }
}

// Avatar
$avatarUrl = null;
if (!$isAnonymous && $profileRow['show_photo'] && !empty($profileRow['profile_image'])) {
    $avatarUrl = $profileRow['profile_image'];
}

// Année / parcours
$butYear  = (int)($profileRow['but_year'] ?? 0);
$parcours = $profileRow['parcours'] ?? 'none';

if ($parcours === 'crea') {
    $parcoursLabel = 'Création graphique';
} elseif ($parcours === 'dev') {
    $parcoursLabel = 'Développement Web';
} else {
    $parcoursLabel = 'Tronc commun';
}

// Type de formation (BUT 3)
$formationType = $profileRow['formation_type'] ?? 'none';
if ($formationType === 'fi') {
    $formationLabel = 'Formation initiale';
} elseif ($formationType === 'fa') {
    $formationLabel = 'Alternance (FA)';
} else {
    $formationLabel = '—';
}

// Semestre actuel (même logique que notes.php / profile.php)
$currentSemesterNumber = getCurrentSemesterNumberForUser($profileRow);
$currentSemesterLabel  = $currentSemesterNumber ? 'S' . $currentSemesterNumber : '—';

// Profil public (headline / about / skills)
$headline = $profileRow['headline'] ?? '';
$about    = $profileRow['about']    ?? '';
$skills   = $profileRow['skills']   ?? '';

// Titre de la page
$pageTitle = $isAnonymous ? 'Profil anonyme' : 'Profil de ' . htmlspecialchars($displayName) . ' – MMI HUB';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mmihub/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
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
      <a href="/mmihub/mmi-notes" class="nav-link nav-notes" >MMI Notes</a>
      <a href="/mmihub/mmi-abs" class="nav-link nav-abs" >MMI ABS</a>
      <a href="/mmihub/mmi-chat" class="nav-link nav-chat" >MMI Chat</a>
      <a href="/mmihub/mmi-edt" class="nav-link nav-services" >MMI EDT</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="/mmihub/mon-profil" class="nav-cta">Mon profil</a>
        <a href="disconnect.php" class="nav-cta">Se déconnecter</a>
      <?php else: ?>
        <a href="/mmihub/connexion" class="nav-cta">Connexion</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="auth-page">
  <div class="profile-layout">

    <!-- COLONNE GAUCHE : résumé -->
    <section class="auth-intro profile-side">
      <p class="notes-kicker">Profil public</p>
      <h1><?= htmlspecialchars($displayName) ?></h1>

      <?php if ($isAnonymous): ?>
        <p>
          Ce compte a choisi de rester <strong>anonyme</strong> dans MMI HUB.
        </p>
      <?php else: ?>
        <p>
          Voici le <strong>profil public</strong> de cet·te étudiant·e MMI.
        </p>
      <?php endif; ?>

      <div class="profile-side-grid">
        <div class="profile-pill">
          <p class="profile-pill-label">Année</p>
          <p class="profile-pill-value">
            <?= $butYear ? 'BUT ' . htmlspecialchars($butYear) : '—' ?>
          </p>
        </div>

        <div class="profile-pill">
          <p class="profile-pill-label">Parcours</p>
          <p class="profile-pill-value">
            <?php
              if ($butYear === 1 || !$profileRow['show_parcours'] || $isAnonymous) {
                  echo '—';
              } else {
                  echo htmlspecialchars($parcoursLabel);
              }
            ?>
          </p>
        </div>

        <div class="profile-pill">
          <p class="profile-pill-label">Semestre actuel</p>
          <p class="profile-pill-value">
            <?= htmlspecialchars($currentSemesterLabel) ?>
          </p>
        </div>

        <div class="profile-pill">
          <p class="profile-pill-label">Type de formation</p>
          <p class="profile-pill-value">
            <?= ($butYear === 3 && !$isAnonymous) ? htmlspecialchars($formationLabel) : '—' ?>
          </p>
        </div>
      </div>
    </section>

    <!-- COLONNE DROITE : contenu du profil public -->
    <section class="profile-main">
      <div class="auth-card profile-section-card" style="margin-bottom:1rem;display:flex;gap:1.5rem;align-items:flex-start;">
        <!-- Avatar -->
        <div>
          <?php if ($avatarUrl && !$isAnonymous): ?>
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" class="chat-avatar" style="width:96px;height:96px;border-radius:999px;object-fit:cover;">
          <?php else: ?>
            <div class="chat-avatar chat-avatar-placeholder" style="width:96px;height:96px;font-size:2rem;">
              <?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'))) ?>
            </div>
          <?php endif; ?>
        </div>

        <div style="flex:1;">
          <?php if ($isAnonymous): ?>
            <h2>Profil anonyme</h2>
            <p style="opacity:0.8;margin-top:0.5rem;">
              L’utilisateur·rice a choisi de ne pas afficher ses informations personnelles
              sur son profil public.
            </p>
          <?php else: ?>
            <h2>À propos</h2>

            <?php if (!empty($headline)): ?>
              <p style="font-weight:600;margin-top:0.4rem;">
                « <?= htmlspecialchars($headline) ?> »
              </p>
            <?php endif; ?>

            <?php if (!empty($about)): ?>
              <p style="margin-top:0.6rem;white-space:pre-line;">
                <?= nl2br(htmlspecialchars($about)) ?>
              </p>
            <?php else: ?>
              <p style="margin-top:0.6rem;opacity:0.8;">
                Cette personne n’a pas encore rempli la section “À propos”.
              </p>
            <?php endif; ?>

            <?php if (!empty($skills)): ?>
              <div style="margin-top:0.8rem;">
                <p class="auth-label" style="margin-bottom:0.3rem;">Domaines forts</p>
                <p><?= htmlspecialchars($skills) ?></p>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$isAnonymous): ?>
      <div class="auth-card profile-section-card">
        <h3>Infos MMI</h3>
        <ul style="list-style:disc;padding-left:1.3rem;margin-top:0.6rem;display:flex;flex-direction:column;gap:0.25rem;">
          <li>Année : <?= $butYear ? 'BUT ' . htmlspecialchars($butYear) : '—' ?></li>
          <li>Semestre actuel : <?= htmlspecialchars($currentSemesterLabel) ?></li>
          <li>Parcours :
            <?php
              if ($butYear === 1 || !$profileRow['show_parcours']) {
                  echo '—';
              } else {
                  echo htmlspecialchars($parcoursLabel);
              }
            ?>
          </li>
          <li>Type de formation :
            <?= ($butYear === 3) ? htmlspecialchars($formationLabel) : '—' ?>
          </li>
        </ul>
      </div>
      <?php endif; ?>

    </section>

  </div>
</main>
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
