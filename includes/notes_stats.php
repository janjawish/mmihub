<?php
// includes/notes_stats.php

/**
 * Convertit une note en statut couleur.
 */
function grade_status(?float $grade): string
{
    if ($grade === null) {
        return 'warn';     // pas encore évalué
    }
    if ($grade < 8.0) {
        return 'fail';
    }
    if ($grade < 10.0) {
        return 'warn';
    }
    return 'ok';
}

/**
 * Calcule stats UE / compétences / globales pour un semestre.
 *
 * @param array $maquette            lignes de loadSemesterMaquetteForUser
 * @param array $gradesByModule      [module_id => ['final_grade'=>...]]
 * @param array $penaltiesByModule   [module_id => malus_points] (absences)
 */
function computeSemesterStats(
    int $semesterId,
    array $maquette,
    array $gradesByModule,
    array $penaltiesByModule = []
): array {

    // 1) Déterminer dynamiquement quelles compétences existent dans la maquette
    $competences = [];
    foreach ($maquette as $row) {
        if (!empty($row['competence'])) {
            $code = $row['competence']; // C1..C5
            if (!isset($competences[$code])) {
                $competences[$code] = [
                    'sum'  => 0.0,
                    'coef' => 0.0,
                    'min'  => null,
                ];
            }
        }
    }

    // Sécurité : si jamais la maquette est vide, on retombe sur C1..C5
    if (empty($competences)) {
        foreach (['C1','C2','C3','C4','C5'] as $c) {
            $competences[$c] = ['sum' => 0.0, 'coef' => 0.0, 'min' => null];
        }
    }

    $sumGlobal      = 0.0;
    $coefGlobal     = 0.0;
    $hasEliminatory = false;
    $modulesList    = [];

    foreach ($maquette as $row) {
        $mid = (int)$row['module_id'];

        // Note agrégée (avant malus)
        $rawGrade = null;
        if (isset($gradesByModule[$mid]) && $gradesByModule[$mid]['final_grade'] !== null) {
            $rawGrade = (float)$gradesByModule[$mid]['final_grade'];
        }

        // Malus d'absences pour ce module
        $penalty = isset($penaltiesByModule[$mid]) ? (float)$penaltiesByModule[$mid] : 0.0;

        $coef = (float)$row['coef'];
        $comp = $row['competence'] ?? null; // ex: "C3"

        // Note effective = note - malus (jamais en dessous de 0)
        $effectiveGrade = $rawGrade;
        if ($rawGrade !== null && $penalty > 0) {
            $effectiveGrade = max(0.0, $rawGrade - $penalty);
        }

        // Pour le tableau "Ressources / SAÉ"
        $modulesList[] = [
            'ue_code'     => $row['ue_code'],
            'module_code' => $row['module_code'],
            'module_name' => $row['module_name'],
            'module_type' => $row['module_type'],
            'coef'        => $coef,
            'grade'       => $effectiveGrade, // APRÈS malus
            'raw_grade'   => $rawGrade,       // AVANT malus
            'penalty'     => $penalty,        // points retirés
        ];

        if ($effectiveGrade === null || $coef <= 0) {
            continue;
        }

        // Moyenne globale semestre
        $sumGlobal  += $effectiveGrade * $coef;
        $coefGlobal += $coef;

        // Moyenne par compétence
        if ($comp && isset($competences[$comp])) {
            $competences[$comp]['sum']  += $effectiveGrade * $coef;
            $competences[$comp]['coef'] += $coef;

            if (
                $competences[$comp]['min'] === null
                || $effectiveGrade < $competences[$comp]['min']
            ) {
                $competences[$comp]['min'] = $effectiveGrade;
            }
        }

        // Note éliminatoire < 8 sur ce module
        if ($effectiveGrade < 8.0) {
            $hasEliminatory = true;
        }
    }

    $avgGlobal = $coefGlobal > 0 ? $sumGlobal / $coefGlobal : null;

    $compOut    = [];
    $validCount = 0;
    $totalComps = count($competences);

    foreach ($competences as $code => $c) {
        $avg    = $c['coef'] > 0 ? $c['sum'] / $c['coef'] : null;
        $status = grade_status($avg);

        // si une note < 8 dans la compétence -> échec de la compétence
        if ($c['min'] !== null && $c['min'] < 8.0) {
            $status = 'fail';
        }

        if ($status === 'ok') {
            $validCount++;
        }

        $compOut[$code] = [
            'avg'    => $avg,
            'status' => $status,
        ];
    }

    // Semestre validé : moyenne ≥ 10 ET toutes les compétences présentes sont "ok"
// === RÈGLE MCC PERSONNALISÉE ===

// Compte des compétences >= 10
$competencesValidees = 0;
$competenceEliminatoire = false;

foreach ($compOut as $c) {
    if ($c['avg'] !== null) {
        if ($c['avg'] >= 10) $competencesValidees++;
        if ($c['avg'] < 8)   $competenceEliminatoire = true;
    }
}

// === RÈGLES PAR SEMESTRE ===
$isS5S6 = ($semesterId == 5 || $semesterId == 6);

if ($isS5S6) {
    // S5 & S6 : on doit valider TOUTES les compétences (il n'y en a que 2)
    $semesterValid = (!$competenceEliminatoire 
                      && $competencesValidees === $totalComps);
} else {
    // S1–S4 : au moins 3 compétences validées
    $semesterValid = (!$competenceEliminatoire 
                      && $competencesValidees >= 3);
}


    return [
        'avg_global'       => $avgGlobal,
        'competences'      => $compOut,        // tableau dynamique (2 ou 5 clés)
        'competences_ok' => $competencesValidees,
        'has_eliminatory'  => $hasEliminatory,
        // on force 0/1 pour MySQL (pas de booléen / chaîne vide)
        'semester_valid'   => $semesterValid ? 1 : 0,
        'modules'          => $modulesList,
    ];
}

