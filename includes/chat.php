<?php
// includes/chat.php

// Nombre d'heures minimum depuis la création du compte pour parler dans le chat
const CHAT_MIN_ACCOUNT_AGE_HOURS = 24;

// Liste très simple de gros mots à flouter (tu peux compléter)
const CHAT_BAD_WORDS = [
    'merde',
    'putain',
    'salope',
    'connard',
    'fdp',
    'enculé',
];

/**
 * Vérifie si l'utilisateur est admin du chat.
 */
function chat_is_admin(array $user): bool {
    return isset($user['chat_role']) && $user['chat_role'] === 'admin';
}

/**
 * Est-ce que l'utilisateur est banni du chat ? (ne doit même pas voir la page)
 */
function chat_is_banned(array $user): bool {
    if (empty($user['chat_banned_until'])) {
        return false;
    }
    $now = new DateTimeImmutable('now');
    $ban = new DateTimeImmutable($user['chat_banned_until']);
    return $ban > $now;
}

/**
 * Est-ce que l'utilisateur est muet (ne peut pas envoyer de message) ?
 */
function chat_is_muted(array $user): bool {
    if (empty($user['chat_muted_until'])) {
        return false;
    }
    $now = new DateTimeImmutable('now');
    $mute = new DateTimeImmutable($user['chat_muted_until']);
    return $mute > $now;
}

/**
 * Est-ce que son compte a au moins CHAT_MIN_ACCOUNT_AGE_HOURS heures ?
 */
function chat_has_old_enough_account(array $user): bool {
    if (empty($user['created_at'])) {
        return false;
    }
    $created = new DateTimeImmutable($user['created_at']);
    $now     = new DateTimeImmutable('now');
    $diffSec = $now->getTimestamp() - $created->getTimestamp();
    $hours   = $diffSec / 3600;

    return $hours >= CHAT_MIN_ACCOUNT_AGE_HOURS;
}

/**
 * Peut-il envoyer un message ?
 */
function chat_can_send(array $user): bool {
    // Ban = pas le droit du tout
    if (chat_is_banned($user)) {
        return false;
    }

    // Mute = pas le droit d'envoyer
    if (chat_is_muted($user)) {
        return false;
    }

    // Admin : toujours autorisé à écrire, même compte tout neuf
    if (chat_is_admin($user)) {
        return true;
    }

    // Plus tard : on ajoutera ici le rôle "profil vérifié"

    // Utilisateur normal : doit avoir un compte suffisamment ancien
    return chat_has_old_enough_account($user);
}

/**
 * Filtre les gros mots en mettant des ****
 */
function chat_filter_bad_words(string $text): string {
    if (empty(CHAT_BAD_WORDS)) {
        return $text;
    }

    foreach (CHAT_BAD_WORDS as $word) {
        $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
        $text = preg_replace_callback($pattern, function ($m) {
            $len = mb_strlen($m[0], 'UTF-8');
            return str_repeat('*', $len);
        }, $text);
    }

    return $text;
}

/**
 * Création d'un message
 */
