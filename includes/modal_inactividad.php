<!-- includes/modal_inactividad.php -->
<div id="modalBloqueoInactividad" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(6px); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #0f172a; padding: 2rem; border-radius: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); width: 100%; max-width: 380px; text-align: center; color: #f8fafc;">
        
        <div style="background: rgba(245, 158, 11, 0.15); width: 56px; height: 56px; border-radius: 1.25rem; border: 1px solid rgba(245, 158, 11, 0.3); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
            <span style="font-size: 26px;">🔒</span>
        </div>

        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; font-weight: 800; color: #fff;">Sesión Bloqueada por Inactividad</h3>
        <p style="margin: 0 0 1.25rem 0; font-size: 0.85rem; color: #94a3b8; font-weight: 500;">Ingresa la contraseña de tu cuenta para reanudar la sesión.</p>

        <div id="mensajeErrorModal" style="display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.75rem; border-radius: 0.75rem; font-size: 0.8rem; font-weight: 700; margin-bottom: 1rem;"></div>

        <div style="margin-bottom: 1.25rem; text-align: left;">
            <input type="password" id="inputPasswordModal" autocomplete="current-password" placeholder="Contraseña actual" style="width: 100%; padding: 0.85rem 1rem; border-radius: 0.85rem; border: 1px solid #334155; background: #1e293b; color: #fff; box-sizing: border-box; outline: none; font-size: 0.85rem;" required>
        </div>

        <button id="btnDesbloquearModal" type="button" style="width: 100%; padding: 0.85rem; border: none; border-radius: 0.85rem; background: #0284c7; color: #fff; font-weight: 800; font-size: 0.8rem; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.3);">DESBLOQUEAR SESIÓN</button>
        
        <div style="margin-top: 1.25rem;">
            <a href="#" onclick="event.preventDefault(); window.location.href = obtenerRutaRaiz('assets/cerrar.php');" style="color: #64748b; font-size: 0.8rem; font-weight: 600; text-decoration: underline;">Cerrar sesión</a>
        </div>
    </div>
</div>

<script src="<?php echo (strpos($_SERVER['REQUEST_URI'], '/Admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/Conductor/') !== false || strpos($_SERVER['REQUEST_URI'], '/Pasajero/') !== false) ? '../js/inactividad.js' : 'js/inactividad.js'; ?>"></script>