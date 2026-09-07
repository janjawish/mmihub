<?php
// includes/semester.php

function getAcademicSlotAndYear(?DateTime $now = null): array
{
    $now = $now ?? new DateTime('now', new DateTimeZone('Europe/Paris'));

    $year  = (int)$now->format('Y');
    $month = (int)$now->format('m');

    // Année scolaire commence en septembre
    $startYear = ($month <= 8) ? $year - 1 : $year;

    // Slot 1 : 1er sept -> 31 déc
    // Slot 2 : 1er janv -> 31 août
    $startP1 = new DateTime("$startYear-09-01", new DateTimeZone('Europe/Paris'));
    $endP1   = new DateTime("$startYear-12-31", new DateTimeZone('Europe/Paris'));

    $slot = ($now >= $startP1 && $now <= $endP1) ? 1 : 2;

    return [$slot, $startYear];
}


function getCurrentSemesterNumber(int $butYear, ?DateTime $now = null): int
{
    [$slot] = getAcademicSlotAndYear($now); // 1 ou 2
    $base   = ($butYear - 1) * 2;           // BUT1 -> 0, BUT2 -> 2, BUT3 -> 4
    return $base + $slot;                   // 1..6
}

function getCurrentSemesterNumberForUser(array $user, ?DateTime $now = null): int
{
    $butYear = (int)($user['but_year'] ?? 1);
    return getCurrentSemesterNumber($butYear, $now);
}
