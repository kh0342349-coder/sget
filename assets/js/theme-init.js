/* ==========================================================================
   assets/js/theme-init.js
   ------------------------------------------------------------------------------
   TEMA CLARO / OSCURO + GESTIÓN DE COOKIES
   ------------------------------------------------------------------------------
   Debe cargarse en el <head>, sin `defer`, y en TODAS las páginas.

   POR QUÉ ESTE ARCHIVO CONCENTRA TAMBIÉN LAS COOKIES
     Es el ÚNICO punto que se ejecuta antes del primer pintado, y por tanto el
     único que puede decidir si una cookie de preferencias puede escribirse.
     Antes:

       · escribía `sget_tema` SIEMPRE, sin mirar el consentimiento. El panel de
         cookies decía «Preferencias: activadas/desactivadas», pero da igual lo
         que el usuario eligiera: la cookie se creaba igual. Eso es exactamente
         la incoherencia que había que eliminar.

       · la lógica de consentimiento vivía en el `<script>` de `index.php`, sin
         relación con el que escribía la cookie: dos sistemas que no se hablaban.

     Aquí hay UNA sola fuente de verdad: `SGETCookies`.

   QUÉ GARANTIZA
     · `html` lleva `dark`/`light`, `data-theme` y `style.colorScheme`.
     · La preferencia se guarda en `localStorage` SOLO si el usuario la aceptó.
     · La cookie `sget_tema` se escribe SOLO con `preferencias === true`, y se
       borra en cuanto el usuario retira el consentimiento.
     · El consentimiento se guarda en `localStorage` (no es una cookie).
     · Si el sistema pide oscuro y no hay preferencia, se respeta.

   API:  window.SGETTheme.get()  ·  .set('dark'|'light')  ·  .toggle()
         window.SGETCookies.consentimiento()  ·  .guardar(prefs)  ·  .coincide()
   ========================================================================== */
