<?php
// includes/mmi_edt.php

/**
 * Helper compatible PHP 7 pour vérifier la fin d'une chaîne.
 */
function mmi_edt_str_ends_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    $lenHaystack = strlen($haystack);
    $lenNeedle   = strlen($needle);
    if ($lenNeedle > $lenHaystack) {
        return false;
    }
    return substr($haystack, -$lenNeedle) === $needle;
}

/**
 * Récupère les réglages EDT (lien iCal) pour un utilisateur.
 */
function mmi_edt_get_settings(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM user_timetables
        WHERE user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Sauvegarde / met à jour le lien iCal pour un utilisateur.
 */
function mmi_edt_save_settings(PDO $pdo, int $userId, string $icalUrl): void
{
    $stmt = $pdo->prepare("
        INSERT INTO user_timetables (user_id, ical_url, created_at)
        VALUES (:uid, :url, NOW())
        ON DUPLICATE KEY UPDATE
            ical_url   = VALUES(ical_url),
            updated_at = NOW()
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':url' => $icalUrl,
    ]);
}

/**
 * Va chercher l'ICS sur l’URL ADE et renvoie un tableau d’événements normalisés.
 */
function mmi_edt_fetch_events_from_url(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException("URL d'emploi du temps invalide.");
    }

    $context = stream_context_create([
        'http' => [
            'timeout'     => 6,
            'user_agent'  => 'MMIHub-EDT/1.0',
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    if ($raw === false || $raw === '') {
        throw new RuntimeException("Impossible de récupérer le flux iCal (ADE).");
    }

    return mmi_edt_parse_ical($raw);
}

/**
 * Unfold + parse rapide du contenu iCal en tableau d’événements.
 */
function mmi_edt_parse_ical(string $icalContent): array
{
    // On "déplie" les lignes (RFC 5545 : continuité par espace ou tabulation).
    $unfolded = preg_replace("/\r\n[ \t]/", '', $icalContent);
    $lines    = preg_split("/\r\n|\n|\r/", trim($unfolded));

    $events       = [];
    $currentEvent = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if ($line === 'BEGIN:VEVENT') {
            $currentEvent = [];
            continue;
        }

        if ($line === 'END:VEVENT') {
            if ($currentEvent !== null) {
                $events[] = mmi_edt_normalize_event($currentEvent);
            }
            $currentEvent = null;
            continue;
        }

        if ($currentEvent === null) {
            continue;
        }

        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }

        $rawKey = substr($line, 0, $pos);
        $value  = substr($line, $pos + 1);

        // On supprime les éventuels paramètres après ";"
        $key = strtoupper(preg_replace('/;.*$/', '', $rawKey));

        switch ($key) {
            case 'DTSTART':
            case 'DTEND':
            case 'SUMMARY':
            case 'LOCATION':
            case 'DESCRIPTION':
                $currentEvent[$key] = $value;
                break;
        }
    }

    // On vire les événements sans date de début
    $events = array_filter($events, function ($e) {
        return isset($e['start']) && $e['start'] instanceof DateTimeInterface;
    });

    // Tri chronologique
    usort($events, function (array $a, array $b) {
        return $a['start'] <=> $b['start'];
    });

    return $events;
}

/**
 * Normalise un évènement brut en structure plus simple.
 */
function mmi_edt_normalize_event(array $raw): array
{
    $start = isset($raw['DTSTART']) ? mmi_edt_parse_ical_datetime($raw['DTSTART']) : null;
    $end   = isset($raw['DTEND'])   ? mmi_edt_parse_ical_datetime($raw['DTEND'])   : null;

    // On nettoie la description : \n --> vraie nouvelle ligne
    $descriptionRaw = isset($raw['DESCRIPTION']) ? $raw['DESCRIPTION'] : '';
    $descriptionRaw = str_replace('\\n', "\n", $descriptionRaw);

    return [
        'start'       => $start,
        'end'         => $end,
        'summary'     => trim($raw['SUMMARY']     ?? 'Cours'),
        'location'    => trim($raw['LOCATION']    ?? ''),
        'description' => trim($descriptionRaw),
    ];
}

