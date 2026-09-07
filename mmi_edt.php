<?php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/semester.php';
require_once __DIR__ . '/includes/mmi_edt.php';
require_once __DIR__ . '/includes/track_visit.php';

$user = require_login($pdo);

// Semestre courant
$currentSemesterId   = getCurrentSemesterNumberForUser($user);
$currentSemesterCode = 'S' . $currentSemesterId;

// Récup des réglages EDT existants
$edtSettings = mmi_edt_get_settings($pdo, (int)$user['id']);
$icalUrl     = $edtSettings['ical_url'] ?? '';

$edtError   = '';
$edtSuccess = '';

// Formulaire : sauvegarde du lien ADE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_edt_link') {
    $url = trim($_POST['ical_url'] ?? '');

    if ($url === '') {
        $edtError = "Merci de coller ton lien ADE d'emploi du temps.";
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $edtError = "Le lien fourni n'est pas une URL valide.";
    } else {
        try {
            mmi_edt_save_settings($pdo, (int)$user['id'], $url);
            $edtSettings = mmi_edt_get_settings($pdo, (int)$user['id']);
            $icalUrl     = $edtSettings['ical_url'] ?? $url;
            $edtSuccess  = "Lien d'emploi du temps enregistré ✅";
        } catch (Throwable $e) {
            $edtError = "Impossible d'enregistrer ton lien pour l'instant.";
        }
    }
}

// Chargement des événements + regroupement par semaines
$weeks = [];
if ($icalUrl !== '') {
    try {
        $events = mmi_edt_fetch_events_from_url($icalUrl);

        // On limite grosso modo aux 6 prochaines semaines
        $now   = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
        $limit = $now->modify('+42 days');

        $events = array_filter($events, function (array $event) use ($now, $limit) {
            $start = $event['start'] ?? null;
            if (!$start instanceof DateTimeInterface) {
                return false;
            }
            return $start >= $now->modify('-2 days') && $start <= $limit;
        });

        $weeks = mmi_edt_group_events_by_week_and_day($events);
    } catch (Throwable $e) {
        $edtError = "Impossible de charger ton emploi du temps ADE.";
    }
}

// Semaine sélectionnée (GET ?week=2025-W49)
$selectedWeekKey = null;
if (!empty($_GET['week']) && isset($weeks[$_GET['week']])) {
    $selectedWeekKey = $_GET['week'];
} elseif (!empty($weeks)) {
    $keys            = array_keys($weeks);
    $selectedWeekKey = reset($keys);
}

$selectedWeek = $selectedWeekKey ? $weeks[$selectedWeekKey] : null;

// Tableau jours FR
$FR_DAYS = [
    'Monday'    => 'Lundi',
    'Tuesday'   => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday'  => 'Jeudi',
    'Friday'    => 'Vendredi',
    'Saturday'  => 'Samedi',
    'Sunday'    => 'Dimanche',
];

// Constantes pour la hauteur / heures (desktop)
$DAY_START_MIN   = 8 * 60 + 30;  // 08:30
$DAY_END_MIN     = 19 * 60;      // 19:00
$DAY_TOTAL_MIN   = $DAY_END_MIN - $DAY_START_MIN; // 630
$HOUR_SLOT_PX    = 64;           // 1h = 64px
$MINUTE_TO_PX    = $HOUR_SLOT_PX / 60; // ≈ 1.0667
$DAY_HEIGHT_PX   = (int) round($DAY_TOTAL_MIN * $MINUTE_TO_PX); // ≈ 672

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title>MMI EDT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    >

    <style>
      html, body {
        max-width: 100%;
        overflow-x: hidden; /* force aucune largeur qui dépasse */
      }

      /* Accent MMI EDT : on réutilise le bleu services (#1a03f4 via --mmi-services) */
      .edt-accent {
        color: var(--mmi-services);
      }

      .edt-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 1.95fr);
        gap: 2rem;
        align-items: flex-start;
      }

      @media (max-width: 960px) {
        .edt-grid {
          grid-template-columns: 1fr;
        }
      }

      .edt-link-panel {
        margin-top: 1.25rem;
        padding: 1rem 1.2rem;
        border-radius: 22px;
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow:
          0 18px 40px rgba(15, 23, 42, 0.85),
          0 0 0 1px rgba(15, 23, 42, 1);
      }
