<?php
require_once __DIR__ . '/register/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Politique de confidentialité – MMIHub</title>
    <meta name="description" content="Politique de confidentialité du projet étudiant MMIHub.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mmihub/style.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="page-legal">
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
      <a href="mmi-notes" class="nav-link nav-notes">MMI Notes</a>
      <a href="mmi-abs" class="nav-link nav-abs">MMI ABS</a>
      <a href="mmi-chat" class="nav-link nav-chat">MMI Chat</a>
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

<main class="legal-container">
    <section class="legal-header">
        <h1>Politique de confidentialité</h1>
        <p>Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>
    </section>

    <section>
        <h2>1. Contexte</h2>
        <p>
            Le site <strong>MMIHub</strong> est un projet étudiant à <strong>but non lucratif</strong>,
            réalisé dans un cadre personnel. Cette politique de confidentialité explique
            quelles données sont collectées, pourquoi et comment elles sont utilisées.
        </p>
        <p>
            À partir du moment où vous créez un compte sur MMIHub, vous acceptez la présente
            Politique de confidentialité ainsi que les <a href="/mmihub/mentions.php">Mentions légales</a>.
        </p>
    </section>

    <section>
        <h2>2. Absence de cookies de suivi</h2>
        <p>
            MMIHub <strong>n’utilise pas de cookies de suivi publicitaire</strong> ni de cookies
            à des fins de profilage marketing.
        </p>
        <p>
            Seuls des cookies strictement nécessaires au fonctionnement du site peuvent être utilisés,
            par exemple pour maintenir votre session lorsque vous êtes connecté·e.
        </p>
    </section>

    <section>
        <h2>3. Données collectées</h2>
        <p>
            Les données collectées sont limitées au strict nécessaire pour faire fonctionner le site
            et les fonctionnalités proposées.
        </p>

        <h3>3.1. Données de compte</h3>
        <ul>
            <li>Adresse e-mail</li>
            <li>Nom, prénom ou pseudonyme</li>
            <li>Mot de passe (stocké de manière hachée, jamais en clair)</li>
            <li>Éventuelles informations de profil que vous choisissez de renseigner
                (par exemple : promo, semestre, options, description, etc.).</li>
                <li>Notes et informations que vous renseignez dans les champs.</li>
                <li>Absences et informations que vous renseignez dans les champs.</li>
                <li>Vos informations sur votre profil publique.</li>
        </ul>

        <h3>3.2. Données techniques</h3>
        <ul>
            <li>Adresse IP (pour la sécurité et la gestion des connexions)</li>
            <li>Informations de navigateur / user-agent</li>
            <li>Données liées à la sécurité (journal des connexions, appareil de confiance, etc.).</li>
        </ul>

        <h3>3.3. Contenus utilisateur</h3>
        <ul>
            <li>Notes, messages, contenus ajoutés dans le cadre des fonctionnalités du site.</li>
        </ul>
    </section>

    <section>
        <h2>4. Finalités des traitements</h2>
        <p>Les données sont utilisées uniquement pour :</p>
        <ul>
            <li>Créer et gérer votre compte utilisateur</li>
            <li>Vous permettre d’accéder aux fonctionnalités du site (profil, notes, emploi du temps, etc.)</li>
            <li>Assurer la sécurité des comptes (connexion, 2FA, IP de confiance, appareil de confiance)</li>
            <li>Éventuellement, produire des statistiques anonymisées d’utilisation à des fins pédagogiques.</li>
        </ul>
        <p>
            Aucune donnée personnelle n’est utilisée à des fins commerciales ni publicitaires.
        </p>
    </section>

    <section>
        <h2>5. Base légale</h2>
        <p>
            Le traitement des données est fondé sur :
        </p>
        <ul>
            <li>Votre <strong>consentement</strong> lors de la création de compte et de l’utilisation du site ;</li>
            <li>L’<strong>intérêt légitime</strong> lié au bon fonctionnement et à la sécurisation du projet étudiant.</li>
        </ul>
    </section>

    <section>
        <h2>6. Durée de conservation</h2>
        <p>
            Les données sont conservées pendant la durée de vie du projet MMIHub et/ou tant que votre compte est actif.
            En cas de demande de suppression de compte, les données associées sont supprimées ou anonymisées,
            dans la mesure du possible et dans un délai raisonnable.
        </p>
    </section>

    <section>
        <h2>7. Partage des données</h2>
        <p>
            Les données collectées via MMIHub ne sont <strong>jamais revendues à des tiers</strong>.
        </p>
        <p>
            Elles ne sont partagées qu’éventuellement avec :
        </p>
        <ul>
            <li>L’hébergeur du site, pour les besoins strictement techniques (stockage, sécurité, maintenance).</li>
        </ul>
    </section>

    <section>
        <h2>8. Sécurité</h2>
        <p>
            Des mesures techniques raisonnables sont mises en œuvre pour protéger les données
            (mots de passe hachés, gestion de session, protection contre certains abus, etc.).
            Cependant, aucun système informatique n’est totalement infaillible.
        </p>
    </section>

    <section>
        <h2>9. Vos droits</h2>
        <p>
            Conformément à la réglementation applicable en matière de protection des données
            (notamment le RGPD), vous disposez des droits suivants :
        </p>
        <ul>
            <li>Droit d’accès à vos données</li>
            <li>Droit de rectification</li>
            <li>Droit à l’effacement (droit à l’oubli) dans certains cas</li>
            <li>Droit de limitation du traitement dans certains cas</li>
            <li>Droit d’opposition, lorsque cela est applicable</li>
        </ul>
        <p>
            Pour exercer ces droits, vous pouvez contacter :
            <a href="mailto:jawishjan@gmail.com">jawishjan@gmail.com</a>.
        </p>
    </section>

    <section>
        <h2>10. Acceptation de la politique de confidentialité</h2>
        <p>
            En créant un compte et en utilisant MMIHub, vous reconnaissez avoir lu,
            compris et accepté la présente Politique de confidentialité ainsi que
            les <a href="/mmihub/mentions.php">Mentions légales</a>.
        </p>
    </section>

    <section>
        <h2>11. Modifications</h2>
        <p>
            Cette politique peut être mise à jour pour refléter des évolutions du projet ou des exigences légales.
            En cas de modification importante, nous ferons au mieux pour l’indiquer clairement sur le site.
        </p>
    </section>
</main>

<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> MMI HUB · Projet étudiant de Jan Jawish – BUT MMI.</p>
    <p class="footer-sub">Respect du RGPD · Tes données servent uniquement à ton suivi personnel.</p>
    <a href="mentions.php">Mentions legales</a><br>
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
