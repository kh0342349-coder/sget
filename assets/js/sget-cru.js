/* ==========================================================================
   assets/js/sget-cru.js
   ------------------------------------------------------------------------------
   LÓGICA COMPARTIDA DE LOS MÓDULOS CRUD
   ------------------------------------------------------------------------------
   Comportamiento común a usuarios / flota / rutas / viajes, en un solo archivo:
     - búsqueda en vivo (por texto y por filtros de estado)
     - envío del formulario por fetch con estado de carga y pintado de errores
     - confirmación de acciones destructivas
     - atajos de teclado dentro de los formularios (Ctrl+Enter = guardar)
   ========================================================================== */
(function (window, document) {
    'use strict';

    var CRUD = {

        /* ------------------------------------------------------------------ */
        /* Búsqueda en vivo                                                    */
        /* ------------------------------------------------------------------ */
        /**
         * @param {string} inputId  id del campo de búsqueda
         * @param {string} filaSel  selector de las filas
         * @param {string} [textoSel] selector de los textos a comparar
         */
        buscar: function (inputId, filaSel, textoSel) {
            var input = document.getElementById(inputId);
            if (!input) return;
            var textoSel = textoSel || '*';

            function aplicar() {
                var q = input.value.trim().toLowerCase();
                var visibles = 0;
                document.querySelectorAll(filaSel).forEach(function (fila) {
                    var texto = textoSel === '*'
                        ? fila.textContent
                        : Array.prototype.map.call(fila.querySelectorAll(textoSel), function (n) { return n.textContent; }).join(' ');
                    var coincide = q === '' || texto.toLowerCase().indexOf(q) > -1;
                    fila.hidden = !coincide;
                    if (coincide) visibles++;
                });

                // Mensaje de "sin resultados"
                var caja = document.querySelector('[data-sget-sin-resultados]');
                if (caja) caja.hidden = visibles > 0;
            }

            input.addEventListener('input', aplicar);
            aplicar();
        },

        /* ------------------------------------------------------------------ */
        /* Filtro por chips de estado                                          */
        /* ------------------------------------------------------------------ */
        /**
         * @param {string} chipSel      selector de los botones de filtro
         * @param {string} filaSel      selector de las filas
         * @param {string} attrEstado   atributo que guarda el estado de la fila
         */
        filtrarEstado: function (chipSel, filaSel, attrEstado) {
            var chips = document.querySelectorAll(chipSel);
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
                    chip.setAttribute('aria-pressed', 'true');
                    var valor = chip.dataset.filtro;

                    document.querySelectorAll(filaSel).forEach(function (fila) {
                        fila.hidden = valor !== '*' && fila.getAttribute(attrEstado) !== valor;
                    });
                });
            });
        },

        /* ------------------------------------------------------------------ */
        /* Envío de formularios                                                */
        /* ------------------------------------------------------------------ */
        /**
         * Envía el formulario al API y redirige a la URL de listado.
         *
         * @param {HTMLFormElement|string} form    formulario o su id
         * @param {object} opciones
         *   url      -> endpoint del API (por defecto usa action del form)
         *   exito    -> URL a la que redirigir (admite {mensaje})
         *   alCerrar -> id del modal a cerrar
         *   refrescar-> si es true, recarga la página en vez de redirigir
         */
        enviar: function (form, opciones) {
            opciones = opciones || {};
            form = typeof form === 'string' ? document.getElementById(form) : form;
            if (!form) { console.warn('[SGETCRUD] Formulario no encontrado'); return; }

            var btn = form.querySelector('[type="submit"]') || opciones.boton;
            var cuerpo = new FormData(form);

            if (window.SGETModal) window.SGETModal.limpiarErrores(form);
            if (btn) { btn.classList.add('sget-cargando'); btn.disabled = true; }
            var textoBoton = btn ? btn.innerHTML : null;
            if (btn) btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';

            fetch(opciones.url || form.action, {
                method: 'POST',
                body: cuerpo,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json().catch(function () { return { status: 'error', mensaje: 'Respuesta inválida del servidor.' }; }); })
            .then(function (json) {
                if (btn) { btn.classList.remove('sget-cargando'); btn.disabled = false; if (textoBoton) btn.innerHTML = textoBoton; }

                if (json.status === 'ok') {
                    if (window.SGETModal) {
                        window.SGETModal.toast(json.mensaje || 'Cambios guardados.', 'exito');
                        if (opciones.alCerrar) window.SGETModal.cerrar(opciones.alCerrar);
                    }
                    if (opciones.refrescar) { setTimeout(function () { location.reload(); }, 700); return; }
                    var destino = opciones.exito || json.redirect;
                    if (destino) {
                        destino = destino.replace('{mensaje}', encodeURIComponent(json.mensaje || ''));
                        setTimeout(function () { location.href = destino; }, 650);
                    }
                } else {
                    if (window.SGETModal) {
                        if (json.errores) window.SGETModal.errores(json.errores, form);
                        window.SGETModal.toast(json.mensaje || 'No se pudo guardar.', 'error');
                    }
                }
            })
            .catch(function () {
                if (btn) { btn.classList.remove('sget-cargando'); btn.disabled = false; if (textoBoton) btn.innerHTML = textoBoton; }
                if (window.SGETModal) window.SGETModal.toast('Error de conexión con el servidor.', 'error');
            });
        },

        /* ------------------------------------------------------------------ */
        /* Atajo Ctrl+Enter para enviar                                       */
        /* ------------------------------------------------------------------ */
        enviarConEnter: function (form, opciones) {
            form = typeof form === 'string' ? document.getElementById(form) : form;
            if (!form) return;
            form.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    CRUD.enviar(form, opciones);
                }
            });
        },

        /* ------------------------------------------------------------------ */
        /* Barra de atajos Ctrl+K (búsqueda global)                             */
        /* ------------------------------------------------------------------ */
        atajoBusqueda: function (inputId) {
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    var input = document.getElementById(inputId);
                    if (input) { e.preventDefault(); input.focus(); input.select(); }
                }
            });
        }
    };

    window.SGETCRUD = CRUD;
})(window, document);
