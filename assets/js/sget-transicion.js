/* ==========================================================================
   assets/js/sget-transicion.js
   ------------------------------------------------------------------------------
   TRANSICIÓN ENTRE MÓDULOS
   ------------------------------------------------------------------------------
   Antes, saltar entre páginas era un corte instantáneo. Esto intercepta los
   clics en enlaces internos, reproduce la animación de salida y recién entonces
   navega, de modo que el usuario percibe continuidad entre módulos.

   Reglas de seguridad (lo que NO se anima, para no romper nada):
     · enlaces con target, download, ctrl/cmd/shift/medio, o `rel=external`
     · enlaces a otro origen, a #anclas o a javascript:/mailto:/tel:
     · envíos de formulario, botones tipo submit
     · el botón atrás / adelante del navegador (se resuelve con `pageshow`)
     · usuarios con `prefers-reduced-motion: reduce`

   API
     SGETTransicion.iniciar()   arranca (automático en DOMContentLoaded)
     SGETTransicion.saltar()    navega sin animación
   ========================================================================== */
(function (window, document) {
    'use strict';

    var DURACION_SALIDA = 300;   // debe coincidir con 07-transiciones.css
    var rutaActual = null;
    var navigating = false;

    var reduced = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : { matches: false };

    /* ------------------------------------------------------------------ */
    /* Barra de progreso                                                  */
    /* ------------------------------------------------------------------ */
    function barra() {
        var b = document.querySelector('.sget-barra-progreso');
        if (!b) {
            b = document.createElement('div');
            b.className = 'sget-barra-progreso';
            b.setAttribute('aria-hidden', 'true');
            document.body.appendChild(b);
        }
        return b;
    }

    function avanzar(pct) {
        var b = barra();
        b.style.width = pct + '%';
        b.dataset.visible = '1';
    }

    function detener() {
        var b = document.querySelector('.sget-barra-progreso');
        if (b) {
            b.style.width = '100%';
            setTimeout(function () {
                b.dataset.visible = '0';
                setTimeout(function () { b.style.width = '0'; }, 220);
            }, 180);
        }
    }

    /* ------------------------------------------------------------------ */
    /* ¿Este enlace se puede animar?                                       */
    /* ------------------------------------------------------------------ */
    function esAnimable(a) {
        if (!a || !a.href) return false;
        if (reduced.matches) return false;
        if (navigating) return false;

        // Propiedades del elemento
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download')) return false;
        if (a.getAttribute('rel') === 'external') return false;
        if (a.dataset.sgetSinTransicion !== undefined) return false;

        // Modificadores del clic
        if (a.__sgetCtrl || a.__sgetMeta || a.__sgetShift || a.__sgetAlt) return false;

        // Destino
        var url;
        try { url = new URL(a.href, window.location.href); }
        catch (e) { return false; }

        if (url.protocol !== window.location.protocol) return false;   // mailto:, tel:, http vs https
        if (url.host !== window.location.host) return false;           // externo
        if (url.pathname === window.location.pathname) {
            // Solo anclas en la misma página: no hay cambio de módulo
            return false;
        }
        // No animar hacia/desde el API ni hacia archivos que se descargan
        if (/\/(api|assets)\//.test(url.pathname)) return false;
        if (/\.(php|html|htm)$/.test(url.pathname) === false) return false;

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Navegación                                                         */
    /* ------------------------------------------------------------------ */
    function saltar() {
        var destino = rutaActual;
        navigating = true;
        document.documentElement.classList.remove('sget-saliendo');
        if (destino) window.location.assign(destino);
    }

    function salir(href) {
        navigating = true;
        rutaActual = href;
        document.documentElement.classList.add('sget-saliendo');
        avanzar(35);

        setTimeout(function () { avanzar(80); }, 90);
        setTimeout(saltar, DURACION_SALIDA);
    }

    /* ------------------------------------------------------------------ */
    /* Marcado de los modificadores del clic                              */
    /* ------------------------------------------------------------------ */
    function marcarModificadores() {
        document.addEventListener('mousedown', function (e) {
            var a = e.target.closest('a');
            if (!a) return;
            a.__sgetCtrl  = e.ctrlKey  || e.metaKey;
            a.__sgetMeta  = e.metaKey;
            a.__sgetShift = e.shiftKey;
            a.__sgetAlt   = e.altKey;
        }, true);
    }

    /* ------------------------------------------------------------------ */
    /* Arranque                                                           */
    /* ------------------------------------------------------------------ */
    function iniciar() {
        // Clasificación de entrada: se ejecuta también con la tecla atrás
        // (bfcache), donde `pageshow` vuelve a firing sin recargar.
        function alMostrar() {
            document.documentElement.classList.remove('sget-saliendo');
            navigating = false;
            rutaActual = null;
            detener();
        }

        window.addEventListener('pageshow', alMostrar);
        // Si la navegación falla, la interfaz no debe quedarse congelada
        window.addEventListener('pagehide', function () { navigating = true; });

        marcarModificadores();

        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0) return;

            // Los formularios y botones submit navegan por su cuenta
            if (e.target.closest('form, button[type="submit"], [type="submit"]')) return;

            var a = e.target.closest('a');
            if (!esAnimable(a)) return;

            e.preventDefault();
            salir(a.href);
        }, false);

        // Si el usuario pide menos movimiento en caliente, se desactiva todo
        if (reduced.addEventListener) {
            reduced.addEventListener('change', function (e) {
                if (e.matches) {
                    document.documentElement.classList.remove('sget-saliendo');
                }
            });
        }

        // Red de seguridad: si por lo que sea la animación no termina,
        // tras 1,5 s se navega igual para no dejar al usuario esperando.
        setTimeout(function () {
            if (navigating && rutaActual) saltar();
        }, 1500);
    }

    window.SGETTransicion = { iniciar: iniciar, saltar: saltar, salir: salir };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})(window, document);
