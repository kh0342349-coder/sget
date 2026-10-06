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
$v  = max(
    (int)(filemtime(Config::raiz('assets/css/01-base.css')) ?: 1),
    (int)(filemtime(Config::raiz('assets/css/02-layout.css')) ?: 1)
);
$mv = filemtime(Config::raiz('assets/js/sget-modal.js')) ?: '1';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['sget_idioma'] ?? 'es', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>SGET · <?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Tema: debe ejecutarse antes del primer pintado, sin defer. -->
    <script src="<?= Config::basePath() ?>/assets/js/theme-init.js?v=<?= filemtime(Config::raiz('assets/js/theme-init.js')) ?: '1' ?>"></script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!--
        CSS MODULAR · un archivo, una responsabilidad.
        Si no sabes dónde está un estilo, esta lista es el mapa:
            01-base          variables, reset, tipografía, modo oscuro
            02-layout        sidebar, cabecera, rejillas, toolbar
            03-componentes   botones, badges, formularios, toasts
            04-modales       overlays, modales, drawers, confirmaciones
            05-tablas        tabla de datos + modo tarjeta en móvil
            06-responsive    breakpoints, táctil, impresión
            07-transiciones  entrada/salida entre módulos
            index            landing page (solo sitio público)
    -->
    <link rel="stylesheet" href="../assets/css/01-base.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/03-componentes.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/04-modales.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/05-tablas.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/06-responsive.css?v=<?= $v ?>">
    <link rel="stylesheet" href="../assets/css/07-transiciones.css?v=<?= $v ?>">
    <?php foreach ($cssExtra as $css): ?>
        <link rel="stylesheet" href="../assets/css/<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>?v=<?= $v ?>">
    <?php endforeach; ?>
</head>
<body>
