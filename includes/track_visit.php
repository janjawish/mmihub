<?php
// includes/track_visit.php

// DEBUG : à mettre à false quand tout marche
const DEBUG_VISITS = false;

// Afficher les erreurs en phase debug
if (DEBUG_VISITS) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Vérifier qu'on a bien $pdo
if (!isset($pdo)) {
    if (DEBUG_VISITS) {
        echo "<p>Pas de \$pdo défini dans track_visit.php</p>";
    }
    return;
}

// Récupération des infos
$ip   = $_SERVER['REMOTE_ADDR']      ?? '';
$url  = $_SERVER['REQUEST_URI']      ?? '';
$ua   = $_SERVER['HTTP_USER_AGENT']  ?? '';
$ref  = $_SERVER['HTTP_REFERER']     ?? null;

// Si pas d'IP ou pas d'URL, on ne fait rien
if ($ip === '' || $url === '') {
    if (DEBUG_VISITS) {
        echo "<p>IP ou URL vide dans track_visit.php</p>";
    }
    return;
}

try {
    // INSERT MINIMAL : juste pour tester
    $stmt = $pdo->prepare("
        INSERT INTO visit_logs (ip, url, user_agent, referrer, created_at)
        VALUES (:ip, :url, :ua, :ref, NOW())
    ");

    $stmt->execute([
        ':ip'  => $ip,
        ':url' => $url,
        ':ua'  => $ua,
        ':ref' => $ref,
    ]);

    if (DEBUG_VISITS) {
        echo "<p>Visite loggée OK !</p>";
    }
} catch (PDOException $e) {
    // Ça va dans les logs PHP
    error_log('Visit log error: ' . $e->getMessage());

    if (DEBUG_VISITS) {
        echo "<pre>ERREUR SQL : " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    }
}