function chat_post_message(PDO $pdo, array $user, string $content, ?int $parentId = null): bool {
    if (!chat_can_send($user)) {
        return false;
    }

    $content = trim($content);
    if ($content === '') {
        return false;
    }

    if ($parentId !== null) {
        // vérifier que le parent existe
        $check = $pdo->prepare("SELECT id FROM chat_messages WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$parentId]);
        if (!$check->fetchColumn()) {
            $parentId = null;
        }
    }

    $filtered = chat_filter_bad_words($content);

    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (user_id, content, content_filtered, parent_id)
        VALUES (:uid, :c, :cf, :pid)
    ");

    return $stmt->execute([
        ':uid' => $user['id'],
        ':c'   => $content,
        ':cf'  => $filtered,
        ':pid' => $parentId,
    ]);
}

/**
 * Charger les messages avec infos user + profil + réactions
 * (on évite ici les paramètres dynamiques type IN (?) pour ne plus avoir l'erreur HY093)
 */
function chat_load_messages(PDO $pdo, int $currentUserId, int $limit = 80): array {
    $limit = max(0, (int)$limit);

    $sql = "
        SELECT
            m.id,
            m.user_id,
            m.content_filtered,
            m.parent_id,
            m.created_at,
            u.first_name,
            u.last_name,
            u.profile_image,
            p.is_anonymous,
            p.show_photo,
            p.public_slug
        FROM chat_messages m
        JOIN users u      ON m.user_id = u.id
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE m.deleted_at IS NULL
        ORDER BY m.id ASC
    ";

    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return [];
    }

    // --- Réactions ---

    $ids = array_map('intval', array_column($rows, 'id'));
    $ids = array_values(array_unique($ids));
    $idList = $ids ? implode(',', $ids) : '';

    $reactionsByMsg = [];

    if ($idList !== '') {
        $rSql = "
            SELECT message_id, emoji, COUNT(*) AS nb
            FROM chat_message_reactions
            WHERE message_id IN ($idList)
            GROUP BY message_id, emoji
        ";
        $rStmt = $pdo->query($rSql);
        $reactionsRaw = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reactionsRaw as $r) {
            $mid = (int)$r['message_id'];
            if (!isset($reactionsByMsg[$mid])) {
                $reactionsByMsg[$mid] = [];
            }
            $reactionsByMsg[$mid][] = [
                'emoji' => $r['emoji'],
                'count' => (int)$r['nb'],
            ];
        }
    }

    foreach ($rows as &$row) {
        $mid = (int)$row['id'];
        $row['reactions'] = $reactionsByMsg[$mid] ?? [];
    }
    unset($row);

    // --- Parents pour les réponses ---

    $parentIds = array_filter(array_map('intval', array_column($rows, 'parent_id')));
    $parentIds = array_values(array_unique($parentIds));
    $parentList = $parentIds ? implode(',', $parentIds) : '';

    $parents = [];
    if ($parentList !== '') {
        $pSql = "
            SELECT
                m.id,
                m.content_filtered,
                u.first_name,
                u.last_name
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.id IN ($parentList)
        ";
        $pStmt = $pdo->query($pSql);
        $parentsRows = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($parentsRows as $p) {
            $parents[(int)$p['id']] = $p;
        }
    }

    foreach ($rows as &$row) {
        $pid = (int)$row['parent_id'];
        $row['parent'] = $parents[$pid] ?? null;
    }
    unset($row);

    return $rows; // plus anciens -> plus récents
}

/**
 * URL publique du profil à partir du slug.
 * Si pas de slug -> on le génère à la volée.
 */
function chat_public_profile_url(array $row): string {
    global $pdo;

    $userId = (int)$row['user_id'];
    $slug   = $row['public_slug'] ?? null;

    if (empty($slug)) {
        // On crée / récupère un slug unique pour ce user
        $slug = ensure_public_slug($pdo, $userId);
    }

    return 'public_profile.php?u=' . urlencode($slug);
}



/**
 * Nom à afficher dans le chat en respectant l'anonymat.
 */
function chat_display_name(array $row): string {
    if (!empty($row['is_anonymous'])) {
        return 'Anonyme';
    }
    $fn = $row['first_name'] ?? '';
    $ln = $row['last_name'] ?? '';
    $initial = $ln !== '' ? mb_substr($ln, 0, 1, 'UTF-8') . '.' : '';
    $name = trim($fn . ' ' . $initial);
    return $name !== '' ? $name : 'Utilisateur';
}

/**
 * URL de l'avatar (ou null si on affiche un avatar générique).
 */
function chat_avatar_url(array $row): ?string {
    if (!empty($row['is_anonymous'])) {
        return null;
    }
    if (!empty($row['show_photo']) && !empty($row['profile_image'])) {
        return $row['profile_image'];
    }
    return null;
}

