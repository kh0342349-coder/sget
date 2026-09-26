/* ==========================================================================
   assets/js/sget-modal.js
   ------------------------------------------------------------------------------
   MOTOR DE MODALES SGET
   ------------------------------------------------------------------------------
   Reemplaza las ~300 líneas de JavaScript inline repetidas en cada módulo.
   Antes: cada página definía abrirModalX/cerrarModalX con toggles de clases
   Tailwind, sin Escape, sin foco atrapado, sin scroll bloqueado y sin estado.
   Ahora: un solo controlador con API declarativa.

   API
     SGETModal.abrir(id)                -> abre por id
     SGETModal.cerrar(id)               -> cierra
     SGETModal.toggle(id)               -> abre/cierra
     SGETModal.estaAbierto(id)          -> bool
     SGETModal.confirmar(opts)          -> diálogo con motivo opcional
     SGETModal.confirmarMotivo(opts)    -> diálogo con anotación OBLIGATORIA
     SGETModal.toast(mensaje, tipo)     -> notificación efímera
     SGETModal.errores(campos)          -> pinta errores de validación
     SGETModal.limpiarErrores()         -> limpia el formulario activo
     SGETModal.enfocar(selector)        -> foco en un campo

   Atributos declarativos (HTML):
     data-sget-modal="id"        abre el modal/drawer con ese id al hacer click
     data-sget-cerrar="#id"      cierra el indicado (o el propio, si no se pone)
     data-sget-confirmar="..."   acción destructiva con confirmación
   ========================================================================== */
