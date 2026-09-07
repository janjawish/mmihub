<?php
// charge la BDD + session_start()
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/track_visit.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title>MMI HUB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
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
      <a href="notes.php" class="nav-link nav-notes">MMI Notes</a>
      <a href="abs.php" class="nav-link nav-abs">MMI ABS</a>
      <a href="chat.php" class="nav-link nav-chat">MMI Chat</a>
      <a href="mmi_edt.php" class="nav-link nav-services">MMI EDT</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="mon-profil" class="nav-cta">Mon profil</a>
        <a href="disconnect.php" class="nav-cta">Se déconnecter</a>
      <?php else: ?>
        <a href="connexion" class="nav-cta">Connexion</a>
      <?php endif; ?>
    </nav>
  </div>
</header>



<main id="top">
    <!-- HERO / INTRO -->
    <section id="intro" class="hero">
        <div class="hero-grid">
            <div class="hero-text">
                <p class="hero-kicker">outil étudiant · but mmi</p>
                <h1>
                    le <span>hub</span> pour
                    <br>suivre <span>ton</span> année <span>gratuitement</span>
                </h1>
                <p class="hero-lead">
                    Marre de la galère pour suivre tes moyennes, tes compétences et tes heures d’absence ?
                    MMI HUB rassemble tout au même endroit, avec les vraies règles du BUT MMI.
                </p>
<div class="hero-actions">
    <?php if (!empty($_SESSION['user_id'])): ?>
        <!-- Utilisateur connecté → bouton vers le profil -->
        <a href="mon-profil" class="btn btn-primary">Découvrez votre profil</a>
    <?php else: ?>
        <!-- Utilisateur non connecté → bouton d'inscription -->
        <a href="creation-de-compte" class="btn btn-primary">Créer mon compte</a>
    <?php endif; ?>

    <a href="#features" class="btn btn-ghost">Voir comment ça marche</a>
