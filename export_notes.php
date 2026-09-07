<?php
// export_notes.php
require_once __DIR__ . '/register/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int)$user['id'];

// On exporte TOUTES les notes de tous les semestres
$stmt = $pdo->prepare("
    SELECT
        s.code AS semester_code,
        m.code AS module_code,
        m.name AS module_name,
        mg.note_ds,
        mg.note_tp,
        mg.final_grade
    FROM module_grades mg
    JOIN modules   m ON m.id = mg.module_id
    JOIN semesters s ON s.id = mg.semester_id
    WHERE mg.user_id = :uid
    ORDER BY mg.semester_id, m.code
");
$stmt->execute([':uid' => $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'mmihub_notes_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Erreur lors de la génération du fichier.');
}

fputcsv($out, ['MMI HUB - Export de notes'], ';');
fputcsv($out, ['Généré le', date('d/m/Y H:i:s')], ';');
fputcsv($out, []);
fputcsv($out, ['Semestre', 'Code module', 'Nom du module', 'Note DS', 'Note TP', 'Note finale'], ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['semester_code'],
        $r['module_code'],
        $r['module_name'],
        $r['note_ds'],
        $r['note_tp'],
        $r['final_grade'],
    ], ';');
}

fclose($out);
exit;
