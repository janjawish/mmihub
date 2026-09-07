<?php

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mentions légales – MMIHub</title>
    <meta name="description" content="Mentions légales du projet étudiant MMIHub.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mmihub/style.css">
</head>
<body class="page-legal">
<header class="site-header">
  <div class="header-inner">
    <a href="accueil#top" class="logo">
      <span class="logo-mark">MMI</span>
      <span class="logo-word">HUB</span>
    </a>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- BOUTON BURGER -->
    <button class="nav-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
    </button>

    <!-- MENU -->
    <nav class="main-nav">
      <a href="mmi-notes" class="nav-link nav-notes">MMI Notes</a>
      <a href="mmi-abs" class="nav-link nav-abs">MMI ABS</a>
      <a href="mmi-chat" class="nav-link nav-chat">MMI Chat</a>
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

<main class="legal-container">
    <section class="legal-header">
        <h1>Mentions légales</h1>
        <p>Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>
    </section>

    <section>
        <h2>1. Éditeur du site</h2>
        <p>
            Le site <strong>MMIHub</strong> est un projet étudiant réalisé dans le cadre d’un projet personnel.<br>
            Il s’agit d’un projet à <strong>but non lucratif</strong>.
        </p>
        <p>
            Éditeur du site : <strong>Jan Jawish</strong><br>
            Responsable de la publication : <strong>Jan Jawish</strong><br>
            Contact : <a href="mailto:jawishjan@gmail.com">jawishjan@gmail.com</a><br>
        </p>
    </section>

    <section>
        <h2>2. Hébergement</h2>
        <p>
            Le site est hébergé par : IONOS<br>

        </p>
    </section>

    <section>
        <h2>3. Objet du site</h2>
        <p>
            MMIHub est une plateforme destinée aux étudiant·e·s, permettant notamment
            l’accès à des informations, outils et fonctionnalités liés à la formation.
            Ce site est fourni dans un cadre pédagogique et expérimental, sans vocation commerciale.
        </p>
    </section>

    <section>
        <h2>4. Propriété intellectuelle</h2>
        <p>
            Sauf mention contraire, l’ensemble des éléments présents sur le site
            (textes, visuels, logo, interface, code, etc.) est la propriété de Jan Jawish et
            ne peut être reproduit, modifié ou réutilisé sans autorisation préalable.
        </p>
        <p>
            Les contenus éventuellement ajoutés par les utilisateur·rice·s (notes, messages, profil, etc.)
            restent de leur responsabilité.
        </p>
    </section>

    <section>
        <h2>5. Responsabilité</h2>
        <p>
            MMIHub est un projet étudiant fourni “en l’état”. Malgré le soin apporté à la qualité
            et à la mise à jour du site, aucune garantie de disponibilité permanente,
            d’absence d’erreurs ou de conformité à un usage particulier n’est donnée.
        </p>
        <p>
            L’éditeur ne pourra être tenu responsable des dommages directs ou indirects
            liés à l’utilisation du site ou à l’impossibilité d’y accéder.
        </p>
    </section>

    <section>
        <h2>6. Liens externes</h2>
        <p>
            Le site peut contenir des liens vers des sites tiers. MMIHub n’exerce aucun contrôle
            sur ces sites et ne peut être tenu responsable de leurs contenus ou de leur politique
            de confidentialité.
        </p>
    </section>

    <section>
        <h2>7. Données personnelles</h2>
        <p>
            Dans le cadre de l’utilisation du site, certaines données personnelles peuvent être collectées,
            principalement lors de la création et de l’utilisation d’un compte utilisateur.
            Ces traitements sont détaillés dans la <a href="/mmihub/politique.php">Politique de confidentialité</a>.
        </p>
        <p>
            <strong>À partir du moment où vous créez un compte sur MMIHub, vous reconnaissez avoir pris
            connaissance et accepter les présentes mentions légales ainsi que la <a href="/mmihub/politique.php">Politique de confidentialité.</a></strong>
        </p>
    </section>

    <section>
        <h2>8. Contact</h2>
        <p>
            Pour toute question relative au site, aux mentions légales ou aux données personnelles,
            vous pouvez contacter : <a href="mailto:jawishjan@gmail.com">jawishjan@gmail.com</a>.
        </p>
    </section>
</main>

<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> MMI HUB · Projet étudiant de Jan Jawish – BUT MMI.</p>
    <p class="footer-sub">Respect du RGPD · Tes données servent uniquement à ton suivi personnel.</p>
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