/**
 * Sauvegarde les stats dans user_semester_stats + met à jour users.
 */
function saveSemesterStats(PDO $pdo, int $userId, int $semesterId, array $stats): void
{
    $comp = $stats['competences'];

    // on normalise au cas où
    $semesterValid = !empty($stats['semester_valid']) ? 1 : 0;

    $sql = "
      INSERT INTO user_semester_stats
        (user_id, semester_id,
         avg_global, avg_c1, avg_c2, avg_c3, avg_c4, avg_c5,
         status_c1, status_c2, status_c3, status_c4, status_c5,
         competences_validated, has_eliminatory, semester_valid)
      VALUES
        (:uid, :sid,
         :g, :c1, :c2, :c3, :c4, :c5,
         :s1, :s2, :s3, :s4, :s5,
         :ok, :elim, :valid)
      ON DUPLICATE KEY UPDATE
         avg_global = VALUES(avg_global),
         avg_c1 = VALUES(avg_c1),
         avg_c2 = VALUES(avg_c2),
         avg_c3 = VALUES(avg_c3),
         avg_c4 = VALUES(avg_c4),
         avg_c5 = VALUES(avg_c5),
         status_c1 = VALUES(status_c1),
         status_c2 = VALUES(status_c2),
         status_c3 = VALUES(status_c3),
         status_c4 = VALUES(status_c4),
         status_c5 = VALUES(status_c5),
         competences_validated = VALUES(competences_validated),
         has_eliminatory = VALUES(has_eliminatory),
         semester_valid = VALUES(semester_valid)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'   => $userId,
        ':sid'   => $semesterId,
        ':g'     => $stats['avg_global'],
        ':c1'    => $comp['C1']['avg'] ?? null,
        ':c2'    => $comp['C2']['avg'] ?? null,
        ':c3'    => $comp['C3']['avg'] ?? null,
        ':c4'    => $comp['C4']['avg'] ?? null,
        ':c5'    => $comp['C5']['avg'] ?? null,
        ':s1'    => $comp['C1']['status'] ?? 'warn',
        ':s2'    => $comp['C2']['status'] ?? 'warn',
        ':s3'    => $comp['C3']['status'] ?? 'warn',
        ':s4'    => $comp['C4']['status'] ?? 'warn',
        ':s5'    => $comp['C5']['status'] ?? 'warn',
        ':ok'    => (int) $stats['competences_ok'],
        ':elim'  => $stats['has_eliminatory'] ? 1 : 0,
        ':valid' => $semesterValid,
    ]);

    // maj users.current_semester_id + current_semester_avg
    $pdo->prepare("
        UPDATE users
        SET current_semester_id = :sid,
            current_semester_avg = :g
        WHERE id = :uid
    ")->execute([
        ':sid' => $semesterId,
        ':g'   => $stats['avg_global'],
        ':uid' => $userId,
    ]);

    // overall_avg = moyenne des avg_global de tous ses semestres
    $st = $pdo->prepare("
        SELECT AVG(avg_global) AS g
        FROM user_semester_stats
        WHERE user_id = :uid AND avg_global IS NOT NULL
    ");
    $st->execute([':uid' => $userId]);
    $overall = $st->fetchColumn();

    $pdo->prepare("UPDATE users SET overall_avg = :g WHERE id = :uid")
        ->execute([':g' => $overall, ':uid' => $userId]);
}
