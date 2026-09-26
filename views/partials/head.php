<?php
/**
 * views/partials/head.php
 * -----------------------------------------------------------------------------
 * <head> COMÚN de todos los módulos internos.
 * -----------------------------------------------------------------------------
 * Uso:
 *     $tituloPagina = 'Gestión de Rutas';
 *     $cssExtra     = ['mi-modulo.css'];      // opcional
 *     include __DIR__ . '/../views/partials/head.php';
 * -----------------------------------------------------------------------------
 */
$tituloPagina = $tituloPagina ?? basename($_SERVER['PHP_SELF'] ?? 'SGET', '.php');
$cssExtra     = $cssExtra     ?? [];

// Versión de assets: cambia al desplegar para romper la caché del navegador
$v = filemtime(Config::raiz('assets/css/01-base.css')) ?: '1';
$mv = filemtime(Config::raiz('assets/js/sget-modal.js')) ?: '1';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['sget_idioma'] ?? 'es', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>SGET · <?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?></title>

    <?php include dirname(__DIR__, 2) . '/includes/theme_init.php'; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- CSS MODULAR: cada archivo tiene una responsabilidad única -->
    <link rel="stylesheet" href="../assets/css/01-base.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/03-componentes.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/04-modales.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/05-tablas.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/06-responsive.css?v=<?= $v ?>">
    <?php foreach ($cssExtra as $css): ?>
        <link rel="stylesheet" href="../assets/css/<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>?v=<?= $v ?>">
    <?php endforeach; ?>
</head>
<body>
