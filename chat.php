<?php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/track_visit.php';

if (empty($_SESSION['user_id'])) {
    header('Location: connexion');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: connexion');
    exit;
}

require_once __DIR__ . '/includes/chat.php';

// Si banni, on bloque direct
if (chat_is_banned($user)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>MMI CHAT – accès refusé</title>
        <link rel="stylesheet" href="/mmihub/style.css">
    </head>
    <body class="notes-body">

    <main class="auth-page">
      <div class="auth-card">
        <h1>Accès au chat refusé</h1>
        <p>Ton compte est actuellement <strong>banni du salon MMI CHAT</strong>. Si tu penses qu’il s’agit d’une erreur, contacte l’équipe pédagogique.</p>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$errorMessage   = '';
$successMessage = '';

// Envoi de message (classique, avec reload de la page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $content  = $_POST['content'] ?? '';
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!chat_can_send($user)) {
        $errorMessage = "Tu ne peux pas envoyer de message pour le moment (compte trop récent, mute ou autre).";
    } elseif (!chat_post_message($pdo, $user, $content, $parentId)) {
        $errorMessage = "Impossible d'envoyer ton message.";
    } else {
        header('Location: mmi-chat#bottom');
        exit;
    }
}

// Toggle réaction (fallback sans JS)
if (isset($_GET['action']) && $_GET['action'] === 'react' && isset($_GET['id'], $_GET['emoji'])) {
    $mid   = (int)$_GET['id'];
    $emoji = $_GET['emoji'];
    chat_toggle_reaction($pdo, $user, $mid, $emoji);
    header('Location: mmi-chat#msg-' . $mid);
    exit;
}

// Actions admin : suppression / mute / ban
if (chat_is_admin($user) && isset($_GET['admin_action'])) {
    $adminAction = $_GET['admin_action'];

    if ($adminAction === 'delete_msg' && isset($_GET['id'])) {
        chat_delete_message($pdo, (int)$_GET['id']);
        header('Location: mmi-chat');
        exit;
    }

    if ($adminAction === 'mute' && isset($_GET['user_id'])) {
        $targetUserId = (int)$_GET['user_id'];
        $hours        = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
        chat_mute_user($pdo, $targetUserId, $hours);
        header('Location: mmi-chat');
        exit;
    }

    if ($adminAction === 'ban' && isset($_GET['user_id'])) {
        $targetUserId = (int)$_GET['user_id'];
        chat_ban_user($pdo, $targetUserId);
        header('Location: mmi-chat');
        exit;
    }
}

// Envoi d’un avertissement admin
if (chat_is_admin($user) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_warning') {
    $targetId = (int)($_POST['target_user_id'] ?? 0);
    $msg      = trim($_POST['warning_message'] ?? '');

    if (!$targetId || $msg === '') {
        $errorMessage = "Merci de renseigner un ID utilisateur valide et un message.";
    } else {
        // Vérifier que l'utilisateur existe vraiment
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $check->execute([$targetId]);
        $exists = $check->fetchColumn();

        if (!$exists) {
            $errorMessage = "Aucun utilisateur trouvé avec l'ID " . htmlspecialchars($targetId) . ".";
        } else {
            if (chat_send_warning($pdo, $targetId, $user['id'], $msg)) {
                $successMessage = "Avertissement envoyé.";
            } else {
                $errorMessage = "Impossible d'envoyer l'avertissement.";
            }
        }
    }
}

// Gestion du "reply"
$replyTo   = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : 0;
$replyMsg  = null;
if ($replyTo > 0) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.content_filtered,
               u.first_name, u.last_name
        FROM chat_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ? AND m.deleted_at IS NULL
    ");
    $stmt->execute([$replyTo]);
    $replyMsg = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Charger les messages existants
$messages = chat_load_messages($pdo, $user['id'], 80);

// Pour le JS temps réel
$lastMessageId = 0;
if (!empty($messages)) {
    $ids = array_column($messages, 'id');
    $lastMessageId = max($ids);
}
$isAdmin = chat_is_admin($user);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title>MMI CHAT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="notes-body">

<header class="site-header">
  <div class="header-inner">
    <a href="accueil#top" class="logo">
      <span class="logo-mark-chat">MMI</span>
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
      <a href="mmi-notes" class="nav-link nav-notes" >MMI Notes</a>
      <a href="mmi-abs" class="nav-link nav-abs" >MMI ABS</a>
      <a href="mmi-chat" class="nav-link nav-chat" >MMI Chat</a>
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

