<?php
// export_absences.php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int)$user['id'];

$stmt = $pdo->prepare("
    SELECT
        s.code AS semester_code,
        m.code AS module_code,
        m.name AS module_name,
        a.absence_date,
        a.hours,
        a.justified,
        a.comment
    FROM absences a
    JOIN modules   m ON m.id = a.module_id
    JOIN semesters s ON s.id = a.semester_id
    WHERE a.user_id = :uid
    ORDER BY a.absence_date ASC, m.code
");
$stmt->execute([':uid' => $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'mmihub_absences_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Erreur lors de la génération du fichier.');
}

fputcsv($out, ['MMI HUB - Export des absences'], ';');
fputcsv($out, ['Généré le', date('d/m/Y H:i:s')], ';');
fputcsv($out, []);
fputcsv($out, [
    'Semestre',
    'Code module',
    'Nom du module',
    'Date de l’absence',
    'Durée (heures)',
    'Justifiée ?',
    'Commentaire'
], ';');

foreach ($rows as $r) {
    $justif = (int)$r['justified'] === 1 ? 'Oui' : 'Non';

    fputcsv($out, [
        $r['semester_code'],
        $r['module_code'],
        $r['module_name'],
        $r['absence_date'],
        $r['hours'],
        $justif,
        $r['comment'],
    ], ';');
}

fclose($out);
exit;
