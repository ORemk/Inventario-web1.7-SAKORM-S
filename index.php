<?php
// index.php - simple redirect fallback to the deploy-SAKORMS subfolder
// Place this file in C:/xampp/htdocs/Sakorms.org/deploy-SAKORMS/index.php

// Requested URI (e.g. /Sakorms.org/login_admin_skm.html)
$req = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

// Remove leading /Sakorms.org/ if present
$path = preg_replace('#^/Sakorms\.org/#','',$req);

// If requesting the root, redirect to the login page
if ($path === '' || $path === '/' || $path === false) {
    header('Location: /Sakorms.org/deploy-SAKORMS/login.html');
    exit;
}

// If the request is already for deploy-SAKORMS, serve the project's login page
// to ensure users land on the authentication screen.
if ($path === '/deploy-SAKORMS' || $path === '/deploy-SAKORMS/') {
    $localLogin = __DIR__ . DIRECTORY_SEPARATOR . 'login.html';
    if (file_exists($localLogin)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($localLogin);
        exit;
    }
    // Fallback: if no login.html, serve index.html or a simple message
    $localIndex = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';
    if (file_exists($localIndex)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($localIndex);
        exit;
    }
    http_response_code(200);
    echo 'deploy-SAKORMS: login/index not found.';
    exit;
}

// Redirect any other path to the same path under Sakorms.org
header('Location: /Sakorms.org' . $path);
exit;