<main class="auth-page notes-page">
  <div class="notes-grid">

    <!-- Colonne gauche -->
    <section class="auth-intro">
      <p class="notes-kicker">MMI HUB · Salon texte</p>
      <h1>MMI CHAT</h1>
      <p>
        Discute avec les autres étudiant·e·s. Les comptes de moins de <strong><?= CHAT_MIN_ACCOUNT_AGE_HOURS ?></strong> heures ne peuvent pas encore envoyer de messages.
      </p>

      <?php if (chat_is_muted($user)): ?>
        <div class="auth-error" style="margin-top:1rem;">
          <p>Tu es actuellement <strong>muet dans le chat</strong>. Tu peux toujours lire les messages mais pas en envoyer.</p>
        </div>
      <?php endif; ?>

      <?php if (!chat_has_old_enough_account($user) && !chat_is_admin($user)): ?>
        <div class="auth-card" style="margin-top:1.2rem;">
          <p>
            Ton compte a été créé récemment. Tu pourras participer au salon
            dans quelques heures. En attendant, tu peux lire les messages.
          </p>
        </div>
      <?php endif; ?>

    </section>

    <!-- Colonne droite : le salon -->
    <section class="auth-card profile-section-card" style="display:flex;flex-direction:column;gap:1rem;max-height:80vh;">
      <h2>SALON GÉNÉRAL</h2>

      <?php if ($errorMessage): ?>
        <div class="auth-error"><p><?= htmlspecialchars($errorMessage) ?></p></div>
      <?php elseif ($successMessage): ?>
        <div class="auth-success"><p><?= htmlspecialchars($successMessage) ?></p></div>
      <?php endif; ?>

      <!-- Liste des messages -->
      <div class="chat-thread" style="flex:1;overflow-y:auto;padding-right:0.5rem;">
        <?php if (!$messages): ?>
          <p style="opacity:0.7;">Aucun message pour l’instant. Lance la conversation !</p>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <?php
              $displayName = chat_display_name($m);
              $avatarUrl   = chat_avatar_url($m);
              $isOwn       = ($m['user_id'] == $user['id']);
              $publicUrl   = chat_public_profile_url($m);
            ?>
            <article id="msg-<?= (int)$m['id'] ?>"
                     class="chat-message<?= $isOwn ? ' chat-message-own' : '' ?>">
              <div class="chat-message-header">
                <a href="<?= htmlspecialchars($publicUrl) ?>" class="chat-avatar-link">
                  <?php if ($avatarUrl): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" class="chat-avatar">
                  <?php else: ?>
                    <div class="chat-avatar chat-avatar-placeholder">
                      <?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'))) ?>
                    </div>
                  <?php endif; ?>
                </a>
                <div class="chat-meta">
                  <a href="<?= htmlspecialchars($publicUrl) ?>" class="chat-author">
                    <?= htmlspecialchars($displayName) ?>
                  </a>
                  <span class="chat-timestamp">
                    <?= htmlspecialchars(date('d/m H:i', strtotime($m['created_at']))) ?>
                    <?php if ($isAdmin && $m['user_id'] == $user['id']): ?>
                      <span class="chat-badge-admin">Admin</span>
                    <?php endif; ?>
                  </span>
                </div>

                <?php if ($isAdmin): ?>
                  <div class="chat-admin-actions">
                    <a href="chat.php?admin_action=delete_msg&id=<?= (int)$m['id'] ?>" class="chat-admin-link"
                       onclick="return confirm('Supprimer ce message ?');">Supprimer</a>
                    <a href="chat.php?admin_action=mute&user_id=<?= (int)$m['user_id'] ?>&hours=24" class="chat-admin-link">Muet 24h</a>
                    <a href="chat.php?admin_action=ban&user_id=<?= (int)$m['user_id'] ?>" class="chat-admin-link"
                       onclick="return confirm('Bannir cet utilisateur du chat ?');">Bannir</a>
                  </div>
                <?php endif; ?>
              </div>

              <?php if (!empty($m['parent'])): ?>
                <div class="chat-reply-preview">
                  <span class="chat-reply-label">
                    En réponse à <?= htmlspecialchars(chat_display_name($m['parent'])) ?> :
                  </span>
                  <span class="chat-reply-text">
                    <?= htmlspecialchars(mb_strimwidth($m['parent']['content_filtered'], 0, 120, '…', 'UTF-8')) ?>
                  </span>
                </div>
              <?php endif; ?>

              <div class="chat-message-content">
                <?= nl2br(htmlspecialchars($m['content_filtered'])) ?>
              </div>

              <div class="chat-message-footer">
                <!-- Répondre -->
                <a href="chat.php?reply_to=<?= (int)$m['id'] ?>#composer" class="chat-action-link">Répondre</a>

                <!-- Réactions -->
                <div class="chat-reactions">
                  <?php
                  $reactionEmojis = ['👍','😄','🔥','😢'];
                  foreach ($reactionEmojis as $emo):
                      $count = 0;
                      if (!empty($m['reactions'])) {
                          foreach ($m['reactions'] as $r) {
                              if ($r['emoji'] === $emo) {
                                  $count = $r['count'];
                                  break;
                              }
                          }
                      }
                  ?>
                    <a href="chat.php?action=react&id=<?= (int)$m['id'] ?>&emoji=<?= urlencode($emo) ?>"
                       class="chat-reaction-pill"
                       data-id="<?= (int)$m['id'] ?>"
                       data-emoji="<?= htmlspecialchars($emo) ?>">
                      <span><?= htmlspecialchars($emo) ?></span>
                      <?php if ($count > 0): ?>
                        <span class="chat-reaction-count"><?= (int)$count ?></span>
                      <?php endif; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
        <div id="bottom"></div>
      </div>

      <!-- Composer -->
      <div id="composer"></div>
      <div class="chat-composer">
        <?php if (!chat_can_send($user)): ?>
          <p style="font-size:0.9rem;opacity:0.7;">
            Tu ne peux pas envoyer de message pour le moment
            (compte trop récent, mute ou ban). Tu peux quand même lire la discussion.
          </p>
        <?php else: ?>
          <?php if ($replyMsg): ?>
            <div class="chat-reply-banner">
              <span>En réponse à
                <strong><?= htmlspecialchars($replyMsg['first_name'] . ' ' . mb_substr($replyMsg['last_name'], 0, 1, 'UTF-8') . '.') ?></strong> :
              </span>
              <span><?= htmlspecialchars(mb_strimwidth($replyMsg['content_filtered'], 0, 140, '…', 'UTF-8')) ?></span>
              <a href="mmi-chat" class="chat-reply-cancel">Annuler</a>
            </div>
          <?php endif; ?>

          <form method="post" class="auth-form chat-send-form">
            <input type="hidden" name="action" value="send_message">
            <?php if ($replyMsg): ?>
              <input type="hidden" name="parent_id" value="<?= (int)$replyMsg['id'] ?>">
            <?php endif; ?>
            <textarea name="content" rows="3" class="auth-input"
                      style="border-radius:18px;resize:vertical;"
                      placeholder="Écris ton message (texte uniquement, pas de fichiers)"></textarea>
            <button type="submit" class="btn btn-primary btn-violet" style="margin-top:0.5rem;">
              Envoyer
            </button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($isAdmin): ?>
        <!-- FAB admin + panneau notification -->
        <button type="button" class="chat-admin-fab" id="chatAdminFab">+</button>

        <div class="chat-admin-panel" id="chatAdminPanel">
          <div class="auth-card profile-section-card">
            <h3>Outil admin : envoyer une notification</h3>
            <form method="post" class="auth-form">
              <input type="hidden" name="action" value="send_warning">
              <div class="auth-row-inline">
                <div>
                  <label class="auth-label">ID utilisateur</label>
                  <input class="auth-input" type="number" name="target_user_id" min="1">
                </div>
                <div style="flex:1;">
                  <label class="auth-label">Message</label>
                  <input class="auth-input" type="text" name="warning_message"
                         placeholder="ex : Calme un peu le spam dans le chat 😊">
                </div>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top:0.4rem;">Envoyer la notification</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

    </section>

  </div>
