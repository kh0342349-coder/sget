/* ==========================================================================
   assets/js/theme-init.js
   ------------------------------------------------------------------------------
   TEMA CLARO / OSCURO · se ejecuta ANTES del primer pintado
   ------------------------------------------------------------------------------
   Debe cargarse en el <head>, sin `defer`, y en TODAS las páginas. Antes solo
   lo cargaban 5 de 30: en las otras el botón de la cabecera lanzaba
   "SGETTheme is not defined" y el tema no cambiaba nunca.

   Qué garantiza:
     · `html` lleva la clase `dark` o `light`  → variantes `dark:` de Tailwind
     · `html` lleva `data-theme`                 → CSS basado en atributos
     · `style.colorScheme` sigue al tema        → scrollbars y inputs nativos
     · la preferencia se guarda en localStorage  → sobrevive a recargas
     · si el sistema pide oscuro y no hay preferencia, se respeta

   API:  window.SGETTheme.get()  ·  window.SGETTheme.set('dark'|'light')  ·  .toggle()
   ========================================================================== */
(function () {
    'use strict';

    var root = document.documentElement;
    var CLAVE = 'theme';          // clave canónica
    var CLAVE_VIEJA = 'color-theme'; // instalaciones antiguas

    function aplicar(tema, persistir) {
        tema = tema === 'dark' ? 'dark' : 'light';

        root.classList.toggle('dark', tema === 'dark');
        root.classList.toggle('light', tema === 'light');
        root.setAttribute('data-theme', tema);
        root.style.colorScheme = tema;

        if (persistir) {
            try {
                localStorage.setItem(CLAVE, tema);
                localStorage.removeItem(CLAVE_VIEJA);
            } catch (error) {
                // Sin almacenamiento la interfaz sigue funcionando, solo no recuerda.
            }
        }
        return tema;
    }

    function leerGuardado() {
        try {
            return localStorage.getItem(CLAVE) || localStorage.getItem(CLAVE_VIEJA);
        } catch (error) {
            return null;
        }
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

    /* --- Resolver el tema inicial --- */
    var guardado = leerGuardado();
    var persistir = guardado === 'dark' || guardado === 'light';

    if (!persistir) {
        guardado = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark' : 'light';
    }

    aplicar(guardado, persistir);

    /* --- API global --- */
    window.SGETTheme = {
        set: function (tema) {
            var t = aplicar(tema, true);
            actualizarIcono();
            escribirGuardado();
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