/**
 * Toggle réaction (ajoute si pas présente, retire si déjà mise).
 * Accessible à tous les comptes (même < 24h).
 */
function chat_toggle_reaction(PDO $pdo, array $user, int $messageId, string $emoji): void {
    $emoji = trim($emoji);
    if ($messageId <= 0 || $emoji === '') {
        return;
    }

    try {
        // cherche si l'utilisateur a déjà réagi avec cet emoji
        $stmt = $pdo->prepare("
            SELECT id FROM chat_message_reactions
            WHERE message_id = :mid
              AND user_id    = :uid
              AND emoji      = :emo
            LIMIT 1
        ");
        $stmt->execute([
            ':mid' => $messageId,
            ':uid' => $user['id'],
            ':emo' => $emoji,
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            // toggle : on supprime si déjà présent
            $del = $pdo->prepare("DELETE FROM chat_message_reactions WHERE id = :id");
            $del->execute([':id' => $existingId]);
        } else {
            // sinon on ajoute
            $ins = $pdo->prepare("
                INSERT INTO chat_message_reactions (message_id, user_id, emoji)
                VALUES (:mid, :uid, :emo)
            ");
            $ins->execute([
                ':mid' => $messageId,
                ':uid' => $user['id'],
                ':emo' => $emoji,
            ]);
        }
    } catch (PDOException $e) {
        // on n'affiche rien à l'utilisateur, au pire la réaction ne passe pas
        // error_log('[chat_toggle_reaction] ' . $e->getMessage());
    }
}

/**
 * Suppression (soft delete) d'un message par un admin.
 */
function chat_delete_message(PDO $pdo, int $messageId): void {
    $stmt = $pdo->prepare("UPDATE chat_messages SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$messageId]);
}

/**
 * Mute un user pendant $hours heures (NULL => indéfini).
 */
function chat_mute_user(PDO $pdo, int $userId, ?int $hours): void {
    if ($hours === null) {
        $until = null;
    } else {
        $until = (new DateTimeImmutable('now'))->modify('+' . (int)$hours . ' hours')->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare("UPDATE users SET chat_muted_until = :u WHERE id = :id");
    $stmt->execute([
        ':u'  => $until,
        ':id' => $userId,
    ]);
}

/**
 * Ban un user du chat (indéfini).
 */
function chat_ban_user(PDO $pdo, int $userId): void {
    $until = (new DateTimeImmutable('now'))->modify('+10 years')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE users SET chat_banned_until = :u WHERE id = :id");
    $stmt->execute([
        ':u'  => $until,
        ':id' => $userId,
    ]);
}

/**
 * Envoie une notification admin à un user.
 */
function chat_send_warning(PDO $pdo, int $targetUserId, int $adminId, string $message): bool {
    $message = trim($message);
    if ($message === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_admin_notifications (target_user_id, admin_id, message)
            VALUES (:tid, :aid, :msg)
        ");
        return $stmt->execute([
            ':tid' => $targetUserId,
            ':aid' => $adminId,
            ':msg' => $message,
        ]);
    } catch (PDOException $e) {
        // Tu peux logger si tu veux :
        // error_log('[chat_send_warning] ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les notifications non lues pour un user.
 */
function chat_fetch_unread_warnings(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT id, message, created_at
        FROM chat_admin_notifications
        WHERE target_user_id = :uid AND is_read = 0
        ORDER BY created_at ASC
    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Marque des notifications comme lues.
 */
function chat_mark_warnings_read(PDO $pdo, array $ids): void {
    if (!$ids) return;
    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique($ids));
    if (!$ids) return;

    $in = implode(',', $ids);
    $sql = "UPDATE chat_admin_notifications SET is_read = 1, read_at = NOW() WHERE id IN ($in)";
    $pdo->exec($sql);
}