/* juste après .edt-link-panel { ... } */
.edt-link-panel,
.edt-calendar,
.edt-week-grid {
  width: 100%;
  max-width: 100%;
  margin-left: 0;
  margin-right: 0;
  box-sizing: border-box;
}

      .edt-link-panel label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.14em;
        opacity: 0.8;
        display: block;
        margin-bottom: 0.45rem;
      }

      .edt-link-row {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
      }

      .edt-link-row input[type="url"] {
        flex: 1 1 260px;
        min-width: 0;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.6);
        background: rgba(15, 23, 42, 0.95);
        color: #e5e7eb;
        padding: 0.55rem 0.9rem;
        font-size: 0.85rem;
        outline: none;
      }

      .edt-link-row input[type="url"]::placeholder {
        color: rgba(148, 163, 184, 0.9);
      }

      .edt-link-row button {
        border-radius: 999px;
        border: none;
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        background: var(--mmi-services);
        color: #f9fafb;
        white-space: nowrap;
      }

      .edt-link-row button:hover {
        filter: brightness(1.1);
      }

      .edt-hint {
        margin-top: 0.45rem;
        font-size: 0.75rem;
        opacity: 0.75;
      }

      .edt-alert {
        margin-top: 0.6rem;
        font-size: 0.8rem;
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
      }

      .edt-alert-error {
        background: rgba(239, 68, 68, 0.12);
        color: #fecaca;
        border: 1px solid rgba(248, 113, 113, 0.6);
      }

      .edt-alert-success {
        background: rgba(16, 185, 129, 0.12);
        color: #bbf7d0;
        border: 1px solid rgba(52, 211, 153, 0.6);
      }

      .edt-calendar {
        border-radius: 24px;
        background: rgba(15, 23, 42, 0.98);
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow:
          0 18px 45px rgba(15, 23, 42, 0.9),
          0 0 0 1px rgba(15, 23, 42, 1);
        padding: 1.2rem 1.4rem;
        box-sizing: border-box;
        max-width: 100%;
      }

      .edt-calendar-header {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.8rem;
      }

      .edt-calendar-header h2 {
        margin: 0;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        opacity: 0.85;
      }

      .edt-calendar-header span {
        font-size: 0.8rem;
        opacity: 0.7;
      }

      /* Onglets de semaines façon ADE (Semaine 49, 50...) */
      .edt-week-tabs {
        display: flex;
        gap: 0.3rem;
        overflow-x: auto;
        padding: 0 0 0.3rem;
        margin-bottom: 0.9rem;
        width: 100%;
        box-sizing: border-box;
      }

      .edt-week-tab {
        flex: 0 0 auto;
        border-radius: 999px;
        padding: 0.35rem 0.75rem;
        font-size: 0.78rem;
        border: 1px solid rgba(148, 163, 184, 0.6);
        color: #e5e7eb;
        text-decoration: none;
        background: rgba(15, 23, 42, 0.9);
        white-space: nowrap;
      }

      .edt-week-tab-active {
        border-color: rgba(129, 140, 248, 1);
        background: var(--mmi-services);
        color: #f9fafb;
      }

      .edt-week-tab span {
        opacity: 0.9;
      }

      /* Grille semaine : 1 col heures + 7 col jours (desktop) */
      .edt-week-grid {
        margin-top: 0.4rem;
        border-radius: 20px;
        background: radial-gradient(circle at top left, rgba(37, 99, 235, 0.22), rgba(15, 23, 42, 0.98));
        border: 1px solid rgba(129, 140, 248, 0.4);
        padding: 0.9rem;
        box-sizing: border-box;
        max-width: 100%;
      }

      .edt-week-grid-header {
        display: grid;
        grid-template-columns: 80px repeat(7, 1fr);
        gap: 0.6rem;
        margin-bottom: 0.4rem;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        opacity: 0.85;
      }

      .edt-week-grid-header div {
        padding: 0.15rem 0.25rem;
      }

      .edt-week-grid-header span {
        display: block;
        font-size: 0.72rem;
        opacity: 0.8;
        text-transform: none;
        letter-spacing: 0;
      }

      .edt-week-days {
        display: grid;
        grid-template-columns: 80px repeat(7, 1fr);
        gap: 0.6rem;
        max-width: 100%;
      }

      .edt-times-col {
        font-size: 0.75rem;
        opacity: 0.85;
        display: flex;
        flex-direction: column;
      }

      .edt-time-slot {
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
        padding: 0.05rem 0.1rem;
        box-sizing: border-box;
      }

      .edt-week-day-column {
        min-height: 5rem;
      }

      .edt-week-day-column-inner {
        position: relative;
        height: <?= $DAY_HEIGHT_PX ?>px;
        border-radius: 16px;
        background: rgba(15, 23, 42, 0.96);
        border: 1px solid rgba(148, 163, 184, 0.4);
        overflow: hidden;
        padding-top: 0.3rem;
        box-sizing: border-box;
      }

      /* Lignes horaires (fines) */
      .edt-hour-line {
        position: absolute;
        left: 0;
        right: 0;
        height: 1px;
        background: rgba(255, 255, 255, 0.08);
      }

      /* Blocs cours positionnés (desktop) */
      .edt-event-block {
        position: absolute;
        left: 6%;
        width: 88%;
        border-radius: 10px;
        padding: 0.45rem 0.55rem;
        color: #fff;
        background: rgba(59, 130, 246, 0.95);
        border: 1px solid rgba(191, 219, 254, 0.9);
        overflow: hidden;
        font-size: 0.8rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-sizing: border-box;
      }

      .edt-event-block:nth-child(3n+2) {
        background: rgba(99, 102, 241, 0.95);
      }

      .edt-event-block:nth-child(3n) {
        background: rgba(37, 99, 235, 0.95);
      }

      .edt-week-day-empty {
        font-size: 0.75rem;
        opacity: 0.65;
        padding: 0.4rem 0.5rem;
      }

      .edt-event-title {
        font-size: 0.8rem;
        font-weight: 600;
      }

      .edt-event-time {
        font-size: 0.75rem;
        opacity: 0.95;
      }

      .edt-event-meta {
        font-size: 0.73rem;
        opacity: 0.92;
      }

      .edt-empty {
        font-size: 0.85rem;
        opacity: 0.8;
      }

      /* Label jour utilisé uniquement sur mobile */
      .edt-day-mobile-label {
        display: none;
        margin-bottom: 0.35rem;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        opacity: 0.85;
      }

      .edt-day-mobile-label span {
        margin-left: 0.35rem;
        font-size: 0.75rem;
        text-transform: none;
        letter-spacing: 0;
        opacity: 0.8;
      }

      /* Responsive mobile */
      @media (max-width: 900px) {
        main.auth-page.notes-page {
          padding-left: 1rem;
          padding-right: 1rem;
          box-sizing: border-box;
        }

        .edt-link-panel,
        .edt-calendar {
          width: 100%;
          margin-left: 0;
          margin-right: 0;
        }

        .edt-week-grid {
          overflow-x: hidden;
          padding: 0.8rem;
          width: 100%;
        }

        .edt-week-grid-header {
          display: none;
        }

        .edt-week-days {
          grid-template-columns: 1fr;
        }

        .edt-times-col {
          display: none;
        }

        .edt-week-day-column-inner {
          position: static;
          height: auto;
          padding-top: 0.6rem;
        }

        .edt-hour-line {
          display: none;
        }

        .edt-event-block {
          position: static;
          width: 100%;
          margin-bottom: 0.5rem;
        }

        .edt-day-mobile-label {
          display: flex;
          align-items: baseline;
        }
      }
      /* Empêche la rangée input + bouton de dépasser sur petit écran */
