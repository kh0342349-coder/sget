/* ==========================================================================
   assets/js/sget-google.js
   -----------------------------------------------------------------------------
   MONTAJE DEL BOTÓN "CONTINUAR CON GOOGLE" EN EL MODAL DE INICIO DE SESIÓN
   -----------------------------------------------------------------------------
   POR QUÉ ESTE ARCHIVO EXISTE (el botón no aparecía)
     El botón lo dibuja Google Identity Services (GSI) con
     `google.accounts.id.renderButton()`. GSI exige dos cosas:
       1. que el script https://accounts.google.com/gsi/client esté cargado, y
       2. que el contenedor esté VISIBLE en el momento de renderizar.
     Antes, el montaje vivía dentro de `abrirPanel()` en index.php, que solo se
     ejecuta en dos casos: la navegación entre modales y la apertura automática
     por sesión. Los botones de la cabecera y de las tarjetas de la landing
     usan `data-sget-modal="panelLogin"`, que pasa por SGETModal.abrir() y NUNCA
     llamaba a abrirPanel(): el hueco quedaba vacío para casi todos los usuarios.

     Ahora el motor de modales emite `sget:modal-abierto` (ver sget-modal.js) y
     este archivo monta el botón siempre que el panel se abra, venga por donde
     venga. Además:
       · espera al SDK (no renderiza sobre un hueco vacío),
       · es idempotente: abrir/cerrar el modal N veces no duplica el botón,
       · se repinta al cambiar el tema (GSI no admite re-render sobre el mismo
         nodo, así que se sustituye el contenedor por uno nuevo),
       · si Google no está disponible muestra un aviso claro en vez del vacío.
   ========================================================================== */
