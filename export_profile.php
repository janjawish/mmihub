<?php
// export_profile.php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo); // s'assure que la personne exporte SES données

$userId = (int)$user['id'];

// Récupération des infos du user (sans le mot de passe ni les tokens sensibles)
$stmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        last_name,
        email,
        birth_date,
        but_year,
        parcours,
        formation_type,
        current_semester_id,
        current_semester_avg,
        overall_avg,
        chat_role,
        email_verified,
        created_at,
        updated_at,
        last_login_ip,
        last_login_ua,
        last_login_at,
        twofa_method,
        legal_accepted,
        legal_accepted_at
    FROM users
    WHERE id = :id
");
$stmt->execute([':id' => $userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupération du profil public (si existe)
$stmtP = $pdo->prepare("
    SELECT
        public_slug,
        headline,
        about,
        skills,
        is_anonymous,
        show_year,
        show_parcours,
        show_photo,
        created_at AS profile_created_at,
        updated_at AS profile_updated_at
    FROM profiles
    WHERE user_id = :id
    LIMIT 1
");
$stmtP->execute([':id' => $userId]);
$profileRow = $stmtP->fetch(PDO::FETCH_ASSOC) ?: [];

// Préparation téléchargement CSV
$filename = 'mmihub_profil_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// délimiteur ; pour être sympa avec Excel/LibreOffice en FR
$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Erreur lors de la génération du fichier.');
}

// Petite ligne de titre
fputcsv($out, ['MMI HUB - Export de données de profil'], ';');
fputcsv($out, ['Généré le', date('d/m/Y H:i:s')], ';');
fputcsv($out, []); // ligne vide

// Section 1 : données de compte
fputcsv($out, ['Données de compte'], ';');
fputcsv($out, ['Champ', 'Valeur'], ';');

foreach ($userRow as $field => $value) {
    // on peut renommer quelques champs pour que ce soit plus lisible
    $label = match ($field) {
        'id'                 => 'ID utilisateur',
        'first_name'         => 'Prénom',
        'last_name'          => 'Nom',
        'email'              => 'Adresse e-mail',
        'birth_date'         => 'Date de naissance',
        'but_year'           => 'Année de BUT',
        'parcours'           => 'Parcours',
        'formation_type'     => 'Type de formation',
        'current_semester_id'=> 'Semestre courant (ID)',
        'current_semester_avg'=> 'Moyenne du semestre courant',
        'overall_avg'        => 'Moyenne générale',
        'chat_role'          => 'Rôle dans le chat',
        'email_verified'     => 'E-mail vérifié',
        'created_at'         => 'Compte créé le',
        'updated_at'         => 'Compte mis à jour le',
        'last_login_ip'      => 'Dernière IP de connexion',
        'last_login_ua'      => 'Dernier user-agent',
        'last_login_at'      => 'Dernière connexion',
        'twofa_method'       => 'Méthode A2F',
        'legal_accepted'     => 'Mentions/politique acceptées',
        'legal_accepted_at'  => 'Date d’acceptation',
        default              => $field,
    };

    if ($field === 'email_verified' || $field === 'legal_accepted') {
        $value = (int)$value === 1 ? 'Oui' : 'Non';
    }

    fputcsv($out, [$label, (string)$value], ';');
}

fputcsv($out, []);

// Section 2 : profil public
fputcsv($out, ['Profil public'], ';');
fputcsv($out, ['Champ', 'Valeur'], ';');

if (!empty($profileRow)) {
    foreach ($profileRow as $field => $value) {
        $label = match ($field) {
            'public_slug'        => 'Slug public',
            'headline'           => 'Phrase d’accroche',
            'about'              => 'À propos',
            'skills'             => 'Compétences',
            'is_anonymous'       => 'Profil anonyme',
            'show_year'          => 'Afficher l’année',
            'show_parcours'      => 'Afficher le parcours',
            'show_photo'         => 'Afficher la photo',
            'profile_created_at' => 'Profil créé le',
            'profile_updated_at' => 'Profil mis à jour le',
            default              => $field,
        };

        if (in_array($field, ['is_anonymous', 'show_year', 'show_parcours', 'show_photo'], true)) {
            $value = (int)$value === 1 ? 'Oui' : 'Non';
        }

        fputcsv($out, [$label, (string)$value], ';');
    }
} else {
    fputcsv($out, ['Profil public', 'Aucun profil public configuré'], ';');
}

fclose($out);
exit;
