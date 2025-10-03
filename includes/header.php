<?php

// Debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die("Config file not found: " . $config_file);
}

// Verifica sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/infl">Influencer Marketplace</a>
        </div>
    </nav>
    <div class="container mt-4">