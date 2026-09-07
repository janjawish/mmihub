<?php
// includes/maquette_loader.php
require_once __DIR__ . '/semester.php';

/**
 * Charge la maquette (UE + modules + coef) pour un user donné.
 */
function loadSemesterMaquetteForUser(PDO $pdo, array $user, ?int $semesterId = null): array
{
    if ($semesterId === null) {
        $semesterId = getCurrentSemesterNumberForUser($user);
    }

    $parcours  = $user['parcours'] ?? 'none';
    $formation = $user['formation_type'] ?? 'none';

    $sql = "
        SELECT
            m.id   AS module_id,
            m.code AS module_code,
            m.name AS module_name,
            m.type AS module_type,
            u.id   AS ue_id,
            u.code AS ue_code,
            u.name AS ue_name,
            u.competence,
            u.ects,
            um.coef
        FROM ue_modules um
        JOIN modules m ON m.id = um.module_id
        JOIN ues     u ON u.id = um.ue_id
        WHERE u.semester_id = :semester
          AND (um.parcours = 'none' OR um.parcours = :parcours)
          AND (um.formation_type = 'none' OR um.formation_type = :formation)
        ORDER BY u.code, m.code
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':semester'  => $semesterId,
        ':parcours'  => $parcours,
        ':formation' => $formation,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
