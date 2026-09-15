<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idioma = $_POST['idioma'] ?? 'es';
    $_SESSION['sget_idioma'] = in_array($idioma, ['es', 'en'], true) ? $idioma : 'es';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'idioma' => $_SESSION['sget_idioma'] ?? 'es'
]);