(function (window, document) {
    'use strict';

    var FOCO_SELECTOR = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    var Modal = {

        /* ---------------------------------------------------------------- */
        /* Token anti-CSRF (lo inyecta el backend en el primer formulario)     */
        /* ---------------------------------------------------------------- */
        get __token() {
            var campo = document.querySelector('input[name="_token"]');
            return campo ? campo.value : '';
        },

        /* ---------------------------------------------------------------- */
        /* Registro / estado                                                */
        /* ---------------------------------------------------------------- */
        pila: [],          // ids abiertos, en orden de apertura
        _porId: function (id) { return document.getElementById(id); },
        _todos: function () { return Array.prototype.slice.call(document.querySelectorAll('[data-sget-capa]')); },

        /* ---------------------------------------------------------------- */
        /* Abrir / cerrar                                                   */
        /* ---------------------------------------------------------------- */
        abrir: function (id, opciones) {
            var el = this._porId(id);
            if (!el) { console.warn('[SGETModal] No existe el modal #' + id); return null; }

            opciones = opciones || {};
            var datos = typeof opciones.datos === 'object' && opciones.datos ? opciones.datos : null;

            if (el.dataset.previo === undefined || datos) {
                el.dataset.previo = el.innerHTML;   // respaldo para restaurar
            }
            if (datos) { this._poblar(el, datos); }

            el.dataset.abierto = '1';
            el.setAttribute('aria-hidden', 'false');
            if (this.pila.indexOf(id) === -1) this.pila.push(id);

            document.body.classList.add('sget-modal-abierto');
            this._activarOverlay(true);

            // Foco en el primer campo útil
            setTimeout(function () {
                var foco = el.querySelector('[data-sget-autofocus]') || el.querySelector(FOCO_SELECTOR);
                if (foco && !el.matches(':focus-within')) { try { foco.focus({ preventScroll: true }); } catch (e) { foco.focus(); } }
            }, 60);

            this._anunciar('Se abrió el diálogo: ' + (el.dataset.titulo || id));
            return el;
        },

        cerrar: function (id, restaurar) {
            var el = typeof id === 'string' ? this._porId(id) : id;
            if (!el) return;
            el.dataset.abierto = '0';
            el.setAttribute('aria-hidden', 'true');

            if (restaurar !== false && el.dataset.previo !== undefined) {
                el.innerHTML = el.dataset.previo;
            }
            delete el.dataset.previo;

            var i = this.pila.indexOf(el.id);
            if (i > -1) this.pila.splice(i, 1);
            if (!this.pila.length) {
                document.body.classList.remove('sget-modal-abierto');
            }
            this._activarOverlay(true);

            // Devolver el foco al elemento que abrió el modal
            if (el._origen && document.contains(el._origen)) {
                try { el._origen.focus({ preventScroll: true }); } catch (e) {}
            }
        },

        toggle: function (id) {
            var el = this._porId(id);
            if (!el) return;
            if (el.dataset.abierto === '1') { this.cerrar(id); }
            else { this.abrir(id); }
        },

        estaAbierto: function (id) {
            var el = this._porId(id);
            return !!el && el.dataset.abierto === '1';
        },

        cerrarTodos: function () {
            var self = this;
            this.pila.slice().forEach(function (id) { self.cerrar(id); });
        },

        /* ---------------------------------------------------------------- */
        /* Overlay compartido                                                */
        /* ---------------------------------------------------------------- */
        _activarOverlay: function (on) {
            document.querySelectorAll('.sget-overlay').forEach(function (ov) {
                var usaOverlay = true;
                // Si el overlay tiene data-sget-propio, se controla desde la capa
                if (ov.dataset.sgetVinculado === undefined) {
                    usaOverlay = Modal.pila.length > 0;
                } else {
                    usaOverlay = Modal._algunaVinculada(ov.dataset.sgetVinculado, on);
                }
                ov.dataset.abierto = usaOverlay ? '1' : '0';
            });
        },

        _algunaVinculada: function (lista, on) {
            return lista.split(',').some(function (id) {
                var el = Modal._porId(id.trim());
                return el && el.dataset.abierto === '1';
            });
        },

        /* ---------------------------------------------------------------- */
        /* Poblado declarativo del modal                                      */
        /* ---------------------------------------------------------------- */
        _poblar: function (el, datos) {
            var self = this;
            Object.keys(datos).forEach(function (campo) {
                var valor = datos[campo];
                if (valor === null || valor === undefined) valor = '';

                // 1) data-sget-campo="nombre" en inputs, selects y textareas
                el.querySelectorAll('[data-sget-campo="' + campo + '"]').forEach(function (campoEl) {
                    if (campoEl.type === 'checkbox') { campoEl.checked = valor === true || valor === 1 || valor === '1' || valor === 'true'; }
                    else { campoEl.value = valor; }
                });

                // 2) data-sget-texto="nombre" en cualquier nodo
                el.querySelectorAll('[data-sget-texto="' + campo + '"]').forEach(function (nodo) {
                    nodo.textContent = valor;
                });

                // 3) data-sget-html="nombre" (solo para HTML ya escapado por el servidor)
                el.querySelectorAll('[data-sget-html="' + campo + '"]').forEach(function (nodo) {
                    nodo.innerHTML = valor;
                });

                // 4) data-sget-clase="nombre" = valor  →  alterna clases
                el.querySelectorAll('[data-sget-clase="' + campo + '"]').forEach(function (nodo) {
                    nodo.className = nodo.className.replace(/\bsget-badge--\S+/g, '').trim() + ' ' + valor;
                });

                // 5) data-sget-mostrar="nombre:0|1" → show/hide
                el.querySelectorAll('[data-sget-mostrar="' + campo + '"]').forEach(function (nodo) {
                    var flag = String(valor);
                    nodo.hidden = (flag === '0' || flag === '' || flag === 'false');
                });
            });
        },

        /* ---------------------------------------------------------------- */
        /* Validación visual                                                  */
        /* ---------------------------------------------------------------- */
        limpiarErrores: function (raiz) {
            (raiz || document).querySelectorAll('[data-campo]').forEach(function (f) {
                f.removeAttribute('data-invalid');
                var err = f.querySelector('.sget-error');
                if (err) { err.dataset.visible = '0'; err.textContent = ''; }
            });
            (raiz || document).querySelectorAll('.sget-input--error, .sget-select--error, .sget-textarea--error')
                .forEach(function (i) { i.classList.remove('sget-input--error', 'sget-select--error', 'sget-textarea--error'); });
        },

        errores: function (campos, raiz) {
            var self = this;
            this.limpiarErrores(raiz);
            var primero = null;
            Object.keys(campos || {}).forEach(function (campo) {
                var envoltura = (raiz || document).querySelector('[data-campo="' + campo + '"]')
                             || (raiz || document).getElementById(campo);
                if (!envoltura) return;
                envoltura.setAttribute('data-invalid', '1');
                var entrada = envoltura.querySelector('.sget-input, .sget-select, .sget-textarea');
                if (entrada) entrada.classList.add(entrada.classList.contains('sget-select') ? 'sget-select--error' : 'sget-input--error');
                var err = envoltura.querySelector('.sget-error');
                if (err) { err.textContent = campos[campo]; err.dataset.visible = '1'; }
                if (!primero) primero = entrada || envoltura;
            });
            if (primero) { try { primero.focus({ preventScroll: true }); primero.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {} }
        },

        /* ---------------------------------------------------------------- */
        /* Toasts                                                            */
        /* ---------------------------------------------------------------- */
        toast: function (mensaje, tipo, ms) {
            if (!mensaje) return;
            tipo = tipo || 'info';
            var zona = document.querySelector('.sget-toast-zona');
            if (!zona) {
                zona = document.createElement('div');
                zona.className = 'sget-toast-zona';
                zona.setAttribute('role', 'status');
                zona.setAttribute('aria-live', 'polite');
                document.body.appendChild(zona);
            }
            var iconos = { exito: 'fa-check-circle', error: 'fa-triangle-exclamation', aviso: 'fa-circle-exclamation', info: 'fa-circle-info' };
            var t = document.createElement('div');
            t.className = 'sget-toast sget-toast--' + tipo;
            t.innerHTML = '<i class="fas ' + (iconos[tipo] || iconos.info) + '"></i><span></span>';
            t.querySelector('span').textContent = mensaje;
            zona.appendChild(t);

            setTimeout(function () {
                t.classList.add('sget-toast--saliendo');
                setTimeout(function () { t.remove(); }, 260);
            }, ms || 4500);
        },

        /* ---------------------------------------------------------------- */
        /* Confirmación simple                                               */
        /* ---------------------------------------------------------------- */
        confirmar: function (opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                var id = opts.id || 'sgetConfirm';
                var wrap = Modal._crearConfirmacion(id, {
                    icono: 'fa-' + (opts.icono || 'fa-triangle-exclamation'),
                    claseIcono: opts.tipo === 'peligro' ? 'sget-modal__icono--peligro' : 'sget-modal__icono--aviso',
                    titulo: opts.titulo || '¿Confirmas la acción?',
                    cuerpo: opts.cuerpo || '',
                    textoOk: opts.textoOk || 'Sí, continuar',
                    claseOk: opts.tipo === 'peligro' ? 'sget-btn--peligro' : 'sget-btn--primario',
                    extras: ''
                });
                Modal._enlazarConfirmacion(wrap, function () { resolve(true); });
            });
        },

        /* ---------------------------------------------------------------- */
        /* Confirmación con ANOTACIÓN OBLIGATORIA (cancelación de viajes)    */
        /* ---------------------------------------------------------------- */
        confirmarMotivo: function (opts) {
            opts = opts || {};
            var minimo = opts.minimo || 15;
            return new Promise(function (resolve) {
                var id = opts.id || 'sgetConfirmMotivo';
                var esCancelacionPrevia = opts.esCancelacionPrevia === true;

                var cuerpo =
                    '<p class="sget-confirm__texto">' + (opts.cuerpo || '') + '</p>' +
                    (opts.opciones ? '<div class="sget-field" style="margin-top:1rem">' +
                        '<label class="sget-label">' + (opts.etiquetaMotivo || 'Motivo de la cancelación') +
                        '<span class="sget-label__req">*</span></label>' +
                        '<select class="sget-select" data-sget-motivo>' + opts.opciones + '</select></div>' : '') +

                    '<div class="sget-confirm__caja">' +
                        '<div class="sget-confirm__titulo-caja">' +
                            '<i class="fas fa-pen-to-square"></i>' +
                            'Anotación para los pasajeros ' + (esCancelacionPrevia ? '(obligatoria)' : '(recomendada)') +
                            '<span class="sget-confirm__contador" data-contador>0/' + minimo + '</span>' +
                        '</div>' +
                        '<textarea class="sget-textarea" rows="4" maxlength="500" data-sget-anotacion ' +
                            'placeholder="' + (opts.placeholderAnotacion ||
                                'Ej.: El vehículo presentó una falla en el motor y no puede cumplir la salida programada. Estamos reprogramando el despacho para las 14:00.') + '"></textarea>' +
                        '<p class="sget-help" style="margin-top:.5rem">' +
                            (esCancelacionPrevia
                                ? 'Este viaje <strong>aún no sale</strong>: la anotación es obligatoria y se enviará a todos los pasajeros reservados.'
                                : 'El viaje ya salió; la anotación se archiva con el cierre y ayuda a los pasajeros a reclamar.') +
                        '</p>' +
                        '<div class="sget-error" data-anotacion-error style="margin-top:.5rem">' +
                            '<i class="fas fa-circle-exclamation"></i><span></span></div>' +
                    '</div>' +

                    (opts.impacto
                        ? '<div class="sget-impacto"><span class="sget-impacto__chip">' +
                            '<i class="fas fa-users"></i>' + opts.impacto + '</span>' +
                            '<span>serán notificados automáticamente.</span></div>'
                        : '');

                var wrap = Modal._crearConfirmacion(id, {
                    icono: 'fa-ban',
                    claseIcono: 'sget-modal__icono--peligro',
                    titulo: opts.titulo || 'Confirmar cancelación',
                    cuerpo: cuerpo,
                    textoOk: opts.textoOk || 'Sí, cancelar el viaje',
                    claseOk: 'sget-btn--peligro',
                    extras: 'sget-btn--neutro',
                    minimo: minimo,
                    esCancelacionPrevia: esCancelacionPrevia
                });

                Modal._enlazarConfirmacion(wrap, function (form) {
                    var anotacion = (form.querySelector('[data-sget-anotacion]').value || '').trim();
                    var motivoEl  = form.querySelector('[data-sget-motivo]');
                    var motivo    = motivoEl ? motivoEl.value : (opts.motivoPorDefecto || '');

                    if (esCancelacionPrevia && anotacion.length < minimo) {
                        var err = form.querySelector('[data-anotacion-error]');
                        err.querySelector('span').textContent =
                            'La anotación es obligatoria: escribe al menos ' + minimo + ' caracteres explicando por qué se cancela el viaje.';
                        err.dataset.visible = '1';
                        form.querySelector('[data-sget-anotacion]').classList.add('sget-textarea--error');
                        form.querySelector('[data-sget-anotacion]').focus();
                        return;   // no resuelve: el diálogo sigue abierto
                    }
                    resolve({ motivo: motivo, anotacion: anotacion });
                });
            });
        },

        /* ---------------------------------------------------------------- */
        /* Utilidades internas de los diálogos                                 */
        /* ---------------------------------------------------------------- */
        _crearConfirmacion: function (id, cfg) {
            var wrap = this._porId(id);
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = id;
                wrap.className = 'sget-modal-wrap sget-confirm';
                wrap.setAttribute('role', 'dialog');
                wrap.setAttribute('aria-modal', 'true');
                wrap.setAttribute('data-sget-capa', '');
                wrap.innerHTML =
                    '<div class="sget-overlay" data-sget-propio="__propio__"></div>' +
                    '<div class="sget-modal sget-modal--sm" data-sget-panel>' +
                        '<div class="sget-modal__head">' +
                            '<div class="sget-modal__titulo">' +
                                '<span class="sget-modal__icono ' + cfg.claseIcono + '"><i class="fas ' + cfg.icono + '"></i></span>' +
                                '<span data-titulo></span>' +
                            '</div>' +
                            '<button type="button" class="sget-modal__cerrar" data-sget-cancelar aria-label="Cerrar">' +
                                '<i class="fas fa-times"></i></button>' +
                        '</div>' +
                        '<div class="sget-modal__body" data-sget-cuerpo></div>' +
                        '<div class="sget-modal__foot">' +
                            '<button type="button" class="sget-btn ' + (cfg.extras || 'sget-btn--neutro') + '" data-sget-cancelar>Cancelar</button>' +
                            '<button type="button" class="sget-btn ' + cfg.claseOk + '" data-sget-ok>' + cfg.textoOk + '</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(wrap);
            }
            wrap.querySelector('[data-titulo]').textContent = cfg.titulo;
            wrap.querySelector('[data-sget-cuerpo]').innerHTML = cfg.cuerpo;
            var ok = wrap.querySelector('[data-sget-ok]');
            ok.className = 'sget-btn ' + cfg.claseOk;
            ok.textContent = cfg.textoOk;

            this._prepararAnotacion(wrap, cfg);
            this.abrir(id);
            return wrap;
        },

        _prepararAnotacion: function (wrap, cfg) {
            var self = this;
            var area  = wrap.querySelector('[data-sget-anotacion]');
            var conta = wrap.querySelector('[data-contador]');
            if (!area) return;

            area.addEventListener('input', function () {
                var n = area.value.trim().length;
                if (conta) {
                    conta.textContent = n + '/' + cfg.minimo;
                    conta.dataset.ok = (n >= cfg.minimo) ? '1' : '0';
                }
                area.classList.toggle('sget-textarea--error', cfg.esCancelacionPrevia && n > 0 && n < cfg.minimo);
                var err = wrap.querySelector('[data-anotacion-error]');
                if (err && n >= cfg.minimo) err.dataset.visible = '0';
            });
        },

        _enlazarConfirmacion: function (wrap, alAceptar) {
            var self = this;
            var id = wrap.id;

            function cerrar() { Modal.cerrar(id); }

            wrap.querySelectorAll('[data-sget-cancelar]').forEach(function (b) {
                b.onclick = cerrar;
            });
            var ov = wrap.querySelector('.sget-overlay');
            if (ov) ov.onclick = cerrar;
            wrap.querySelector('[data-sget-ok]').onclick = function () {
                alAceptar(wrap, function () { cerrar(); });
            };
            // Evita que se acumulen manejadores si el diálogo se reutiliza
            wrap.dataset.enlazado = '1';
        },

        /* ---------------------------------------------------------------- */
        /* Accesibilidad                                                     */
        /* ---------------------------------------------------------------- */
        _anunciar: function (texto) {
            var live = document.getElementById('sgetLive');
            if (!live) {
                live = document.createElement('div');
                live.id = 'sgetLive';
                live.className = 'sget-solo-lectores';
                live.setAttribute('aria-live', 'polite');
                document.body.appendChild(live);
            }
            live.textContent = texto;
        },

        _atraparFoco: function (e) {
            if (e.key !== 'Tab' || !this.pila.length) return;
            var capa = this._porId(this.pila[this.pila.length - 1]);
            if (!capa) return;
            var focos = Array.prototype.slice.call(capa.querySelectorAll(FOCO_SELECTOR))
                .filter(function (el) { return el.offsetParent !== null; });
            if (!focos.length) return;
            var primero = focos[0];
            var ultimo  = focos[focos.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        }
    };

    /* ===================================================================== */
    /* Arranque: delegación de eventos + atajos                             */
    /* ===================================================================== */
    document.addEventListener('DOMContentLoaded', function () {

        // Cualquier elemento con data-sget-modal abre su destino
        document.addEventListener('click', function (e) {
            var disparador = e.target.closest('[data-sget-modal]');
            if (disparador) {
                e.preventDefault();
                var destino = Modal._porId(disparador.dataset.sgetModal);
                if (destino) { destino._origen = disparador; Modal.abrir(destino.id, { datos: Modal._datosDe(disparador) }); }
                return;
            }

            // data-sget-cerrar (propio o indicado)
            var cerrador = e.target.closest('[data-sget-cerrar]');
            if (cerrador) {
                e.preventDefault();
                var objetivo = cerrador.dataset.sgetCerrar;
                if (objetivo) { Modal.cerrar(objetivo); }
                else {
                    var capa = cerrador.closest('[data-sget-capa]');
                    if (capa) Modal.cerrar(capa.id);
                }
                return;
            }

            // Overlay: cierra la capa que lo contiene
            var overlay = e.target.closest('.sget-overlay');
            if (overlay) {
                if (overlay.dataset.sgetVinculado) return;
                var capaOv = overlay.closest('[data-sget-capa]');
                if (capaOv) Modal.cerrar(capaOv.id);
            }
        });

        // Escape cierra la capa superior
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && Modal.pila.length) {
                e.preventDefault();
                Modal.cerrar(Modal.pila[Modal.pila.length - 1]);
            }
            Modal._atraparFoco(e);
        });

        // Marca los overlays con data-sget-vinculado para el gestión de z-index
        document.querySelectorAll('.sget-overlay').forEach(function (ov) {
            if (!ov.dataset.sgetVinculado) {
                var padre = ov.closest('[data-sget-capa]');
                if (padre) ov.dataset.sgetVinculado = padre.id;
            }
        });
    });

    /* Lee un JSON desde data-sget-datos para poblar el modal */
    Modal._datosDe = function (el) {
        var raw = el.getAttribute('data-sget-datos');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('[SGETModal] data-sget-datos inválido en', el);
            return null;
        }
    };

    window.SGETModal = Modal;
})(window, document);
