/* ==========================================================================
   assets/js/sget-google.js
   -----------------------------------------------------------------------------
   MONTAJE DEL BOTÓN "CONTINUAR CON GOOGLE" EN LOS MODALES DE ACCESO
   -----------------------------------------------------------------------------
   POR QUÉ ESTE ARCHIVO EXISTE
     El botón lo dibuja Google Identity Services (GSI) con
     `google.accounts.id.renderButton()`, y GSI exige dos cosas:
       1. que el script https://accounts.google.com/gsi/client esté cargado, y
       2. que el contenedor esté VISIBLE en el momento de renderizar.
     Antes el montaje vivía dentro de `abrirPanel()` en index.php, que solo se
     ejecuta al cambiar de un modal a otro: los botones de la cabecera y de las
     tarjetas de la landing usan `data-sget-modal`, que pasa por
     `SGETModal.abrir()` y nunca llamaba a esa función, así que el hueco quedaba
     vacío para casi todos los usuarios.

     Ahora el motor de modales emite `sget:modal-abierto` y este archivo monta
     el botón en CUALQUIER panel que declare la zona, venga por donde venga.

   PANELES QUE DECLARAN LA ZONA
     · #panelLogin     → "Continuar con Google" (iniciar sesión)
     · #panelRegistro  → "Registrarse con Google"  (crea la cuenta si no existe)
     Ambos usan EXACTAMENTE el mismo componente, la misma zona y las mismas
     reglas visuales: no hay dos diseños distintos para el mismo servicio.

   CUIDADOS
     · Idempotente: abrir/cerrar N veces no duplica el botón.
     · Se repinta al cambiar de tema (GSI no admite re-render sobre el mismo
       nodo, así que se sustituye el contenedor por uno nuevo).
     · Envía el token anti-CSRF que el backend exige.
     · Si Google no está disponible muestra un aviso claro en vez del vacío.
   ========================================================================== */
