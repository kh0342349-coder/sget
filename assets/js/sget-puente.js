/* ==========================================================================
   assets/js/sget-puente.js
   ------------------------------------------------------------------------------
   PUENTE DE COMPATIBILIDAD PARA LAS PÁGINAS LEGACY
   ------------------------------------------------------------------------------
   Conductor/ y Pasajero/ ya usan el marcado de modal del sistema
   (`.sget-modal-wrap` + `.sget-overlay` + `data-sget-capa`), pero su JavaScript
   histórico sigue llamando funciones con nombres propios:

       abrirModalSolicitar() · cerrarModalDrawer() · cerrarTodosModales() …

   Este archivo traduce esas funciones al motor común en vez de reescribir cada
   página. Así el marcado puede migrarse a modales sin tocar el JS de cada vista.

   Además, expone una función global `puenteCerrarTodos()` para el atajo de
   Escape, de modo que cerrar con el teclado funciona igual que con la X.
   ========================================================================== */
(function (window, document) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* PUENTES                                                           */
    /* ------------------------------------------------------------------ */

    /* Cada entrada mapea "función antigua" → "id del modal".
       Los ids son los que se conservan en el marcado migrado. */
    var PUENTES = {
        /* Conductor */
        abrirModalSolicitar:   'drawerProgramar',        // viajes_conductor
        abrirModalSolicitar2:  'drawerProgramarReporte',  // viaje_asignado
        abrirModalSolicitar3:  'drawerProgramarResenas',  // resenas_conductor

        /* Pasajero */
        abrirModalReserva:          'drawerReservaPasajero',
        abrirModalReservaHistorial: 'drawerReservaHistorial',
        abrirModalReservaGlobal:    'modalFichaViajePasajero'
    };

    var CIERRES = ['cerrarModalDrawer', 'cerrarModalConfirmar', 'cerrarModalResena',
                   'cerrarModalFicha', 'cerrarModalDrawerHistorial', 'cerrarModalFichaHistorial',
                   'cerrarModalCalificarHistorial', 'cerrarModalOpinion'];

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                         */
    /* ------------------------------------------------------------------ */
    function abrir(id) {
        if (!id) return;
        var el = document.getElementById(id);
        if (!el) { console.warn('[SGET] No existe el modal #' + id); return; }
        window.SGETModal.abrir(id);
    }

    /** Cierra todos los modales abiertos de la página. */
    function cerrarTodos() {
        if (window.SGETModal) window.SGETModal.cerrarTodos();
    }

    var yaEnvolados = {};

    function envolver(nombre, alEjecutar) {
        // Idempotente: si el nombre ya está envuelto no se vuelve a envolver.
        if (yaEnvolados[nombre]) return;
        yaEnvolados[nombre] = true;
        var previa = window[nombre];
        window[nombre] = function () {
            // 1) Lógica propia de la página (poblar campos, fechas…)
            if (typeof previa === 'function') {
                try { previa.apply(this, arguments); } catch (e) {
                    console.warn('[SGET] Error en ' + nombre + ':', e);
                }
            }
            // 2) El motor hace el resto (centrar, overlay, foco, Escape)
            try { alEjecutar(); } catch (e) { console.warn('[SGET] ' + nombre + ':', e); }
        };
    }

    /* ------------------------------------------------------------------ */
    /* Instalación                                                        */
    /* ------------------------------------------------------------------ */
    /* Se hace en DOMContentLoaded para que las funciones propias de la página
       ya existan: si el puente se instala antes, la página las define después y
       pisa el puente, y entonces el modal nunca se cierra. */
    function instalar() {
        Object.keys(PUENTES).forEach(function (nombre) {
            var id = PUENTES[nombre];
            envolver(nombre, function () { abrir(id); });
        });

        CIERRES.forEach(function (nombre) {
            envolver(nombre, cerrarTodos);
        });

        window.cerrarTodosModales = cerrarTodos;
        window.cerrarTodosModalesLegacy = cerrarTodos;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', instalar);
    } else {
        instalar();
    }
    /* Segunda pasada en `load`: algunas páginas definen sus funciones en
       scripts que se ejecutan después de DOMContentLoaded y, si no, la página
       pisaría el puente y el modal no cerraría al pulsar la X ni Cancelar. */
    window.addEventListener('load', function () { yaEnvolados = {}; instalar(); });

    /* Escape también cierra los modales de estas páginas. */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var abierto = document.querySelector('[data-sget-capa][data-abierto="1"]');
        if (!abierto) return;
        e.preventDefault();
        if (window.SGETModal) window.SGETModal.cerrar(abierto.id, false);
    });
})(window, document);