</div>

                <div class="hero-tags">
                    <span class="tag">MMI Notes</span>
                    <span class="tag">MMI ABS</span>
                    <span class="tag">MMI Chat</span>
                    <span class="tag">MMI EDT</span>
                </div>
            </div>

            <!-- côté stickers -->
            <div class="hero-bento">
                <a class="sticker sticker-notes" href="notes.php" aria-label="Aller à MMI Notes">
                    <span class="sticker-title">MMI NOTES</span>
                    <span class="sticker-sub">Moyenne, compétences validées, et pleins d'autres</span>
                </a>
                <a class="sticker sticker-abs" href="abs.php" aria-label="Aller à MMI ABS">
                    <span class="sticker-title">+10H ?</span>
                    <span class="sticker-sub">Suivi de tes absences et ta moyenne en temps réel </span>
                </a>
                <a class="sticker sticker-chat" href="chat.php" aria-label="Aller à MMI Chat">
                    <span class="sticker-title">MMI CHAT</span>
                    <span class="sticker-sub">Créer un vrai réseau MMI entre les départements</span>
                </a>
                <a class="sticker sticker-services" href="mmi_edt.php" aria-label="Aller à MMI EDT">
                    <span class="sticker-title">MMI EDT</span>
                    <span class="sticker-sub">Mettre ton lien ADE et tu as accés à ton emploi du temps</span>
                </a>
            </div>
        </div>
    </section>

    <!-- STORYTELLING -->
    <section id="story" class="story">
        <div class="story-grid">
            <div class="story-block story-me">
                <h2>Je suis Jan Jawish,<br>étudiant en BUT MMI.</h2>
                <p>
                    Je suis en BUT MMI et le département nous offre pas une application comme ce qu'on avait au lycée Pronote
                    et c'était presque impossible de suivre ses notes et ses absences dans des tableaux excel
                    ou n'importe quelle autre application car le systéme de notation est beaucoup trop complexe.
                    L'idée de cet outil est de vous aider à vous situer dans l'année, que ce soit les notes ou les absences.
                    Mais aussi de permettre aux BUT MMI de faire un vrai réseau via MMI CHAT où les 
                    éléves en MMI peuvent discuter depuis les différents départements (Elbeuf, Troye, etc) et avoir accés à leur emploi du temps via MMI EDT
                </p>
                <p>
                    J’ai donc décidé de fabriquer l’outil que j’aurais aimé avoir dès le S1 :
                    clair, simple, pensé <strong>par</strong> un MMI <strong>pour</strong> les MMI.
                </p>
            </div>
            <div class="story-block story-why">
                <h3>MMI HUB, c’est quoi ?</h3>
                <ul>
                    <li><strong>MMI Notes</strong> Un suivi complet de tes notes avec les bons coefficients</li>
                    <li><strong>MMI ABS</strong> Pouvoir noter tes absences pour avoir un ordre d'idée mais surtout dépassés les 10h le calcul est fait dans MMI NOTES pour savoir combien de points tu perds et dans quelle ressource et une moyenne en temps réelle</li>
                    <li><strong>MMI Chat</strong> Un chat pour VOUS permettre de discuter de vos projets, des outils que vous utilisez au quotidien et surtout des petits tips entre MMI.</li>
                    <li><strong>MMI EDT</strong> Avoir accés à ton emploi du temps !</li>
                </ul>
                <p>
                    Tout est basé sur les MCC officielles du BUT MMI, avec une interface agréable.
                </p>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="features">
        <div class="section-header">
            <p class="section-kicker">fonctionnalités</p>
            <h2>Quatre outils pour garder le contrôle</h2>
        </div>

        <div class="features-grid">
            <article class="feature-card feature-card-notes">
                <div class="feature-label">dashboard</div>
                <h3>MMI Notes</h3>
                <p>
                    Suivi des notes par ressource, UE et compétence (C1 à C5),
                    code couleur, statut de semestre, et vue claire de ce que tu dois rattraper.
                </p>
                <ul>
                    <li>Calcul DS / TP / SAE</li>
                    <li>Validation d’UE et de compétences</li>
                    <li>Message clair : semestre ok ou non</li>
                </ul>
            </article>
                        <article class="feature-card feature-card-alt">
                <div class="feature-label">dashboard</div>
                <h3>MMI ABS</h3>
                <p>
                    Tu indiques la ressource, la durée et si c’est justifié ou pas.
                    MMI HUB suit automatiquement ton total d’absences non justifiées.
                </p>
                <ul>
                    <li>Alerte visuelle quand tu approches des 10h</li>
                    <li>Indication de la pénalité (-0,5 par ressource)</li>
                    <li>Moyenne en temps réel</li>
                </ul>
            </article>
            <article class="feature-card feature-card-chat">
                <div class="feature-label">MMI Chat</div>
                <h3>MMI Chat</h3>
                <p>
                    Un espace de discussion entre étudiants pour poser tes questions,
                    partager des ressources et garder toutes les réponses au même endroit.
                </p>
                <ul>
                    <li>Prise en main facile</li>
                    <li>Profile publique</li>
                    <li>Systéme de modération</li>
                </ul>
            </article>

            <article class="feature-card feature-card-services">
                <div class="feature-label">MMI EDT</div>
                <h3>MMI EDT</h3>
                <p>
                    Un outil qui va te permettre d'avoir ton emploi du temps à porté de 
                    mail sans te connecter à ton ent à chaque fois.
                </p>
                <ul>
                    <li>Tu mets ton lien ADE</li>
                    <li>L'outil te l'affiche immédiatement et aux prochaines connexions</li>
                </ul>
            </article>


        </div>
    </section>

    <!-- CTA -->
    <section id="cta" class="cta">
        <div class="cta-inner">
            <?php if (empty($_SESSION['user_id'])): ?>
                <!-- Version pour visiteur non connecté -->
                <div class="cta-text">
                    <p class="cta-kicker">prêt à t’organiser ?</p>
                    <h2>Crée ton compte et prépare ton semestre dès maintenant.</h2>
                    <p>
                        Tu indiques ton année (BUT 1, 2 ou 3), MMI HUB se charge du reste :
                        semestres, parcours, formation initiale ou alternance.
                    </p>
                </div>
                <div class="cta-actions">
                    <a href="/mmihub/creation-de-compte" class="btn btn-primary btn-big">Créer un compte</a>
                    <a href="/mmihub/connexion" class="btn btn-outline">Se connecter</a>
                </div>
            <?php else: ?>
                <!-- Version pour utilisateur connecté -->
                <div class="cta-text">
                    <p class="cta-kicker">prêt à t’organiser ?</p>
                    <h2>Découvre MMI Notes et MMI ABS dès maintenant.</h2>
                    <p>
                        Suis tes moyennes, tes compétences et tes heures d’absence en temps réel
                        avec MMI Notes et MMI ABS. MMI HUB s’occupe du calcul, toi tu te concentres
                        sur ton année.
                    </p>
                </div>
                <div class="cta-actions">
                    <a href="/mmihub/mmi-notes" class="btn btn-primary btn-big">Découvrir MMI Notes</a>
                    <a href="/mmihub/mmi-abs" class="btn btn-outline btn-big">Découvrir MMI ABS</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> MMI HUB · Projet étudiant de Jan Jawish – BUT MMI.</p>
    <p class="footer-sub">Respect du RGPD · Tes données servent uniquement à ton suivi personnel.</p>
    <a href="mentions.php">Mentions legales</a><br>
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
