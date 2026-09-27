<?php
// El bloqueo se conserva en la sesión PHP para sobrevivir a recargas y navegación.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['inactividad_contexto'])) {
    try {
        $_SESSION['inactividad_contexto'] = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $_SESSION['inactividad_contexto'] = hash('sha256', uniqid('', true));
    }
}

$inactividadInicialmenteBloqueada = !empty($_SESSION['sesion_bloqueada']);
$inactividadContexto = hash('sha256', (string) $_SESSION['inactividad_contexto']);
$inactividadConfig = [
    'inicialmenteBloqueada' => $inactividadInicialmenteBloqueada,
    'contexto' => $inactividadContexto,
    // El navegador NO debe decidir el plazo: lo recibe desde core/Config.php
    // para que cambiarlo en un solo sitio sincronice servidor y cliente.
    'minutosInactividad' => Config::MINUTOS_INACTIVIDAD,
    'segundosGracia'     => Config::SEGUNDOS_GRACIA_INACTIVIDAD,
    'bloqueadaEn' => isset($_SESSION['inactividad_bloqueada_en'])
        ? (int) $_SESSION['inactividad_bloqueada_en']
        : null,
    'temporizadorIniciaEn' => isset($_SESSION['inactividad_temporizador_inicia_en'])
        ? (int) $_SESSION['inactividad_temporizador_inicia_en']
        : null,
    'cierraEn' => isset($_SESSION['inactividad_cierra_en'])
        ? (int) $_SESSION['inactividad_cierra_en']
        : null
];
$inactividadDisplay = $inactividadInicialmenteBloqueada ? 'flex' : 'none';
?>
<div id="modalBloqueoInactividad"
     role="dialog"
     aria-modal="true"
     aria-labelledby="tituloBloqueoInactividad"
     aria-describedby="descripcionBloqueoInactividad"
     style="display: <?= $inactividadDisplay ?>; position: fixed; inset: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(6px); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #0f172a; padding: 2rem; border-radius: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); width: 100%; max-width: 380px; text-align: center; color: #f8fafc;">

        <div style="background: rgba(245, 158, 11, 0.15); width: 56px; height: 56px; border-radius: 1.25rem; border: 1px solid rgba(245, 158, 11, 0.3); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
            <span style="font-size: 26px;">🔒</span>
        </div>

        <h3 id="tituloBloqueoInactividad" style="margin: 0 0 0.5rem 0; font-size: 1.25rem; font-weight: 800; color: #fff;">Sesión Bloqueada por Inactividad</h3>
        <p id="descripcionBloqueoInactividad" style="margin: 0 0 1.25rem 0; font-size: 0.85rem; color: #94a3b8; font-weight: 500;">Ingresa la contraseña de tu cuenta para reanudar la sesión.</p>

        <div id="mensajeErrorModal" role="alert" aria-live="assertive" style="display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.75rem; border-radius: 0.75rem; font-size: 0.8rem; font-weight: 700; margin-bottom: 1rem;"></div>

        <div style="margin-bottom: 1.25rem; text-align: left;">
            <input type="password" id="inputPasswordModal" autocomplete="current-password" placeholder="Contraseña actual" aria-label="Contraseña actual" style="width: 100%; padding: 0.85rem 1rem; border-radius: 0.85rem; border: 1px solid #334155; background: #1e293b; color: #fff; box-sizing: border-box; outline: none; font-size: 0.85rem;" required>
        </div>

        <button id="btnDesbloquearModal" type="button" style="width: 100%; padding: 0.85rem; border: none; border-radius: 0.85rem; background: #0284c7; color: #fff; font-weight: 800; font-size: 0.8rem; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.3);">DESBLOQUEAR SESIÓN</button>

        <div style="margin-top: 1.25rem;">
            <a id="enlaceCerrarSesionInactividad" href="<?= htmlspecialchars(obtenerRutaCs('assets/cerrar.php'), ENT_QUOTES, 'UTF-8') ?>" style="color: #64748b; font-size: 0.8rem; font-weight: 600; text-decoration: underline;">Cerrar sesión</a>
        </div>
    </div>
</div>

<?php
// La ruta se resuelve en PHP para que el enlace quede correcto desde Admin,
// Conductor, Pasajero o desde la raíz.
function obtenerRutaCs($ruta) {
    $requestUri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');
    $esSubcarpeta = preg_match('~/(admin|conductor|pasajero)/~i', $requestUri);

    return $esSubcarpeta ? '../' . $ruta : $ruta;
}
?>
<script>
window.SGET_INACTIVITY_CONFIG = <?= json_encode($inactividadConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?php echo obtenerRutaCs('js/inactividad.js'); ?>"></script>