(function (window, document) {
    'use strict';

    /* Identificador de la aplicación en Google Cloud (OAuth 2.0 → «ID de
       cliente web»). Debe coincidir con el dominio/origen autorizado en la
       consola de Google Cloud. */
    var CLIENT_ID = '916674198156-4uh6adhaklk2bpsvli6hnmrgg0bgktlp.apps.googleusercontent.com';

    var SEL_ZONA      = '[data-sget-google-zona]';
    var SEL_CONTENEDOR= '[data-sget-google]';
    var SEL_CARGANDO  = '[data-sget-google-cargando]';
    var SEL_AVISO     = '[data-sget-google-aviso]';
    var SEL_ENDPOINT  = '[data-sget-google-endpoint]';

    var MAX_ESPERA_MS = 10000;   // 10 s: tras eso se avisa y se deja de insistir
    var ESPERA_MS     = 120;

    /* Estado por zona (clave = id del panel). Cada panel lleva su propio
       "montado": el login puede estar pintado mientras el de registro sigue
       esperando al SDK, y ninguno pisa al otro. */
    var estado = {};
    var avisoMostrado = {};

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                         */
    /* ------------------------------------------------------------------ */
    function sdkListo() {
        return !!(window.google && window.google.accounts && window.google.accounts.id);
    }

    function zonas() {
        return Array.prototype.slice.call(document.querySelectorAll(SEL_ZONA));
    }

    function panelDe(zona) {
        return zona.closest('[data-sget-capa]') || zona.closest('.sget-modal-wrap');
    }

    function clave(zona) {
        var p = panelDe(zona);
        return p && p.id ? p.id : 'sin-id';
    }

    function estadoDe(zona) {
        var k = clave(zona);
        if (!estado[k]) estado[k] = { montado: false };
        return estado[k];
    }

    function temaOscuro() {
        return document.documentElement.classList.contains('dark');
    }

    function idiomaActual() {
        var attr = document.documentElement.getAttribute('data-language')
                || document.documentElement.getAttribute('lang')
                || 'es';
        return attr.slice(0, 2).toLowerCase() === 'en' ? 'en' : 'es';
    }

    function tokenCsrf() {
        if (window.SGET_CSRF) return window.SGET_CSRF;
        var campo = document.querySelector('input[name="_token"]');
        return campo ? campo.value : '';
    }

    function endpointDe(zona) {
        var nodo = zona.querySelector(SEL_ENDPOINT);
        return nodo ? nodo.value : 'controllers/auth_google.php';
    }

    function mostrarAviso(zona, mensaje) {
        var aviso = zona.querySelector(SEL_AVISO);
        var texto = zona.querySelector('[data-sget-google-texto]');
        var hueco = zona.querySelector(SEL_CONTENEDOR);

        if (hueco) hueco.hidden = true;
        if (aviso) {
            if (mensaje && texto) texto.textContent = mensaje;
            aviso.hidden = false;
        }
        avisoMostrado[clave(zona)] = true;
    }

    function ocultarAviso(zona) {
        var aviso = zona.querySelector(SEL_AVISO);
        var hueco = zona.querySelector(SEL_CONTENEDOR);
        if (aviso) aviso.hidden = true;
        if (hueco) hueco.hidden = false;
        avisoMostrado[clave(zona)] = false;
    }

    /**
     * Crea un contenedor NUEVO para el botón.
     * GSI registra internamente el nodo donde pintó: volver a renderizar sobre
     * el mismo elemento lanza «Cannot render button twice». Sustituir el nodo
     * (en lugar de vaciarlo con innerHTML) es la única forma limpia de
     * repintar, por ejemplo al cambiar de tema.
     */
    function contenedorNuevo(zona) {
        var viejo = zona.querySelector(SEL_CONTENEDOR);
        if (!viejo) return null;

        var nuevo = document.createElement('div');
        nuevo.className = viejo.className;
        nuevo.setAttribute('data-sget-google', '');
        if (viejo.id) nuevo.id = viejo.id;
        viejo.parentNode.replaceChild(nuevo, viejo);
        return nuevo;
    }

    /* ------------------------------------------------------------------ */
    /* Montaje                                                            */
    /* ------------------------------------------------------------------ */
    function renderizar(zona, destino) {
        var caja = destino || zona.querySelector(SEL_CONTENEDOR);
        if (!caja) return false;

        /* ANCHO DEL BOTÓN
           El botón oficial debe ocupar exactamente lo mismo que el botón
           principal del formulario. GSI recibe un ancho en píxeles y lo fija en
           línea, así que hay que medirlo bien:

           · `clientWidth` mide el ANCHO DE CONTENIDO, que es 0 si el elemento
             todavía no tiene layout (el panel se monta con una animación).
           · `getBoundingClientRect()` incluye la `transform` del modal
             (`scale(.94)` al entrar), así que da un valor MÁS ANCHO que el real:
             con eso el botón se salía de la caja por los dos lados.

           La solución es medir el contenedor padre con `offsetWidth` (ancho de
           layout, sin transform) y, además, forzar `max-width:100%` al nodo que
           inyecta GSI. Con las dos barreras, el botón nunca puede desbordar. */
        var contenedor = caja.parentElement || caja;
        var anchoCaja = contenedor.offsetWidth || caja.offsetWidth || 0;
        var ancho = Math.round(Math.max(200, Math.min(400, anchoCaja || 320)));

        try {
            window.google.accounts.id.initialize({
                client_id: CLIENT_ID,
                callback: function (respuesta) { enviar(respuesta, zona); },
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
                width: ancho,
                text: 'continue_with',
                locale: idiomaActual()
            });

            /* Barrera de seguridad: aunque el ancho calculado se quedara corto
               por un cambio de layout, el botón nunca puede pasar del ancho de
               su contenedor. */
            Array.prototype.forEach.call(caja.children, function (hijo) {
                hijo.style.maxWidth = '100%';
            });

            estadoDe(zona).montado = true;
            ocultarAviso(zona);
            return true;
        } catch (error) {
            console.warn('[SGET] No se pudo montar el botón de Google:', error);
            return false;
        }
    }

    /** Envía el id_token al backend y aplica la respuesta. */
    function enviar(respuesta, zona) {
        try {
            fetch(endpointDe(zona), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ token: respuesta.credential, csrf: tokenCsrf() })
            })
                .then(function (r) { return r.json().catch(function () {
                    return { success: false, message: 'El servidor no devolvió una respuesta válida.' };
                }); })
                .then(function (data) {
                    if (data && data.success && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    var caja = zona.querySelector('[data-sget-google-texto]');
                    var aviso = zona.querySelector(SEL_AVISO);
                    var extra = (data && data.message) ? data.message
                        : 'No se pudo iniciar sesión con Google.';
                    if (aviso) aviso.hidden = false;
                    if (caja) caja.textContent = extra;
                    window.SGETModal && SGETModal.toast(extra, 'error');
                })
                .catch(function () {
                    var msg = 'No se pudo comunicar con el servidor. Revisa tu conexión.';
                    var caja = zona.querySelector('[data-sget-google-texto]');
                    var aviso = zona.querySelector(SEL_AVISO);
                    if (aviso) aviso.hidden = false;
                    if (caja) caja.textContent = msg;
                    window.SGETModal && SGETModal.toast(msg, 'error');
                });
        } catch (error) {
            console.warn('[SGET] Error al procesar la respuesta de Google:', error);
        }
    }

    /**
     * Espera activa al SDK de Google y monta el botón de una zona.
     * @param {Element} zona
     * @param {boolean} forzarRepintado  ignora el estado «ya montado»
     */
    function montarZona(zona, forzarRepintado) {
        if (!zona) return;

        var st = estadoDe(zona);
        if (forzarRepintado) { st.montado = false; avisoMostrado[clave(zona)] = false; }
        if (st.montado) return;

        /* GSI no renderiza en un nodo oculto: el panel tiene que estar
           abierto de verdad. */
        var panel = panelDe(zona);
        if (panel && panel.dataset.abierto !== '1') return;

        ocultarAviso(zona);
        var intento = 0;

        (function esperar() {
            if (sdkListo()) {
                var caja = forzarRepintado ? contenedorNuevo(zona) : zona.querySelector(SEL_CONTENEDOR);
                if (caja && renderizar(zona, caja)) return;
                mostrarAviso(zona, 'No se pudo cargar el acceso con Google. Inténtalo de nuevo en unos segundos.');
                return;
            }
            if ((intento * ESPERA_MS) >= MAX_ESPERA_MS) {
                mostrarAviso(zona);
                return;
            }
            intento++;
            window.setTimeout(esperar, ESPERA_MS);
        })();
    }

    /** Monta todas las zonas cuyo panel esté abierto. */
    function montar(forzarRepintado) {
        zonas().forEach(function (zona) { montarZona(zona, forzarRepintado); });
    }

    /* ------------------------------------------------------------------ */
    /* Arranque                                                            */
    /* ------------------------------------------------------------------ */
    function iniciar() {
        /* 1) El motor de modales avisa de cada apertura (data-sget-modal,
              abrirPanel(), navegación entre modales, apertura por sesión…) */
        document.addEventListener('sget:modal-abierto', function (e) {
            if (!e.detail || !e.detail.id) return;
            var panel = document.getElementById(e.detail.id);
            if (!panel) return;
            var zona = panel.querySelector(SEL_ZONA);
            if (zona) montarZona(zona, false);
        });

        /* 2) Red de seguridad: el panel pudo quedar abierto antes de que
              este script corriera (o abrirse sin pasar por el motor). */
        if (document.querySelector('[data-sget-capa][data-abierto="1"]')) montar(false);

        /* 3) El tema decide el color del botón (outline / filled_black). */
        document.addEventListener('sget:tema', function () {
            if (document.querySelector('[data-sget-capa][data-abierto="1"]')) montar(true);
        });

        /* 4) El idioma también se elige al renderizar. */
        document.addEventListener('sget:idioma', function (e) {
            var nuevo = (e.detail && e.detail.idioma) || idiomaActual();
            if (nuevo !== idiomaActual()) montar(true);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    /* API pública (depuración y pruebas automatizadas). */
    window.SGETGoogle = { montar: montar, sdkListo: sdkListo, zonas: zonas };
    /* Se conserva el nombre histórico por si algún onclick lo invoca. */
    window.inicializarBotonGoogle = function () { montar(true); };
    /* El login normal ya no lo llama: el envío vive en `enviar()`. */
    window.handleGoogleResponse = function (respuesta) { enviar(respuesta, zonas()[0]); };
})(window, document);