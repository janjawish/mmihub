<?php
require_once __DIR__ . '/register/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

require_once __DIR__ . '/includes/chat.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

switch ($action) {

    // -------------------------------------------------------------------------
    // FETCH : nouveaux messages + notifications admin
    // -------------------------------------------------------------------------
    case 'fetch':
        $sinceId = (int)($_GET['since_id'] ?? 0);

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
              AND m.id > :since_id
            ORDER BY m.id ASC
            LIMIT 100
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':since_id' => $sinceId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $messages   = [];
        $messageIds = [];

        // Pré-structuration des messages
        foreach ($rows as $row) {
            $mid = (int)$row['id'];
            $messageIds[] = $mid;
            $messages[$mid] = [
                'id'           => $mid,
                'user_id'      => (int)$row['user_id'],
                'content'      => $row['content_filtered'],
                'parent_id'    => $row['parent_id'] ? (int)$row['parent_id'] : null,
                'created_at'   => $row['created_at'],
                'display_name' => chat_display_name($row),
                'avatar_url'   => chat_avatar_url($row),
                'public_slug'  => $row['public_slug'] ?? null,
                'reactions'    => [],
                'parent'       => null,
            ];
        }

        // Réactions
        if ($messageIds) {
            $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
            $rStmt = $pdo->prepare("
                SELECT message_id, emoji, COUNT(*) AS nb
                FROM chat_message_reactions
                WHERE message_id IN ($placeholders)
                GROUP BY message_id, emoji
            ");
            $rStmt->execute($messageIds);
            $rea = $rStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rea as $r) {
                $mid = (int)$r['message_id'];
                if (!isset($messages[$mid])) {
                    continue;
                }
                $messages[$mid]['reactions'][] = [
                    'emoji' => $r['emoji'],
                    'count' => (int)$r['nb'],
                ];
            }

            // Parents (pour l'aperçu des réponses)
            $parentIds = array_filter(
                array_unique(
                    array_map(function ($m) {
                        return $m['parent_id'] ?? null;
                    }, $messages)
                )
            );

            if ($parentIds) {
                $placeholdersP = implode(',', array_fill(0, count($parentIds), '?'));
                $pStmt = $pdo->prepare("
                    SELECT m.id, m.content_filtered,
                           u.first_name, u.last_name
                    FROM chat_messages m
                    JOIN users u ON m.user_id = u.id
                    WHERE m.id IN ($placeholdersP)
                ");
                $pStmt->execute($parentIds);
                $parentsRows = $pStmt->fetchAll(PDO::FETCH_ASSOC);

                $parents = [];
                foreach ($parentsRows as $p) {
                    $parents[(int)$p['id']] = $p;
                }

                foreach ($messages as &$m) {
                    if (!empty($m['parent_id']) && isset($parents[$m['parent_id']])) {
                        $pm = $parents[$m['parent_id']];
                        $m['parent'] = [
                            'id'      => (int)$pm['id'],
                            'author'  => $pm['first_name'] . ' ' . mb_substr($pm['last_name'], 0, 1, 'UTF-8') . '.',
                            'content' => $pm['content_filtered'],
                        ];
                    }
                }
                unset($m);
            }
        }

        // Notifications admin non lues
        $warnings = chat_fetch_unread_warnings($pdo, $userId);
        if ($warnings) {
            $ids = array_column($warnings, 'id');
            chat_mark_warnings_read($pdo, $ids);
        }

        echo json_encode([
            'ok'       => true,
            'messages' => array_values($messages),
            'warnings' => $warnings,
        ], JSON_UNESCAPED_UNICODE);
        break;

    // -------------------------------------------------------------------------
    // REACT : toggle d'une réaction émoji
    // -------------------------------------------------------------------------
    case 'react':
        $messageId = (int)($_POST['message_id'] ?? 0);
        $emoji     = $_POST['emoji'] ?? '';

        if (!$messageId || $emoji === '') {
            echo json_encode(['ok' => false, 'error' => 'invalid'], JSON_UNESCAPED_UNICODE);
            break;
        }

        chat_toggle_reaction($pdo, $user, $messageId, $emoji);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        break;

    // -------------------------------------------------------------------------
    default:
        echo json_encode(['ok' => false, 'error' => 'unknown_action'], JSON_UNESCAPED_UNICODE);
        break;
}
