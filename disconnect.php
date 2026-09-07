<?php
// charge la BDD + démarre la session
require_once __DIR__ . '/register/config.php';

// On vide toutes les données de session
$_SESSION = [];

// On supprime éventuellement le cookie de session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// On détruit la session
session_destroy();

// On renvoie l'utilisateur sur la page d'accueil
header('Location: index.php');
exit;
// charge la BDD + démarre la session
require_once __DIR__ . '/register/config.php';

// On vide toutes les données de session
$_SESSION = [];

// On supprime éventuellement le cookie de session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// On détruit la session
session_destroy();

// On renvoie l'utilisateur sur la page d'accueil
header('Location: index.php');
exit;