/**
 * Parse une date iCal (ex: 20251203T073000Z) en DateTimeImmutable Europe/Paris.
 * Si la date se termine par "Z", on la considère comme UTC puis on convertit en Europe/Paris.
 */
function mmi_edt_parse_ical_datetime(string $value): ?DateTimeImmutable
{
    $value = trim($value);

    // Cas UTC avec "Z" à la fin
    if (mmi_edt_str_ends_with($value, 'Z')) {
        $valueNoZ = substr($value, 0, -1);
        $dt       = DateTimeImmutable::createFromFormat('Ymd\THis', $valueNoZ, new DateTimeZone('UTC'));
        if ($dt instanceof DateTimeImmutable) {
            return $dt->setTimezone(new DateTimeZone('Europe/Paris'));
        }
    }

    // Sinon on tente en heure locale (Europe/Paris)
    $tzParis  = new DateTimeZone('Europe/Paris');
    $patterns = [
        'Ymd\THis',
        'Ymd',
    ];

    foreach ($patterns as $pattern) {
        $dt = DateTimeImmutable::createFromFormat($pattern, $value, $tzParis);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }

    try {
        return new DateTimeImmutable($value, $tzParis);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Regroupe les événements par semaine ISO + jour.
 *
 * Retourne structure:
 * [
 *   '2025-W49' => [
 *      'year'  => 2025,
 *      'week'  => 49,
 *      'start' => DateTimeImmutable (lundi),
 *      'days'  => [
 *          '2025-12-01' => [events...],
 *          ...
 *      ]
 *   ],
 *   ...
 * ]
 */
function mmi_edt_group_events_by_week_and_day(array $events): array
{
    $weeks = [];

    foreach ($events as $event) {
        $start = $event['start'] ?? null;
        if (!$start instanceof DateTimeInterface) {
            continue;
        }

        $isoYear   = (int) $start->format('o');
        $isoWeek   = (int) $start->format('W');
        $weekKey   = sprintf('%d-W%02d', $isoYear, $isoWeek);
        $dayKey    = $start->format('Y-m-d');

        if (!isset($weeks[$weekKey])) {
            // Lundi de la semaine ISO courante
            $monday = (new DateTimeImmutable($start->format('Y-m-d H:i:s'), $start->getTimezone()))
                ->modify('Monday this week');

            $weeks[$weekKey] = [
                'year'  => $isoYear,
                'week'  => $isoWeek,
                'start' => $monday,
                'days'  => [],
            ];
        }

        if (!isset($weeks[$weekKey]['days'][$dayKey])) {
            $weeks[$weekKey]['days'][$dayKey] = [];
        }

        $weeks[$weekKey]['days'][$dayKey][] = $event;
    }

    // Tri des semaines par date de début
    uasort($weeks, function (array $a, array $b) {
        return $a['start'] <=> $b['start'];
    });

    // Tri des jours + événements dans chaque semaine
    foreach ($weeks as &$week) {
        ksort($week['days']);
        foreach ($week['days'] as &$dayEvents) {
            usort($dayEvents, function (array $a, array $b) {
                return $a['start'] <=> $b['start'];
            });
        }
        unset($dayEvents);
    }
    unset($week);

    return $weeks;
}

/**
 * Extrait salle + prof à partir d'un évènement.
 * - salle --> location
 * - prof  --> dernière ligne non vide de description, en ignorant "Exporté le..."
 */
function mmi_edt_extract_room_and_teacher(array $event): array
{
    $room = trim($event['location'] ?? '');
    $teacher = '';

    $desc = trim($event['description'] ?? '');
    if ($desc !== '') {
        // On découpe en lignes
        $lines = preg_split('/\r\n|\n|\r/', $desc);
        $clean = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // On ignore les lignes de type "Exporté le..."
            if (stripos($line, 'export') !== false) {
                continue;
            }
            $clean[] = $line;
        }

        if (!empty($clean)) {
            // On prend la dernière ligne comme prof
            $teacher = end($clean);
        }
    }

    return [$room, $teacher];
}