(function () {
    'use strict';

    var root = document.documentElement;
    var CLAVE = 'theme';             // clave canónica de localStorage
    var CLAVE_VIEJA = 'color-theme'; // instalaciones antiguas
    var CLAVE_CONSENTIMIENTO = 'sget_cookies_consent';

    /* ================================================================== */
    /* Preferencias de cookies · fuente única de verdad                  */
    /* ================================================================== */
    var SGETCookies = {

        /* La decisión de consentimiento vive en localStorage porque NO es una
           cookie: no viaja al servidor y no la crea JavaScript sola. */
        consentimiento: function () {
            try {
                var bruto = localStorage.getItem(CLAVE_CONSENTIMIENTO);
                if (!bruto) return null;
                var prefs = JSON.parse(bruto);
                if (!prefs || typeof prefs !== 'object') return null;
                return {
                    necesarias:   true,                    // no es opcional
                    preferencias: prefs.preferencias === true,
                    // SGET NO usa cookies analíticas. El campo se conserva para
                    // que el consentimiento guardado sea válido si algún día se
                    // añade alguna, pero hoy siempre es false.
                    analitica:    prefs.analitica === true
                };
            } catch (error) {
                return null;
            }
        },

        /** ¿El usuario ha aceptado guardar preferencias? */
        permitePreferencias: function () {
            var c = this.consentimiento();
            return !!c && c.preferencias === true;
        },

        /** Guarda el consentimiento y aplica sus consecuencias de inmediato. */
        guardar: function (prefs) {
            var finales = {
                necesarias:   true,
                preferencias: !!(prefs && prefs.preferencias),
                analitica:    !!(prefs && prefs.analitica)
            };

            try {
                localStorage.setItem(CLAVE_CONSENTIMIENTO, JSON.stringify(finales));
            } catch (error) {
                // Sin almacenamiento no se puede recordar el consentimiento:
                // se asume que NO se permiten preferencias.
                finales.preferencias = false;
                finales.analitica = false;
            }

            aplicarConsentimiento(finales);
            return finales;
        },

        /** Restablece: el visitante volverá a ver el aviso la próxima vez. */
        olvidar: function () {
            try { localStorage.removeItem(CLAVE_CONSENTIMIENTO); } catch (error) {}
            borrarCookieTema();
            borrarTemaGuardado();
        }
    };

    /* ================================================================== */
    /* Cookies                                                            */
    /* ================================================================== */

    /* ¿La página se está sirviendo por HTTPS? Solo entonces se marca `Secure`.
       Ponerlo en `http://localhost` haría que el navegador DESCARTASE la
       cookie y el tema no se guardaría nunca. */
    function esHttps() {
        return (location.protocol === 'https:')
            || (location.hostname === 'localhost' && location.protocol === 'https:');
    }

    /* Atributos de la cookie de preferencias:
         Path=/      → la usan todas las páginas
         SameSite=Lax→ no se envía en iframes de terceros (CSRF)
         Max-Age     → un año
         Secure      → solo en HTTPS
       SIN `HttpOnly` porque la cookie la administra JavaScript a propósito. */
    function escribirCookieTema(tema) {
        try {
            var partes = [
                'sget_tema=' + encodeURIComponent(tema),
                'path=/',
                'max-age=31536000',
                'samesite=Lax'
            ];
            if (esHttps()) partes.push('secure');
            document.cookie = partes.join(';');
        } catch (error) {
            // Sin cookies la interfaz sigue funcionando: solo no recuerda el tema.
        }
    }

    function borrarCookieTema() {
        try {
            var partes = ['sget_tema=', 'path=/', 'max-age=0', 'samesite=Lax'];
            if (esHttps()) partes.push('secure');
            document.cookie = partes.join(';');
        } catch (error) {}
    }

    /** ¿Existe ahora mismo la cookie `sget_tema`? (para diagnósticos) */
    function tieneCookieTema() {
        return document.cookie.split(';').some(function (parte) {
            return parte.trim().indexOf('sget_tema=') === 0;
        });
    }

    /* ================================================================== */
    /* Tema                                                               */
    /* ================================================================== */

    function aplicar(tema, persistir) {
        tema = tema === 'dark' ? 'dark' : 'light';

        root.classList.toggle('dark', tema === 'dark');
        root.classList.toggle('light', tema === 'light');
        root.setAttribute('data-theme', tema);
        root.style.colorScheme = tema;

        /* Persistir la preferencia implica que el usuario la aceptó. Si no la
           aceptó, no se guarda NI en localStorage NI en la cookie: el tema se
           aplica solo durante esta visita y el sistema vuelve a mandar. */
        if (persistir && SGETCookies.permitePreferencias()) {
            try {
                localStorage.setItem(CLAVE, tema);
                localStorage.removeItem(CLAVE_VIEJA);
            } catch (error) {
                // Sin almacenamiento la interfaz sigue funcionando.
            }
            escribirCookieTema(tema);
        } else if (!SGETCookies.permitePreferencias()) {
            /* Al retirar el consentimiento se borra cualquier rastro previo que
               hubiera de una visita anterior. */
            borrarCookieTema();
            borrarTemaGuardado();
        }

        return tema;
    }

    function leerGuardado() {
        /* Solo se lee si el usuario aceptó preferencias: si las rechazó, un
           valor antiguo en localStorage no debe seguir decidiendo el tema. */
        if (!SGETCookies.permitePreferencias()) return null;
        try {
            return localStorage.getItem(CLAVE) || localStorage.getItem(CLAVE_VIEJA);
        } catch (error) {
            return null;
        }
    }

    function borrarTemaGuardado() {
        try {
            localStorage.removeItem(CLAVE);
            localStorage.removeItem(CLAVE_VIEJA);
        } catch (error) {}
    }

    function escribirGuardado() {
        try {
            localStorage.setItem(CLAVE, root.getAttribute('data-theme') || 'light');
            localStorage.removeItem(CLAVE_VIEJA);
        } catch (error) {}
    }

    function actualizarIcono() {
        var icono = document.getElementById('themeIcon');
        if (!icono) return;
        var oscuro = root.classList.contains('dark');
        icono.className = 'fas ' + (oscuro ? 'fa-sun' : 'fa-moon') +
                          ' text-base ' + (oscuro ? 'text-amber-400' : 'text-slate-600');
    }

    /** Traduce un cambio de consentimiento a sus efectos sobre el tema. */
    function aplicarConsentimiento(prefs) {
        var actual = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';

        if (prefs.preferencias) {
            // Se vuelve a persistir el tema que el usuario está viendo ahora.
            try { localStorage.setItem(CLAVE, actual); } catch (error) {}
            escribirCookieTema(actual);
        } else {
            borrarCookieTema();
            borrarTemaGuardado();
        }

        document.dispatchEvent(new CustomEvent('sget:consentimiento', {
            detail: { preferencias: prefs.preferencias }
        }));
    }

    /* --- Resolver el tema inicial --- */
    var guardado = leerGuardado();
    var persistir = guardado === 'dark' || guardado === 'light';

    if (!persistir) {
        guardado = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark' : 'light';
    }

    aplicar(guardado, persistir);

    /* --- API global --- */
    window.SGETCookies = SGETCookies;

    window.SGETTheme = {
        set: function (tema) {
            var t = aplicar(tema, true);
            actualizarIcono();
            if (SGETCookies.permitePreferencias()) escribirGuardado();
            document.dispatchEvent(new CustomEvent('sget:tema', { detail: { tema: t } }));
            return t;
        },
        toggle: function () {
            return window.SGETTheme.set(root.classList.contains('dark') ? 'light' : 'dark');
        },
        get: function () { return root.classList.contains('dark') ? 'dark' : 'light'; },
        refrescarIcono: actualizarIcono
    };

    /* Marca de que el script llegó al final: sirve para diagnosticar si una
       página carga el archivo pero falla antes de registrar la API. */
    root.setAttribute('data-sget-theme-listo', '1');

    /* --- El icono del header sigue al tema aunque cambie desde otro sitio --- */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { actualizarIcono(); });
    } else {
        actualizarIcono();
    }

    /* Si otro código cambia el tema sin pasar por la API, el icono se resincroniza. */
    document.addEventListener('sget:tema', function () { actualizarIcono(); });
})();