(function (window, document) {
    'use strict';

    /* Identificador de la aplicación en Google Cloud (OAuth 2.0 → "ID de cliente
       web"). Debe coincidir con el dominio/origen autorizado en la consola. */
    var CLIENT_ID = '916674198156-4uh6adhaklk2bpsvli6hnmrgg0bgktlp.apps.googleusercontent.com';

    var ID_PANEL      = 'panelLogin';
    var SEL_CONTENEDOR = '[data-sget-google]';
    var SEL_CARGANDO  = '[data-sget-google-cargando]';
    var SEL_AVISO     = '[data-sget-google-aviso]';

    var MAX_ESPERA_MS = 10000;   // 10 s: tras eso se avisa y se deja de insistir
    var ESPERA_MS     = 120;     // intervalo entre comprobaciones

    /* Estado por panel, para no renderizar dos veces sobre el mismo nodo. */
    var montado = false;   // el botón oficial ya está en el DOM
    var avisado = false;   // ya se mostró el aviso de "Google no disponible"
    var idioma   = '';

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                         */
    /* ------------------------------------------------------------------ */
    function sdkListo() {
        return !!(window.google && window.google.accounts && window.google.accounts.id);
    }

    function panel() {
        return document.getElementById(ID_PANEL);
    }

    function zona() {
        var p = panel();
        return p ? p.querySelector('[data-sget-google-zona]') : null;
    }

    function contenedor() {
        var p = panel();
        return p ? p.querySelector(SEL_CONTENEDOR) : null;
    }

    function temaOscuro() {
        return document.documentElement.classList.contains('dark');
    }

    function idiomaActual() {
        return (document.documentElement.getAttribute('data-language') ||
                document.documentElement.getAttribute('lang') || 'es').slice(0, 2) === 'en' ? 'en' : 'es';
    }

    function mostrarAviso(mensaje) {
        var caja = zona();
        if (!caja) return;
        var aviso  = caja.querySelector(SEL_AVISO);
        var texto  = caja.querySelector('[data-sget-google-texto]');
        var hueco  = caja.querySelector(SEL_CONTENEDOR);

        // Se limpia el hueco: nada de esqueletos de carga eternos
        if (hueco) hueco.hidden = true;
        if (aviso) {
            if (mensaje && texto) texto.textContent = mensaje;
            aviso.hidden = false;
        }
        avisado = true;
    }

    function ocultarAviso() {
        var caja = zona();
        if (!caja) return;
        var aviso = caja.querySelector(SEL_AVISO);
        var hueco = caja.querySelector(SEL_CONTENEDOR);
        if (aviso) aviso.hidden = true;
        if (hueco) hueco.hidden = false;
        avisado = false;
    }

    /**
     * Crea un contenedor NUEVO para el botón.
     * GSI registra internamente el nodo donde pintó: si se vuelve a renderizar
     * sobre el mismo elemento lanza "Cannot render button twice". Sustituir el
     * nodo (en lugar de vaciarlo con innerHTML) es la única forma limpia de
     * repintar, por ejemplo al cambiar de tema.
     */
    function contenedorNuevo() {
        var viejo = contenedor();
        if (!viejo) return null;

        var nuevo = document.createElement('div');
        nuevo.className = viejo.className;
        nuevo.setAttribute(SEL_CONTENEDOR.replace(/[\[\]]/g, ''), '');
        if (viejo.id) nuevo.id = viejo.id;
        viejo.parentNode.replaceChild(nuevo, viejo);
        return nuevo;
    }

    /* ------------------------------------------------------------------ */
    /* Montaje                                                            */
    /* ------------------------------------------------------------------ */
    function renderizar(destino) {
        var caja = destino || contenedor();
        if (!caja) return false;

        try {
            window.google.accounts.id.initialize({
                client_id: CLIENT_ID,
                callback: window.handleGoogleResponse,
                ux_mode: 'popup',
                context: 'signin',
                auto_select: false,
                cancel_on_tap_outside: true
            });

            window.google.accounts.id.renderButton(caja, {
                type: 'standard',
                theme: temaOscuro() ? 'filled_black' : 'outline',
                size: 'large',
                shape: 'pill',
                width: Math.max(240, Math.min(320, (caja.clientWidth || 280))),
                // 'continue_with' es el único valor válido en todos los idiomas;
                // el texto concreto lo pone Google según `locale`.
                text: 'continue_with',
                locale: idioma
            });

            montado = true;
            ocultarAviso();
            return true;
        } catch (error) {
            console.warn('[SGET] No se pudo montar el botón de Google:', error);
            return false;
        }
    }

    /**
     * Espera activa al SDK de Google y monta el botón.
     * @param {boolean} forzarRepintado  true = ignorar el estado "ya montado"
     */
    function montar(forzarRepintado) {
        idioma = idiomaActual();
        if (forzarRepintado) { montado = false; avisado = false; }

        if (!zona()) return;               // esta página no tiene el modal de login

        // El panel debe ser visible: GSI no renderiza en un nodo oculto.
        if (!panel() || panel().dataset.abierto !== '1') return;

        if (montado && !forzarRepintado) return;

        ocultarAviso();
        var intento = 0;

        (function esperar() {
            if (sdkListo()) {
                var caja = forzarRepintado ? contenedorNuevo() : contenedor();
                if (caja && renderizar(caja)) return;
                mostrarAviso('No se pudo cargar el acceso con Google. Intenta de nuevo en unos segundos.');
                return;
            }
            if ((intento * ESPERA_MS) >= MAX_ESPERA_MS) {
                mostrarAviso();
                return;
            }
            intento++;
            window.setTimeout(esperar, ESPERA_MS);
        })();
    }

    /* ------------------------------------------------------------------ */
    /* Arranque                                                            */
    /* ------------------------------------------------------------------ */
    function iniciar() {
        idioma = idiomaActual();

        // 1) El motor de modales avisa de cada apertura (data-sget-modal,
        //    abrirPanel(), cambio entre modales, apertura por sesión...)
        document.addEventListener('sget:modal-abierto', function (e) {
            if (!e.detail || e.detail.id !== ID_PANEL) return;
            montar(false);
        });

        // 2) Red de seguridad: si el panel ya quedó abierto antes de que
        //    este script corriera (o se abrió sin pasar por el motor).
        if (panel() && panel().dataset.abierto === '1') montar(false);

        // 3) El tema decide el color del botón (outline / filled_black).
        document.addEventListener('sget:tema', function () {
            if (panel() && panel().dataset.abierto === '1') montar(true);
        });

        // 4) El idioma también se elige al renderizar.
        document.addEventListener('sget:idioma', function (e) {
            var nuevo = (e.detail && e.detail.idioma) || idiomaActual();
            if (nuevo !== idioma) montar(true);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    /* API pública (depuración y pruebas automatizadas). */
    window.SGETGoogle = { montar: montar, sdkListo: sdkListo };
    /* Se conserva el nombre histórico por si algún onclick lo invoca. */
    window.inicializarBotonGoogle = function () { montar(true); };
})(window, document);