@media (max-width: 600px) {
  .edt-link-row {
    flex-direction: column;
    align-items: stretch;
    gap: 0.4rem;
  }

  .edt-link-row input[type="url"] {
    flex: 1 1 auto;
    width: 100%;
  }

  .edt-link-row button {
    width: 100%;
    text-align: center;
  }
}
/* Menu mobile spécifique à cette page */
@media (max-width: 900px) {
  body.notes-body .main-nav {
    display: none;
  }

  body.notes-body .main-nav.is-open {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: absolute;
    top: 64px;             /* hauteur du header */
    left: 0;
    right: 0;
    padding: 1rem 1.25rem;
    background: rgba(15, 23, 42, 0.98);
    border-bottom: 1px solid rgba(148, 163, 184, 0.4);
    z-index: 40;
  }

  body.notes-body .nav-toggle.is-open .nav-toggle-bar:nth-child(1) {
    transform: translateY(6px) rotate(45deg);
  }
  body.notes-body .nav-toggle.is-open .nav-toggle-bar:nth-child(2) {
    opacity: 0;
  }
  body.notes-body .nav-toggle.is-open .nav-toggle-bar:nth-child(3) {
    transform: translateY(-6px) rotate(-45deg);
  }
}

    </style>
</head>
<body class="notes-body">

