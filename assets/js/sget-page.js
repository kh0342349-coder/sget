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

    /* Utilidades compartidas por los módulos */
    var SGET = {};

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
    /* DURACIÓN DEL TRAYECTO → cuándo se cierra cada viaje                  */
    /* ================================================================== */

    /**
     * Al escribir la distancia se estima la duración (45 km/h) y se muestra
     * en formato legible. Si el usuario escribe la duración a mano, se respeta.
     */
    SGET.initDuracionRuta = function () {
        var dis      = document.getElementById('ruta_dis');
        var dur      = document.getElementById('ruta_dur');
        var legible  = document.querySelector('[data-duracion-legible]');
        if (!dis || !dur || !legible) return;

        var tocadoDuracion = false;
        dur.addEventListener('input', function () { tocadoDuracion = true; });

        function pintar() {
            var m = parseInt(dur.value, 10);
            if (isNaN(m) || m <= 0) { legible.textContent = 'Sin definir'; return; }
            var h = Math.floor(m / 60), r = m % 60;
            legible.textContent = h === 0 ? (r + ' min') : (r === 0 ? (h + ' h') : (h + ' h ' + r + ' min'));
        }

        dis.addEventListener('input', function () {
            var km = parseFloat(dis.value);
            if (!isNaN(km) && km > 0 && !tocadoDuracion) {
                dur.value = Math.max(30, Math.round((km / 45) * 60));
            }
            pintar();
        });

        dur.addEventListener('input', pintar);
        pintar();
    };

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

                // Reglas de negocio del diálogo de cancelación, antes de enviar.
                if (form.dataset.sgetAnotacionObligatoria === '1') {
                    var errores = validarCancelacion(form);
                    if (Object.keys(errores).length) {
                        window.SGETModal.errores(errores, form);
                        window.SGETModal.toast('Revisa los campos marcados en rojo.', 'error');
                        return;
                    }
                }

                enviar(new FormData(form), function (j) {
                    if (j.status === 'ok') {
                        if (form.dataset.sgetCerrarAlGuardar) window.SGETModal.cerrar(form.dataset.sgetCerrarAlGuardar);
                        window.SGETModal.toast(j.mensaje, 'exito');
                        recargarEn();
                    } else {
                        if (j.errores) window.SGETModal.errores(j.errores, form);
                        aviso(j);
                    }
                });
            });

            // Contador y aviso en vivo de la anotación obligatoria
            var area = form.querySelector('[data-anotacion]');
            var contador = form.querySelector('[data-contador]');
            if (area && contador) {
                var minimo = parseInt(form.dataset.sgetMinAnotacion, 10) || 15;
                var obligatorio = form.dataset.sgetAnotacionObligatoria === '1';
                area.addEventListener('input', function () {
                    var n = area.value.trim().length;
                    contador.textContent = n + '/' + minimo;
                    contador.dataset.ok = (n >= minimo) ? '1' : '0';
                    area.classList.toggle('sget-textarea--error', obligatorio && n > 0 && n < minimo);
                    if (n >= minimo) {
                        var err = form.querySelector('[data-error-anotacion]');
                        if (err) err.dataset.visible = '0';
                    }
                });
            }

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
        /* CANCELAR VIAJE                                                 */
        /* El diálogo lo construye views/modals/cancelar-viaje.php y lo envía
           con data-sget-form (el flujo estándar de formularios). Esta acción
           solo se limita a abrirlo: no hay una segunda implementación. */
        cancelarViaje: function (el) {
            var capa = document.getElementById('modalCancelarViaje');
            if (!capa) {
                console.warn('[SGET] Falta views/modals/cancelar-viaje.php en la página');
                return;
            }

            var d;
            try { d = JSON.parse(el.getAttribute('data-sget-dato') || '{}'); }
            catch (e) { console.warn('[SGET] data-sget-dato inválido', el); return; }

            var vencido   = d.vencido === 1 || d.vencido === true;
            var yaSalio   = d.ya_salio === 1 || d.ya_salio === true;
            var pasajeros = parseInt(d.pasajeros || '0', 10);
            var S = function (v) { return v === 0 ? '0' : '1'; };

            var impacto = pasajeros === 0
                ? 'No hay pasajeros reservados, así que no se enviará ningún aviso.'
                : (yaSalio
                    ? 'recibirán el aviso de cierre de esta salida.'
                    : 'serán notificados de la cancelación y sus reservas quedarán canceladas sin cobro.');

            // Se limpia ANTES de abrir: si se limpiara después, al reabrir el
            // modal podrían quedar valores del viaje anterior.
            var formPre = capa.querySelector('[data-sget-panel]');
            if (formPre) formPre.reset();
            window.SGETModal.limpiarErrores(formPre);
            var areaPre = capa.querySelector('[data-anotacion]');
            if (areaPre) { areaPre.value = ''; areaPre.classList.remove('sget-textarea--error'); }
            var errPre = capa.querySelector('[data-error-anotacion]');
            if (errPre) errPre.dataset.visible = '0';
            var contPre = capa.querySelector('[data-contador]');
            if (contPre) { contPre.textContent = '0/15'; contPre.dataset.ok = '0'; }

            window.SGETModal.abrir('modalCancelarViaje', {
                datos: {
                    id:            d.id,
                    titulo:        vencido ? ('Viaje #' + d.id + ' vencido') : ('Cancelar viaje #' + d.id),
                    trayecto:      d.trayecto || '',
                    salida:        d.salida || 'Sin definir',
                    vence:         d.vence || 'Sin definir',
                    duracion:      d.duracion || 'Sin definir',
                    conductor:     d.conductor || 'Sin asignar',
                    placa:         d.placa || 'Sin placa',
                    pasajeros:     pasajeros,
                    impacto:       impacto,
                    etiquetaAnotacion: yaSalio
                        ? 'Anotación para el archivo (opcional)'
                        : 'Anotación para los pasajeros (obligatoria)',
                    textoAnotacion: yaSalio
                        ? 'El viaje ya salió; la anotación se archiva con el cierre para trazabilidad.'
                        : 'El viaje aún no sale, por lo que SGET <strong>exige</strong> esta anotación y la envía literalmente a cada pasajero reservado.',
                    // Visibilidad según el estado del viaje
                    iconoBan:       S(vencido ? 0 : 1),
                    iconoVencido:   S(vencido ? 1 : 0),
                    avisoVencido:   S(vencido ? 1 : 0),
                    zonaFormulario: S(vencido ? 0 : 1),
                    cajaAnotacion:  S(1),
                    botonCancelar:  S(vencido ? 0 : 1),
                    botonEntendido: S(vencido ? 1 : 0),
                }
            });

            // La obligatoriedad de la anotación depende del momento de la salida
            var form = capa.querySelector('[data-sget-form]');
            if (form) form.dataset.sgetAnotacionObligatoria = yaSalio ? '0' : '1';
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

    /** Validación del formulario de cancelación (reglas de negocio). */
    function validarCancelacion(form) {
        var errores = {};
        var minimo  = parseInt(form.dataset.sgetMinAnotacion, 10) || 15;

        var motivo = form.querySelector('[name="motivo"]');
        if (motivo && !motivo.value) errores.motivo = 'Selecciona el motivo de la cancelación.';

        var anotacion = form.querySelector('[name="anotacion"]');
        if (anotacion && anotacion.value.trim().length < minimo) {
            errores.anotacion_cancelacion =
                'La anotación es obligatoria: escribe al menos ' + minimo +
                ' caracteres explicando por qué se cancela. Se enviará a los pasajeros.';
        }

        var confirmo = form.querySelector('[name="confirmo"]');
        if (confirmo && !confirmo.checked) errores.confirmo = 'Debes confirmar que entiendes la cancelación.';

        return errores;
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
        prepararModales();
        prepararFormularios();
        prepararAcciones();
        prepararPestanas();
        if (typeof SGET.initDuracionRuta === 'function') SGET.initDuracionRuta();
    });

    /* Se exponen las acciones y la API en window para:
       · depurar desde la consola del navegador
       · poder invocarlas desde las sondas de prueba (pruebas/*.js)
       Nada del flujo normal depende de esto. */
    window.SGETPagina   = { API: API, SGET: SGET };
    window.SGETAcciones = ACCIONES;
})(window, document);