</main>
<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> MMI HUB · Projet étudiant de Jan Jawish – BUT MMI.</p>
    <p class="footer-sub">Respect du RGPD · Tes données servent uniquement à ton suivi personnel.</p>
    <a href="mentions.php">Mentions legales</a><br>
    <a href="politique.php">Politique de confidentialité</a>
</footer>
<script>
  const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  let lastMessageId = <?= (int)$lastMessageId ?>;

  // FAB admin -> ouvre / ferme le panneau de notif
  const fab   = document.getElementById('chatAdminFab');
  const panel = document.getElementById('chatAdminPanel');
  if (fab && panel) {
    fab.addEventListener('click', () => {
      panel.classList.toggle('open');
    });
  }

  const chatThread = document.querySelector('.chat-thread');

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function renderMessage(msg) {
    if (!chatThread) return;
    if (document.getElementById('msg-' + msg.id)) return;

    const article = document.createElement('article');
    article.id = 'msg-' + msg.id;
    article.className = 'chat-message';

    const created = new Date(msg.created_at.replace(' ', 'T'));
    const timeStr = created.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});

    const avatar = msg.avatar_url
      ? `<img src="${escapeHtml(msg.avatar_url)}" alt="" class="chat-avatar">`
      : `<div class="chat-avatar chat-avatar-placeholder">
           ${escapeHtml(msg.display_name.charAt(0).toUpperCase())}
         </div>`;

    const parentPreview = msg.parent
      ? `<div class="chat-reply-preview">
           <span class="chat-reply-label">En réponse à ${escapeHtml(msg.parent.author)} :</span>
           <span class="chat-reply-text">${escapeHtml(msg.parent.content).slice(0,120)}…</span>
         </div>`
      : '';

    const reactionEmojis = ['👍','😄','🔥','😢'];
    let reactionsHtml = '';
    for (const emo of reactionEmojis) {
      const found = (msg.reactions || []).find(r => r.emoji === emo);
      const count = found ? found.count : 0;
      reactionsHtml += `
        <a href="chat.php?action=react&id=${msg.id}&emoji=${encodeURIComponent(emo)}"
           class="chat-reaction-pill"
           data-id="${msg.id}"
           data-emoji="${emo}">
          <span>${emo}</span>${count>0 ? `<span class="chat-reaction-count">${count}</span>` : ''}
        </a>`;
    }

    // URL publique du profil : on attend que l'API renvoie msg.public_slug.