<header class="site-header">
  <div class="header-inner">
    <a href="accueil#top" class="logo">
      <span class="logo-mark-services">MMI</span>
      <span class="logo-word">HUB</span>
    </a>

    <button class="nav-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
    </button>

    <nav class="main-nav">
      <a href="mmi-notes" class="nav-link nav-notes">MMI Notes</a>
      <a href="mmi-abs" class="nav-link nav-abs">MMI ABS</a>
      <a href="mmi-chat" class="nav-link nav-chat">MMI Chat</a>
      <!-- on garde la classe nav-services pour récupérer le bleu -->
      <a href="mmi-edt" class="nav-link nav-services">MMI EDT</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="mon-profil" class="nav-cta">Mon profil</a>
        <a href="disconnect.php" class="nav-cta">Se déconnecter</a>
      <?php else: ?>
        <a href="connexion" class="nav-cta">Connexion</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="auth-page notes-page">
  <section class="notes-hero">
    <div class="notes-hero-kicker">MMI HUB · MMI EDT</div>
    <h1 class="edt-accent">MMI EDT</h1>
    <p>
      Visualise ton <strong>emploi du temps</strong> semaine par semaine,
      directement dans MMI HUB, à partir de ton lien ADE (iCal) pour le semestre
      <span class="notes-semester-pill"><?= htmlspecialchars($currentSemesterCode) ?></span>.
    </p>
  </section>

  <div class="edt-grid">
    <!-- Colonne gauche : configuration du lien ADE -->
    <section class="auth-intros">
      <p class="notes-kicker">Lien ADE / iCal</p>

      <div class="edt-link-panel">
        <form method="post">
          <input type="hidden" name="action" value="save_edt_link">

          <label for="ical_url">Lien iCal de ton emploi du temps</label>

          <div class="edt-link-row">
            <input
              type="url"
              id="ical_url"
              name="ical_url"
              required
              placeholder="https://adecampus.univ-rouen.fr/...&calType=ical&nbWeeks=4..."
              value="<?= htmlspecialchars($icalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >
            <button type="submit">Enregistrer</button>
          </div>

          <p class="edt-hint">
            Sur ADE, clique sur <strong>Exporter</strong> &gt; <strong>iCal</strong>, copie le lien,
            puis colle-le ici.
          </p>

          <?php if ($edtError !== ''): ?>
            <p class="edt-alert edt-alert-error">
              <?= htmlspecialchars($edtError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
          <?php elseif ($edtSuccess !== ''): ?>
            <p class="edt-alert edt-alert-success">
              <?= htmlspecialchars($edtSuccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
          <?php endif; ?>
        </form>
      </div>

      <p style="font-size:0.8rem;opacity:0.8;margin-top:1rem;">
        Tu peux ajuster le paramètre <code>nbWeeks</code> dans ton lien ADE
        pour afficher plus ou moins de semaines dans MMI HUB.
      </p>
    </section>

    <!-- Colonne droite : calendrier type ADE -->
    <section class="edt-calendar">
      <div class="edt-calendar-header">
        <h2>Vue hebdomadaire</h2>
        <span>
          Source : <?= $icalUrl ? 'ADE (flux iCal)' : 'aucune source configurée' ?>
        </span>
      </div>

      <?php if ($icalUrl === ''): ?>
        <p class="edt-empty">
          Colle ton lien ADE dans le panneau de gauche pour afficher ton emploi du temps,
          façon ADE mais avec la charte MMI HUB 💼📅
        </p>
      <?php elseif (empty($weeks)): ?>
        <p class="edt-empty">
          Aucun cours trouvé sur les prochaines semaines (vérifie ton lien ADE
          ou le paramètre <code>nbWeeks</code>).
        </p>
      <?php else: ?>
        <!-- Onglets de semaines -->
        <div class="edt-week-tabs">
          <?php foreach ($weeks as $key => $week): ?>
            <?php
              /** @var DateTimeInterface $weekStart */
              $weekStart = $week['start'];
              $weekEnd   = $weekStart->modify('+6 days');
              $isActive  = ($key === $selectedWeekKey);
            ?>
            <a
              class="edt-week-tab <?= $isActive ? 'edt-week-tab-active' : '' ?>"
              href="mmi-edt?week=<?= urlencode($key) ?>"
            >
              <strong>Semaine <?= (int)$week['week'] ?></strong>
              <span>
                · <?= $weekStart->format('d/m') ?> → <?= $weekEnd->format('d/m') ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if ($selectedWeek): ?>
          <?php
            /** @var DateTimeInterface $monday */
            $monday = $selectedWeek['start'];
          ?>

          <div class="edt-week-grid">
            <!-- Ligne des jours -->
            <div class="edt-week-grid-header">
              <div>Heures</div>
              <?php for ($i = 0; $i < 7; $i++): ?>
                <?php
                  $date = $monday->modify("+{$i} days");
                  $en   = $date->format('l');
                  $fr   = $FR_DAYS[$en] ?? $en;
                ?>
                <div>
                  <?= htmlspecialchars($fr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  <span><?= $date->format('d/m') ?></span>
                </div>
              <?php endfor; ?>
            </div>

            <!-- Colonnes jours + colonne heures -->
            <div class="edt-week-days">
              <!-- Colonne heures -->
              <div class="edt-times-col">
                <?php
                  // labels toutes les 30min de 08:30 à 19:00
                  for ($m = 0; $m <= $DAY_TOTAL_MIN; $m += 30) {
                      $minutes = $DAY_START_MIN + $m;
                      $h = (int) floor($minutes / 60);
                      $min = $minutes % 60;
                      $label = sprintf('%02d:%02d', $h, $min);
                      $rowHeight = 30 * $MINUTE_TO_PX; // 30 minutes
                      echo '<div class="edt-time-slot" style="height:' . htmlspecialchars(round($rowHeight,1), ENT_QUOTES, 'UTF-8') . 'px">'
                          . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                          . '</div>';
                  }
                ?>
              </div>

              <!-- 7 colonnes de jours -->
              <?php for ($i = 0; $i < 7; $i++): ?>
                <?php
                  $date      = $monday->modify("+{$i} days");
                  $dayKey    = $date->format('Y-m-d');
                  $dayEvents = $selectedWeek['days'][$dayKey] ?? [];

                  $enDay = $date->format('l');
                  $frDay = $FR_DAYS[$enDay] ?? $enDay;
                ?>
                <div class="edt-week-day-column">
                  <div class="edt-week-day-column-inner">
                    <!-- Label utilisé sur mobile (caché sur desktop) -->
                    <div class="edt-day-mobile-label">
                      <?= htmlspecialchars($frDay, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      <span><?= $date->format('d/m') ?></span>
                    </div>

                    <?php
                      // Lignes horaires toutes les 30 minutes (08:30 -> 19:00)
                      $lineStepMin = 30;
                      $lineCount   = (int) ($DAY_TOTAL_MIN / $lineStepMin); // 21 segments
                      for ($k = 0; $k <= $lineCount; $k++) {
                          $minutesSinceStart = $k * $lineStepMin;
                          $topPx = $minutesSinceStart * $MINUTE_TO_PX;
                          echo '<div class="edt-hour-line" style="top:' . htmlspecialchars(round($topPx,1), ENT_QUOTES, 'UTF-8') . 'px"></div>';
                      }
                    ?>

                    <?php if (empty($dayEvents)): ?>
                      <div class="edt-week-day-empty">
                        Pas de cours
                      </div>
                    <?php else: ?>
                      <?php foreach ($dayEvents as $event): ?>
                        <?php
                          /** @var DateTimeInterface|null $start */
                          /** @var DateTimeInterface|null $end */
                          $start = $event['start'] ?? null;
                          $end   = $event['end']   ?? null;

                          if (!$start instanceof DateTimeInterface || !$end instanceof DateTimeInterface) {
                              continue;
                          }

                          // Minutes absolues
                          $startMin = (int)$start->format('H') * 60 + (int)$start->format('i');
                          $endMin   = (int)$end->format('H') * 60 + (int)$end->format('i');

                          // Clamp dans la plage 08:30 -> 19:00
                          if ($startMin < $DAY_START_MIN) $startMin = $DAY_START_MIN;
                          if ($endMin   > $DAY_END_MIN)   $endMin   = $DAY_END_MIN;

                          if ($endMin <= $startMin) {
                              $endMin = $startMin + 60; // minimum 1h si souci
                          }

                          $topPx    = ($startMin - $DAY_START_MIN) * $MINUTE_TO_PX;
                          $heightPx = ($endMin   - $startMin)     * $MINUTE_TO_PX;
                          if ($heightPx < 32) {
                              $heightPx = 32; // hauteur mini
                          }

                          list($room, $teacher) = mmi_edt_extract_room_and_teacher($event);
                        ?>
                        <div
                          class="edt-event-block"
                          style="top:<?= htmlspecialchars(round($topPx, 1), ENT_QUOTES, 'UTF-8') ?>px;
                                 height:<?= htmlspecialchars(round($heightPx, 1), ENT_QUOTES, 'UTF-8') ?>px;"
                        >
                          <div class="edt-event-title">
                            <?= htmlspecialchars($event['summary'] ?? 'Cours', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                          </div>

                          <div class="edt-event-time">
                            <?= htmlspecialchars($start->format('H:i') . ' – ' . $end->format('H:i'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                          </div>

                          <div class="edt-event-meta">
                            <?php if ($room !== ''): ?>
                              Salle <?= htmlspecialchars($room, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php endif; ?>
                            <?php if ($teacher !== ''): ?>
                              <?php if ($room !== ''): ?><br><?php endif; ?>
                              Prof <?= htmlspecialchars($teacher, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endfor; ?>

            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</main>

<script src="app.js"></script>
<script>
  // Fallback / fix nav mobile pour cette page
  document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.nav-toggle');
    const nav    = document.querySelector('.main-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
      const open = nav.classList.toggle('is-open');
      toggle.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('nav-open', open);
    });
  });
</script>
</body>
</html>

