/* ==========================================================================
   assets/js/sget-page.js
   ------------------------------------------------------------------------------
   COMPORTAMIENTO DECLARATIVO DE LAS PÁGINAS CRUD
   ------------------------------------------------------------------------------
   Un solo archivo para rutas, flota, usuarios y viajes. Cada página declara
   lo que necesita con atributos HTML, sin escribir JavaScript propio.

   MARCA DE ALTA / EDICIÓN
     data-sget-modal="modalRuta"        abre el modal indicado
       data-sget-datos='{...}'           (opcional) puebla el modal (edición)
       data-sget-nuevo='titulo|nombre'  (opcional) título del modo alta

   ACCIONES PUNTUALES (no necesitan modal)
     data-sget-accion="alternar|eliminar|finalizar|enCurso|cancelarViaje|leerNotificacion"
       data-sget-modulo="vehiculo"       módulo del API
       data-sget-dato='{"id":3}'        parámetros extra
       data-sget-titulo / data-sget-texto / data-sget-ok
       data-sget-anotacion="1"           exige anotación obligatoria

   FORMULARIOS
     data-sget-form                    se envía al API y redirige
     data-sget-cerrar-al-guardar="id"  cierra ese modal al terminar
   ========================================================================== */
(function (window, document) {
    'use strict';

    var API = '../api/index.php';

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */
    function enviar(datos, alTerminar) {
        return fetch(API, {
            method: 'POST',
            body: datos,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().catch(function () { return { status: 'error', mensaje: 'Respuesta inválida del servidor.' }; }); })
            .then(function (j) { if (alTerminar) alTerminar(j); return j; });
    }

    function token() { return window.SGETModal.__token; }

    function aviso(j) {
        window.SGETModal.toast(j.mensaje || 'Operación completada.', j.status === 'ok' ? 'exito' : 'error');
    }

    function recargarEn(ms) { setTimeout(function () { location.reload(); }, ms || 750); }

    /* ================================================================== */
    /* APERTURA DE MODALES                                                */
    /* ================================================================== */
    function prepararModales() {
        document.querySelectorAll('[data-sget-modal]').forEach(function (btn) {
            if (btn.hasAttribute('data-sget-listo')) return;
            btn.setAttribute('data-sget-listo', '1');

            btn.addEventListener('click', function () {
                var id = btn.dataset.sgetModal;
                var modal = document.getElementById(id);
                if (!modal) return;

                var form = modal.querySelector('[data-sget-panel]');
                var datos = null;

                try { datos = JSON.parse(btn.getAttribute('data-sget-datos') || 'null'); } catch (e) { datos = null; }

                if (datos) {
                    // ---- MODO EDICIÓN: se abre con datos ----
                    window.SGETModal.abrir(id, { datos: datos });
                    if (form) window.SGETModal.limpiarErrores(form);
                    return;
                }

                // ---- MODO ALTA: se limpia antes de abrir ----
                if (form) {
                    form.reset();
                    window.SGETModal.limpiarErrores(form);
                    form.querySelectorAll('[data-sget-campo]').forEach(function (c) {
                        if (c.type === 'hidden' && c.name.match(/^id_/)) c.value = '0';
                    });
                    form.querySelectorAll('[data-sget-texto="titulo"]').forEach(function (t) {
                        var partes = (btn.dataset.sgetNuevo || 'Nuevo registro').split('|');
                        t.textContent = partes[0];
                    });
                    // Valores por defecto sensatos (nunca datos de negocio inventados)
                    form.querySelectorAll('[data-sget-default]').forEach(function (c) {
                        c.value = c.dataset.sgetDefault;
                    });
                }
                window.SGETModal.abrir(id);
            });
        });
    }

    /* ================================================================== */
    /* FORMULARIOS                                                        */
    /* ================================================================== */
    function prepararFormularios() {
        document.querySelectorAll('[data-sget-form]').forEach(function (form) {
            if (form.hasAttribute('data-sget-listo')) return;
            form.setAttribute('data-sget-listo', '1');

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                enviar(new FormData(form), function (j) {
                    if (j.status === 'ok') {
                        if (form.dataset.sgetCerrarAlGuardar) window.SGETModal.cerrar(form.dataset.sgetCerrarAlGuardar);
                        recargarEn();
                    } else {
                        if (j.errores) window.SGETModal.errores(j.errores, form);
                        aviso(j);
                    }
                });
            });

            // Ctrl+Enter = guardar
            form.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        });
    }

    /* ================================================================== */
    /* ACCIONES PUNTUALES                                                  */
    /* ================================================================== */
    var ACCIONES = {

        /* ---------- Alternar estado (flota / rutas) ---------- */
        alternar: function (el, extra) {
            var cuerpo = new FormData();
            cuerpo.append('_token', token());
            cuerpo.append('modulo', el.dataset.sgetModulo);
            cuerpo.append('accion', extra.accion || 'alternarEstado');
            cuerpo.append('id', extra.id);
            if (extra.estado !== undefined) cuerpo.append('estado', extra.estado);

            el.classList.add('sget-cargando');
            enviar(cuerpo, function (j) {
                el.classList.remove('sget-cargando');
                aviso(j);
                if (j.status === 'ok') recargarEn();
            });
        },

        /* ---------- Eliminar ---------- */
        eliminar: function (el, extra) {
            window.SGETModal.confirmar({
                tipo: 'peligro',
                icono: 'fa-trash',
                titulo: el.dataset.sgetTitulo || 'Confirmar eliminación',
                cuerpo: el.dataset.sgetTexto || 'Esta acción no se puede deshacer. ¿Deseas continuar?',
                textoOk: el.dataset.sgetOk || 'Sí, eliminar'
            }).then(function () {
                var cuerpo = new FormData();
                cuerpo.append('_token', token());
                cuerpo.append('modulo', el.dataset.sgetModulo);
                cuerpo.append('accion', 'eliminar');
                cuerpo.append('id', extra.id);
                enviar(cuerpo, function (j) { aviso(j); if (j.status === 'ok') recargarEn(900); });
            });
        },

        /* ---------- Suspender / activar usuario ---------- */
        suspender: function (el, extra) {
            var suspender = extra.estado === 0;
            window.SGETModal.confirmar({
                tipo: suspender ? 'peligro' : 'normal',
                icono: suspender ? 'fa-user-slash' : 'fa-user-check',
                titulo: suspender ? 'Suspender cuenta' : 'Reactivar cuenta',
                cuerpo: suspender
                    ? 'La cuenta de <strong>' + (extra.nombre || 'este usuario') + '</strong> quedará bloqueada para iniciar sesión. ' +
                      'Su historial de viajes y reservas se conserva intacto.'
                    : 'Se restablecerá el acceso al sistema de <strong>' + (extra.nombre || 'este usuario') + '</strong>.',
                textoOk: suspender ? 'Sí, suspender' : 'Sí, reactivar'
            }).then(function () {
                var cuerpo = new FormData();
                cuerpo.append('_token', token());
                cuerpo.append('modulo', 'usuario');
                cuerpo.append('accion', 'cambiarEstado');
                cuerpo.append('id', extra.id);
                cuerpo.append('estado', extra.estado);
                enviar(cuerpo, function (j) { aviso(j); if (j.status === 'ok') recargarEn(); });
            });
        },

        /* ---------- Finalizar viaje ---------- */
        finalizar: function (el, extra) {
            window.SGETModal.confirmar({
                icono: 'fa-flag-checkered',
                titulo: 'Finalizar viaje #' + extra.id,
                cuerpo: 'Se marcará el viaje como <strong>Finalizado</strong> y se liberarán automáticamente ' +
                        'el conductor y el vehículo para que puedan volver a asignarse.',
                textoOk: 'Sí, finalizar',
                claseOkIsDanger: false
            }).then(function () {
                var cuerpo = new FormData();
                cuerpo.append('_token', token());
                cuerpo.append('modulo', 'viaje');
                cuerpo.append('accion', 'finalizar');
                cuerpo.append('id', extra.id);
                enviar(cuerpo, function (j) { aviso(j); if (j.status === 'ok') recargarEn(); });
            });
        },

        /* ---------- Marcar en curso ---------- */
        enCurso: function (el, extra) {
            var cuerpo = new FormData();
            cuerpo.append('_token', token());
            cuerpo.append('modulo', 'viaje');
            cuerpo.append('accion', 'enCurso');
            cuerpo.append('id', extra.id);
            enviar(cuerpo, function (j) { aviso(j); if (j.status === 'ok') recargarEn(); });
        },

        /* ============================================================== */
        /* CANCELAR VIAJE · el flujo crítico del sistema                 */
        /* ============================================================== */
        cancelarViaje: function (el) {
            var datos = JSON.parse(el.getAttribute('data-sget-dato') || '{}');
            var idViaje   = datos.id;
            var instantes = datos.salida || '';
            var yaSalio   = datos.ya_salio === 1 || datos.ya_salio === true;
            var pasajeros = parseInt(datos.pasajeros || '0', 10);

            var opciones = '';
            window.__MOTIVOS_VIAJE__.forEach(function (m) {
                opciones += '<option value="' + m[0] + '">' + m[1] + '</option>';
            });

            window.SGETModal.confirmarMotivo({
                titulo: 'Cancelar viaje #' + idViaje,
                cuerpo: 'Vas a cancelar <strong>' + (datos.trayecto || 'este viaje') + '</strong>' +
                        (instantes ? ' con salida programada <strong>' + instantes + '</strong>' : '') + '. ' +
                        'Esta acción no se puede deshacer.',
                opciones: opciones,
                minimo: datos.minimo || 15,
                esCancelacionPrevia: !yaSalio,
                impacto: pasajeros + (pasajeros === 1 ? ' pasajero' : ' pasajeros'),
                textoOk: 'Sí, cancelar y notificar',
                placeholderAnotacion: yaSalio
                    ? 'Ej.: El viajeCONCLUSION se cerró por cuarto turno del conductor. Queda registrado para el historial.'
                    : 'Ej.: El vehículo presentó una falla en el motor y no puede cumplir la salida programada. Se reprograma para las 14:00 desde el mismo punto de encuentro.'
            }).then(function (r) {
                if (!r) return;
                var cuerpo = new FormData();
                cuerpo.append('_token', token());
                cuerpo.append('modulo', 'viaje');
                cuerpo.append('accion', 'cancelar');
                cuerpo.append('id', idViaje);
                cuerpo.append('motivo', r.motivo);
                cuerpo.append('anotacion', r.anotacion);

                enviar(cuerpo, function (j) {
                    if (j.status === 'ok') {
                        window.SGETModal.confirmar({
                            tipo: 'peligro',
                            icono: 'fa-circle-check',
                            titulo: 'Viaje cancelado',
                            cuerpo: j.mensaje,
                            textoOk: 'Entendido'
                        });
                    } else {
                        aviso(j);
                    }
                    recargarEn(1000);
                });
            });
        },

        /* ---------- Marcar notificación como leída ---------- */
        leerNotificacion: function (el, extra) {
            var cuerpo = new FormData();
            cuerpo.append('_token', token());
            cuerpo.append('modulo', 'notificacion');
            cuerpo.append('accion', 'leer');
            cuerpo.append('id', extra.id);
            enviar(cuerpo);
        }
    };

    function prepararAcciones() {
        document.querySelectorAll('[data-sget-accion]').forEach(function (el) {
            if (el.hasAttribute('data-sget-listo')) return;
            el.setAttribute('data-sget-listo', '1');

            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                var accion = el.dataset.sgetAccion;
                var extra  = {};
                try { extra = JSON.parse(el.getAttribute('data-sget-dato') || '{}'); } catch (e) {}
                extra.nombre = el.dataset.nombre || extra.nombre;

                if (ACCIONES[accion]) ACCIONES[accion](el, extra);
                else console.warn('[SGET] Acción desconocida:', accion);
            });
        });
    }

    /* ================================================================== */
    /* PESTAÑAS                                                           */
    /* ================================================================== */
    function prepararPestanas() {
        document.querySelectorAll('[data-sget-tab]').forEach(function (btn) {
            if (btn.hasAttribute('data-sget-listo')) return;
            btn.setAttribute('data-sget-listo', '1');

            btn.addEventListener('click', function () {
                var grupo = btn.closest('[data-sget-tabgrupo]') || document;
                grupo.querySelectorAll('[data-sget-tab]').forEach(function (b) {
                    b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
                });
                document.querySelectorAll('[data-sget-panel-id]').forEach(function (p) {
                    p.hidden = p.dataset.sgetPanelId !== btn.dataset.sgetTab;
                });
            });
        });
    }

    /* ================================================================== */
    /* ARRANQUE                                                            */
    /* ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        window.__MOTIVOS_VIAJE__ = window.__MOTIVOS_VIAJE__ || [];
        prepararModales();
        prepararFormularios();
        prepararAcciones();
        prepararPestanas();
    });
})(window, document);