const publicUrl = msg.public_slug
  ? ('profil-publique?u=' + encodeURIComponent(msg.public_slug))
  : ('profil-publique?user_id=' + encodeURIComponent(msg.user_id));


    article.innerHTML = `
      <div class="chat-message-header">
        <a href="${publicUrl}" class="chat-avatar-link">
          ${avatar}
        </a>
        <div class="chat-meta">
          <a href="${publicUrl}" class="chat-author">
            ${escapeHtml(msg.display_name)}
          </a>
          <span class="chat-timestamp">${timeStr}</span>
        </div>
      </div>
      ${parentPreview}
      <div class="chat-message-content">
        ${escapeHtml(msg.content).replace(/\n/g,'<br>')}
      </div>
      <div class="chat-message-footer">
        <a href="chat.php?reply_to=${msg.id}#composer" class="chat-action-link">Répondre</a>
        <div class="chat-reactions">
          ${reactionsHtml}
        </div>
      </div>
    `;

    chatThread.appendChild(article);
    chatThread.scrollTop = chatThread.scrollHeight;
  }

  // Toast notif admin
  function showWarningToast(w) {
    const box = document.createElement('div');
    box.className = 'chat-warning-toast';
    box.innerHTML = `
      <strong>Avertissement du staff</strong>
      <p>${escapeHtml(w.message)}</p>
    `;
    document.body.appendChild(box);
    setTimeout(() => box.classList.add('visible'), 10);
    setTimeout(() => {
      box.classList.remove('visible');
      setTimeout(() => box.remove(), 400);
    }, 5000); // disparaît après quelques secondes
  }

  // Polling pour nouveaux messages + notifs
  async function pollChat() {
    try {
      const res = await fetch('chat_api.php?action=fetch&since_id=' + lastMessageId, {cache: 'no-store'});
      const data = await res.json();
      if (!data.ok) return;

      if (data.messages && data.messages.length) {
        data.messages.forEach(m => {
          renderMessage(m);
          if (m.id > lastMessageId) lastMessageId = m.id;
        });
      }

      if (data.warnings && data.warnings.length) {
        data.warnings.forEach(showWarningToast);
      }
    } catch (e) {
      console.error(e);
    }
  }
  setInterval(pollChat, 3000);

  // Réactions en AJAX (en direct) + fallback GET si JS plante
  if (chatThread) {
    chatThread.addEventListener('click', async (e) => {
      const link = e.target.closest('.chat-reaction-pill');
      if (!link) return;
      e.preventDefault();
      const emoji = link.dataset.emoji;
      const id    = link.dataset.id;
      try {
        await fetch('chat_api.php?action=react', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: 'message_id=' + encodeURIComponent(id) +
                '&emoji=' + encodeURIComponent(emoji)
        });
        // On force un refresh des compteurs
        pollChat();
      } catch (_) {}
    });
  }
</script>
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
