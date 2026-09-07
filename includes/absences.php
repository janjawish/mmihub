<?php
// includes/absences.php

/**
 * Retourne la liste des absences d'un utilisateur pour un semestre.
 * + info module (code, nom).
 */
function get_user_absences(PDO $pdo, int $userId, int $semesterId): array
{
    $sql = "
        SELECT a.*,
               m.code AS module_code,
               m.name AS module_name
        FROM absences a
        JOIN modules m ON m.id = a.module_id
        WHERE a.user_id = :uid
          AND a.semester_id = :sid
        ORDER BY a.absence_date DESC, a.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid' => $userId,
        ':sid' => $semesterId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Calcule le résumé des absences + les malus par module.
 *
 * Règle :
 * - Les 10 premières heures d'absences injustifiées (tous modules confondus) ne retirent aucun point.
 * - Au-delà de 10h, chaque heure injustifiée enlève 0,5 pt sur la ressource
 *   où a été enregistrée l'absence (en suivant l'ordre chronologique).
 */
function get_absence_summary(PDO $pdo, int $userId, int $semesterId): array
{
    $THRESHOLD     = 10.0; // 10h "gratuites" au global
    $PENALTY_PER_H = 0.5;  // -0,5 pt par heure injustifiée au-delà de 10h

    // On récupère TOUTES les absences du semestre, par ordre chronologique
    $stmt = $pdo->prepare("
        SELECT
            id,
            module_id,
            hours,
            justified,
            absence_date
        FROM absences
        WHERE user_id = :uid
          AND semester_id = :sid
        ORDER BY absence_date ASC, id ASC
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':sid' => $semesterId,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unjustifiedHours = 0.0;
    $justifiedHours   = 0.0;

    // Heures injustifiées par module (pour affichage)
    $perModuleHours = [];

    // Malus par module (ce qui va vraiment retirer des points)
    $penalties = [];

    // Compteur global d'heures injustifiées déjà "consommées"
    $accumUnjustified = 0.0;

    foreach ($rows as $row) {
        $hours      = (float)$row['hours'];
        $moduleId   = (int)$row['module_id'];
        $isJustified = (int)$row['justified'] === 1;

        if ($isJustified) {
            $justifiedHours += $hours;
            continue;
        }

        // Injustifiée
        $unjustifiedHours += $hours;

        if (!isset($perModuleHours[$moduleId])) {
            $perModuleHours[$moduleId] = 0.0;
        }
        $perModuleHours[$moduleId] += $hours;

        // ---- LOGIQUE DU SEUIL GLOBAL 10H ----
        // heures "gratuites" restantes avant d'atteindre le seuil
        $freeCapacity = max(0.0, $THRESHOLD - $accumUnjustified);

        // Sur cette absence, seule la partie AU-DESSUS de freeCapacity est pénalisée
        $penalizedHours = max(0.0, $hours - $freeCapacity);

        if ($penalizedHours > 0) {
            $penaltyDelta = round($penalizedHours * $PENALTY_PER_H, 2);

            if (!isset($penalties[$moduleId])) {
                $penalties[$moduleId] = 0.0;
            }
            // On cumule le malus sur ce module
            $penalties[$moduleId] = round($penalties[$moduleId] + $penaltyDelta, 2);
        }

        // On augmente le compteur global (toutes les heures injustifiées, même non pénalisées)
        $accumUnjustified += $hours;
    }

    // Heures "payantes" au total (au-dessus du seuil)
    $excessHours = max(0.0, $unjustifiedHours - $THRESHOLD);

    // Détails lisibles (texte par module)
    $details = [];
    if (!empty($penalties)) {
        $ids = implode(',', array_map('intval', array_keys($penalties)));
        $sql = "SELECT id, code, name FROM modules WHERE id IN ($ids)";
        $stmt = $pdo->query($sql);
        $modulesById = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $modulesById[(int)$m['id']] = $m;
        }

        foreach ($penalties as $mid => $penalty) {
            if (!isset($modulesById[$mid])) {
                continue;
            }

            $m = $modulesById[$mid];
            $details[] = sprintf(
                '%s : %s — −%.2f pts (%.1f h injustifiées)',
                $m['code'],
                $m['name'],
                $penalty,
                $perModuleHours[$mid] ?? 0
            );
        }
    }

    return [
        'unjustified_hours' => $unjustifiedHours,
        'justified_hours'   => $justifiedHours,
        'threshold'         => $THRESHOLD,
        'penalties'         => $penalties,      // [module_id => malus]
        'per_module_hours'  => $perModuleHours, // [module_id => heures injustifiées]
        'details'           => $details,        // lignes de texte optionnelles
        'excess_hours'      => $excessHours,    // total d'heures payantes (global)
    ];
}

