<?php
// includes/services.php

// Mail admin pour les validations
if (!defined('SERVICES_ADMIN_EMAIL')) {
    define('SERVICES_ADMIN_EMAIL', 'jawishjan@gmail.com');
}

/**
 * Retourne le profil MMI Services de l'utilisateur (ou null).
 */
function services_get_profile_for_user(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM service_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Retourne un profil par son id.
 */
function services_get_profile_by_id(PDO $pdo, int $profileId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM service_profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Crée (ou met à jour) un profil services pour l'utilisateur.
 * $type = 'person' ou 'company'.
 * - person   -> company_status = 'none'
 * - company  -> company_status = 'pending' + mail admin
 */
function services_create_profile(PDO $pdo, int $userId, string $type): ?int {
    if (!in_array($type, ['person','company'], true)) {
        return null;
    }

    $companyStatus = ($type === 'company') ? 'pending' : 'none';

    $stmt = $pdo->prepare("
        INSERT INTO service_profiles (user_id, profile_type, company_status, public_email)
        VALUES (:uid, :pt, :cs, (SELECT email FROM users WHERE id = :uid))
        ON DUPLICATE KEY UPDATE
            profile_type   = VALUES(profile_type),
            company_status = VALUES(company_status)
    ");
    $ok = $stmt->execute([
        ':uid' => $userId,
        ':pt'  => $type,
        ':cs'  => $companyStatus,
    ]);
    if (!$ok) return null;

    $profile = services_get_profile_for_user($pdo, $userId);

    // Si création d'une entreprise → mail admin pour validation
    if ($profile && $type === 'company') {
        services_notify_admin_profile_created($profile);
    }

    return $profile ? (int)$profile['id'] : null;
}

/**
 * Met à jour un profil services.
 * Pour les entreprises, on renvoie un mail admin pour revalidation.
 */
function services_update_profile(PDO $pdo, int $profileId, int $userId, array $data): bool {
    $check = $pdo->prepare("SELECT * FROM service_profiles WHERE id = ?");
    $check->execute([$profileId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['user_id'] !== $userId) {
        return false;
    }

    $fields = [];
    $params = [':id' => $profileId];

    $allowed = [
        'company_name','siren','siret','headline','bio','avatar_path',
        'website_url','portfolio_url','phone','public_email',
        'sectors','zones','is_remote_only',
        'role_webdev','role_crea','role_photo','role_av','is_generalist'
    ];

    foreach ($allowed as $col) {
        if (array_key_exists($col, $data)) {
            $fields[] = "$col = :$col";
            $params[":$col"] = $data[$col];
        }
    }

    // Si le profil est marqué comme généraliste, on désactive les spécialisations
    if (!empty($data['is_generalist'])) {
        $fields[] = "role_webdev = 0";
        $fields[] = "role_crea = 0";
        $fields[] = "role_photo = 0";
        $fields[] = "role_av = 0";
    }

    if (!$fields) return true;

    $sql = "UPDATE service_profiles SET " . implode(',', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);

    // Si profil entreprise → notifier l’admin pour revalider
    if ($ok && $row['profile_type'] === 'company') {
        services_notify_admin_profile_changed($row, $data);
    }

    return $ok;
}

/**
 * Crée une annonce.
 * Règles :
 *  - profil_type = 'company'  -> annonce en ligne directe (online), pas de mail
 *  - profil_type = 'person'   -> statut 'pending' + mail admin
 */
function services_create_offer(PDO $pdo, int $profileId, int $userId, array $data): ?int {
    $p = services_get_profile_by_id($pdo, $profileId);
    if (!$p || (int)$p['user_id'] !== $userId) {
        return null;
    }

    $title       = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $price       = isset($data['price_cents']) ? (int)$data['price_cents'] : 0;
    $isPriceOn   = !empty($data['is_price_enabled']);

    if ($title === '' || $description === '') {
        return null;
    }

    // Seules les entreprises validées peuvent afficher un prix
    $canPrice = ($p['profile_type'] === 'company' && $p['company_status'] === 'approved');
    if (!$canPrice) {
        $isPriceOn = 0;
        $price = 0;
    }

    // Entreprise → en ligne directe ; Particulier → attente validation admin
    $status = ($p['profile_type'] === 'company') ? 'online' : 'pending';

    $stmt = $pdo->prepare("
        INSERT INTO service_offers (profile_id, title, description, base_price_cents, is_price_enabled, status)
        VALUES (:pid, :t, :d, :pr, :ipe, :st)
    ");
    $ok = $stmt->execute([
        ':pid' => $profileId,
        ':t'   => $title,
        ':d'   => $description,
        ':pr'  => $isPriceOn ? max(0, $price) : null,
        ':ipe' => $isPriceOn ? 1 : 0,
        ':st'  => $status,
    ]);
    if (!$ok) return null;

    $offerId = (int)$pdo->lastInsertId();

    // Image principale (si tu ajoutes l’upload)
    if (!empty($data['image_path'])) {
        $imgStmt = $pdo->prepare("
            INSERT INTO service_offer_images (offer_id, file_path, position)
            VALUES (:oid, :fp, 1)
        ");
        $imgStmt->execute([
            ':oid' => $offerId,
            ':fp'  => $data['image_path'],
        ]);
    }

    // Particulier → notifier l’admin pour validation de l’annonce
    if ($p['profile_type'] === 'person') {
        services_notify_admin_offer_pending($offerId, $title, $p);
    }

    return $offerId;
}

/**
 * Récupère une annonce avec profil + images.
 */
function services_get_offer(PDO $pdo, int $offerId): ?array {
    $stmt = $pdo->prepare("
        SELECT o.*, sp.profile_type, sp.company_status,
               sp.company_name, sp.headline, sp.avatar_path,
               sp.phone, sp.public_email, sp.user_id
        FROM service_offers o
        JOIN service_profiles sp ON o.profile_id = sp.id
        WHERE o.id = ?
    ");
    $stmt->execute([$offerId]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$offer) return null;

    $iStmt = $pdo->prepare("
        SELECT * FROM service_offer_images
        WHERE offer_id = ?
        ORDER BY position ASC, id ASC
    ");
    $iStmt->execute([$offerId]);
    $offer['images'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    return $offer;
}

/**
 * Liste des annonces pour le fil.
 * (celles en ligne et actives, avec une éventuelle miniature)
 */
function services_list_feed(PDO $pdo, int $limit = 30): array {
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            sp.profile_type,
            sp.company_status,
            sp.company_name,
            sp.headline,
            sp.avatar_path
        FROM service_offers o
        JOIN service_profiles sp ON o.profile_id = sp.id
        WHERE o.is_active = 1
          AND o.status = 'online'
        ORDER BY (o.contact_clicks * 3 + o.views_count) DESC, o.created_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$offers) return [];

    $ids = array_column($offers, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $iStmt = $pdo->prepare("
        SELECT offer_id, file_path
        FROM service_offer_images
        WHERE offer_id IN ($in)
        ORDER BY offer_id, position ASC, id ASC
    ");
    $iStmt->execute($ids);
    $imgs = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    $thumbs = [];
    foreach ($imgs as $img) {
        $oid = (int)$img['offer_id'];
        if (!isset($thumbs[$oid])) {
            $thumbs[$oid] = $img['file_path'];
        }
    }

    foreach ($offers as &$o) {
        $id = (int)$o['id'];
        $o['thumb'] = $thumbs[$id] ?? null;
    }
    unset($o);

    return $offers;
}

/**
 * Statistiques vues / contacts.
 * 1 compte = 1 vue max par annonce.
 */
function services_increment_view(PDO $pdo, int $offerId, ?int $userId): void
{
    // On ne compte que les utilisateurs connectés
    if (!$userId) {
        return;
    }

    // On essaie d'insérer (offer_id, user_id).
    // Si la ligne existe déjà => aucune ligne affectée.
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO service_offer_views (offer_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$offerId, $userId]);

    // Si rowCount() > 0 => c'est la 1ère fois que ce user voit cette annonce
    if ($stmt->rowCount() > 0) {
        $up = $pdo->prepare("
            UPDATE service_offers
            SET views_count = views_count + 1
            WHERE id = ?
        ");
        $up->execute([$offerId]);
    }
}

/**
 * 1 compte = 1 clic contact max par annonce.
 */
function services_increment_contact_click(PDO $pdo, int $offerId, ?int $userId): void
{
    if (!$userId) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO service_offer_contacts (offer_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$offerId, $userId]);

    if ($stmt->rowCount() > 0) {
        $up = $pdo->prepare("
            UPDATE service_offers
            SET contact_clicks = contact_clicks + 1
            WHERE id = ?
        ");
        $up->execute([$offerId]);
    }
}


/**
 * Badge selon type de profil.
 */
function services_badge_for_profile(array $p): array {
    if ($p['profile_type'] === 'company' && $p['company_status'] === 'approved') {
        return ['Entreprise', 'badge-entreprise'];
    }
    if ($p['profile_type'] === 'company' && $p['company_status'] !== 'approved') {
        return ['Entreprise (à valider)', 'badge-entreprise-pending'];
    }
    return ['Particulier', 'badge-particulier'];
}

/* =========================
   ️  EMAILS ADMIN
   ========================= */

/**
 * Helper mailer pour MMI Services.
 * Reprend la config SMTP de config.php (PHPMailer).
 */
function services_build_mailer(): ?\PHPMailer\PHPMailer\PHPMailer {
    try {
        $mail = createBaseMailer();
        $mail->setFrom(app_env('SMTP_FROM_EMAIL', app_env('SMTP_USERNAME')), 'MMI HUB – Services');

        return $mail;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Mail admin : profil entreprise créé.
 */
function services_notify_admin_profile_created(array $profileRow): void {
    $mail = services_build_mailer();
    if (!$mail) return;

    try {
        $mail->addAddress(SERVICES_ADMIN_EMAIL);

        $mail->isHTML(true);
        $mail->Subject = 'MMI SERVICES – Nouveau profil entreprise à valider';

        $userId      = (int)$profileRow['user_id'];
        $companyName = $profileRow['company_name'] ?? '';
        $siren       = $profileRow['siren'] ?? '';
        $siret       = $profileRow['siret'] ?? '';

        $mail->Body = '
            <p>Nouveau <strong>profil entreprise</strong> créé sur MMI Services.</p>
            <ul>
              <li>User ID : <strong>' . htmlspecialchars((string)$userId) . '</strong></li>
              <li>Nom d\'entreprise : <strong>' . htmlspecialchars($companyName) . '</strong></li>
              <li>SIREN : ' . htmlspecialchars($siren) . '</li>
              <li>SIRET : ' . htmlspecialchars($siret) . '</li>
            </ul>
            <p>Va dans l\'interface d\'admin / ton phpMyAdmin pour vérifier les infos et passer le statut à <code>approved</code> si tout est OK.</p>
        ';

        $mail->AltBody =
            "Nouveau profil entreprise sur MMI Services.\n" .
            "User ID : $userId\n" .
            "Nom : $companyName\n" .
            "SIREN : $siren\nSIRET : $siret\n";

        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // silencieux
    }
}

/**
 * Mail admin : profil entreprise modifié (à revalider).
 */
function services_notify_admin_profile_changed(array $oldProfileRow, array $data): void {
    $mail = services_build_mailer();
    if (!$mail) return;

    try {
        $mail->addAddress(SERVICES_ADMIN_EMAIL);

        $mail->isHTML(true);
        $mail->Subject = 'MMI SERVICES – Profil entreprise modifié';

        $userId      = (int)$oldProfileRow['user_id'];
        $companyName = $data['company_name'] ?? $oldProfileRow['company_name'] ?? '';
        $siren       = $data['siren'] ?? $oldProfileRow['siren'] ?? '';
        $siret       = $data['siret'] ?? $oldProfileRow['siret'] ?? '';

        $mail->Body = '
            <p>Un <strong>profil entreprise</strong> a été modifié sur MMI Services.</p>
            <ul>
              <li>User ID : <strong>' . htmlspecialchars((string)$userId) . '</strong></li>
              <li>Nom d\'entreprise : <strong>' . htmlspecialchars($companyName) . '</strong></li>
              <li>SIREN : ' . htmlspecialchars($siren) . '</li>
              <li>SIRET : ' . htmlspecialchars($siret) . '</li>
            </ul>
            <p>Vérifie le profil et mets à jour <code>company_status</code> si nécessaire.</p>
        ';

        $mail->AltBody =
            "Profil entreprise modifié sur MMI Services.\n" .
            "User ID : $userId\n" .
            "Nom : $companyName\n" .
            "SIREN : $siren\nSIRET : $siret\n";

        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // silencieux
    }
}

/**
 * Mail admin : nouvelle annonce de particulier à valider.
 */
function services_notify_admin_offer_pending(int $offerId, string $title, array $profileRow): void {
    $mail = services_build_mailer();
    if (!$mail) return;

    try {
        $mail->addAddress(SERVICES_ADMIN_EMAIL);

        $mail->isHTML(true);
        $mail->Subject = 'MMI SERVICES – Nouvelle annonce à valider';

        $userId   = (int)$profileRow['user_id'];
        $headline = $profileRow['headline'] ?? '';

        $mail->Body = '
            <p>Nouvelle <strong>annonce de particulier</strong> à valider sur MMI Services.</p>
            <ul>
              <li>Offer ID : <strong>' . htmlspecialchars((string)$offerId) . '</strong></li>
              <li>Titre : <strong>' . htmlspecialchars($title) . '</strong></li>
              <li>User ID : <strong>' . htmlspecialchars((string)$userId) . '</strong></li>
              <li>Headline profil : ' . htmlspecialchars($headline) . '</li>
            </ul>
            <p>Va dans phpMyAdmin / ton outil d’admin pour passer le statut de l’annonce à <code>online</code> ou la refuser.</p>
        ';

        $mail->AltBody =
            "Nouvelle annonce à valider sur MMI Services.\n" .
            "Offer ID : $offerId\n" .
            "Titre : $title\n" .
            "User ID : $userId\n";

        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // silencieux
    }
}
