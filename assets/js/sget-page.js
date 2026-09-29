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

    /** FormData común para las acciones puntuales del API. */
    function cuerpoDeAccion(extra, accion) {
        var cuerpo = new FormData();
        cuerpo.append('_token', token());
        cuerpo.append('modulo', extra.modulo || '');
        cuerpo.append('accion', accion);
        cuerpo.append('id', extra.id);
        return cuerpo;
    }

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

                    // La vista previa de una imagen sobrevive a form.reset() (es un
                    // <div> con estilo y un <img>, no un campo): al abrir «Nuevo
                    // anuncio» después de editar uno, se veía la foto del anuncio
                    // anterior aunque no hubiera ningún archivo elegido. Se limpia
                    // aquí para que no se pueda guardar creyendo que está puesta.
                    //   · data-sget-preview-caja  -> la caja de la previsualización
                    //   · data-sget-src           -> el <img> que la muestra
                    //   · data-sget-preview-nombre-> el nombre del archivo
                    form.querySelectorAll('[data-sget-preview-caja]').forEach(function (caja) {
                        caja.style.display = 'none';
                        caja.hidden = false;
                    });
                    form.querySelectorAll('[data-sget-preview-nombre]').forEach(function (n) {
                        n.textContent = '';
                    });
                    form.querySelectorAll('[data-sget-src]').forEach(function (img) {
                        img.removeAttribute('src');
                    });

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

        /* ---------- Publicar / destacar un anuncio de la landing ---------- */
        anuncioEstado: function (el, extra) {
            enviar(cuerpoDeAccion(extra, 'alternarEstado')).then(function (j) {
                aviso(j);
                if (j.status === 'ok') recargarEn(700);
            });
        },

        anuncioDestacado: function (el, extra) {
            enviar(cuerpoDeAccion(extra, 'alternarDestacado')).then(function (j) {
                aviso(j);
                if (j.status === 'ok') recargarEn(700);
            });
        },

        anuncioEliminar: function (el, extra) {
            window.SGETModal.confirmar({
                tipo: 'peligro',
                icono: 'fa-trash',
                titulo: el.dataset.sgetTitulo || 'Eliminar anuncio',
                cuerpo: el.dataset.sgetTexto || 'Se eliminará el anuncio y su imagen de la landing.',
                textoOk: 'Sí, eliminar'
            }).then(function () {
                enviar(cuerpoDeAccion(extra, 'eliminar')).then(function (j) {
                    aviso(j);
                    if (j.status === 'ok') recargarEn(700);
                });
            });
        },

        /* ---------- Actualizar / eliminar un reporte de pasajero ---------- */
        guardarReporte: function (el, extra) {
            // El estado y el viaje se eligen en la MISMA fila del reporte, así
            // que se leen desde su <tr> y no desde un formulario aparte.
            var fila = el.closest('[data-sget-fila]') || document;
            var estado = fila.querySelector('[data-sget-estado-reporte="' + extra.id + '"]');
            var viaje  = fila.querySelector('[data-sget-viaje-reporte="' + extra.id + '"]');

            var cuerpo = new FormData();
            cuerpo.append('_token', token());
            cuerpo.append('modulo', extra.modulo || 'reporte');
            cuerpo.append('accion', 'actualizar');
            cuerpo.append('id', extra.id);
            cuerpo.append('estado', estado ? estado.value : 'pendiente');
            cuerpo.append('id_via', viaje ? viaje.value : 0);

            el.classList.add('sget-cargando');
            enviar(cuerpo, function (j) {
                el.classList.remove('sget-cargando');
                aviso(j);
                if (j.status === 'ok') recargarEn(800);
            });
        },

        eliminarReporte: function (el, extra) {
            window.SGETModal.confirmar({
                tipo: 'peligro',
                icono: 'fa-trash',
                titulo: el.dataset.sgetTitulo || 'Eliminar reporte',
                cuerpo: el.dataset.sgetTexto || 'Se eliminará el reporte de forma permanente.',
                textoOk: 'Sí, eliminar'
            }).then(function () {
                var cuerpo = new FormData();
                cuerpo.append('_token', token());
                cuerpo.append('modulo', 'reporte');
                cuerpo.append('accion', 'eliminar');
                cuerpo.append('id', extra.id);
                enviar(cuerpo, function (j) { aviso(j); if (j.status === 'ok') recargarEn(800); });
            });
        },

        /* ---------- Notificaciones ----------
           El manejador real vive en assets/js/sget-notificaciones.js, que carga
           el propio buzón (campanita) en TODAS las páginas. Aquí no se hace
           nada a propósito: si se manejara también aquí, cada clic dispararía
           dos peticiones y, en las páginas de Pasajero y Conductor, donde este
           archivo ni siquiera se carga, el botón quedaría muerto. */
        leerNotificacion: function () { /* lo resuelve sget-notificaciones.js */ }
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
                extra.modulo = el.dataset.sgetModulo || extra.modulo;

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
    /* BÚSQUEDA Y FILTROS DE LA BARRA DE HERRAMIENTAS                       */
    /* ================================================================== */
    /**
     * Busca por texto y filtra por estado sin que cada página escriba su
     * propio <script> al final.
     *
     * POR QUÉ ESTÁ AQUÍ Y NO EN CADA PÁGINA
     *   Admin/anuncios.php traía la barra con buscador y con los botones
     *   Todos / Visibles / Ocultos… y NO TENÍA NINGÚN MANEJADOR: los botones
     *   no hacían nada y el buscador tampoco filtraba. La misma barra se había
     *   cableado a mano en otros listados, así que el comportamiento se
     *  tipico se hacia aquí una vez y todas las páginas lo heredan.
     *
     * CÓMO SE DECLARA EN EL HTML
     *   <input id="buscarX" data-sget-buscar>
     *   <button data-sget-filtro="1" data-sget-filtro-de="data-estado">Visibles</button>
     *   <article data-sget-fila data-estado="1"> …
     *   <p data-sget-sin-resultados hidden>Ningún anuncio coincide.</p>
     */
    function prepararBusquedaYFiltros() {
        var filas = document.querySelectorAll('[data-sget-fila]');
        if (!filas.length) return;

        var vacio = document.querySelector('[data-sget-sin-resultados]');

        /* --- Texto ---------------------------------------------------- */
        var buscador = document.querySelector('[data-sget-buscar]');
        if (buscador) {
            var aplicar = function () {
                var q = buscador.value.trim().toLowerCase();
                var visibles = 0;

                filas.forEach(function (fila) {
                    var coincide = q === '' || fila.textContent.toLowerCase().indexOf(q) > -1;
                    if (coincide && fila.dataset.sgetFiltroActivo !== '1') coincide = false;
                    fila.hidden = !coincide;
                    if (coincide) visibles++;
                });

                if (vacio) vacio.hidden = visibles > 0;
            };

            buscador.addEventListener('input', aplicar);

            // Ctrl+K / Cmd+K lleva al buscador: es lo que dice el placeholder.
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    buscador.focus();
                    buscador.select();
                }
            });
        }

        /* --- Estado ---------------------------------------------------- */
        var chips = document.querySelectorAll('[data-sget-filtro]');
        chips.forEach(function (chip) {
            var attr = chip.dataset.sgetFiltroDe || 'data-estado';
            var valor = chip.dataset.sgetFiltro;

            if (valor === '*') chip.setAttribute('aria-pressed', 'true');

            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
                chip.setAttribute('aria-pressed', 'true');

                filas.forEach(function (fila) {
                    var pasa = valor === '*' || fila.getAttribute(attr) === valor;
                    fila.dataset.sgetFiltroActivo = pasa ? '1' : '0';
                });

                if (buscador) buscador.dispatchEvent(new Event('input'));
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
        prepararBusquedaYFiltros();
        if (typeof SGET.initDuracionRuta === 'function') SGET.initDuracionRuta();
    });

    /* Se exponen las acciones y la API en window para:
       · depurar desde la consola del navegador
       · poder invocarlas desde las sondas de prueba (pruebas/*.js)
       Nada del flujo normal depende de esto. */
    window.SGETPagina   = { API: API, SGET: SGET };
    window.SGETAcciones = ACCIONES;
})(window, document);
