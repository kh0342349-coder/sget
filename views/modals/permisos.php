<?php
/**
 * views/modals/permisos.php
 * -----------------------------------------------------------------------------
 * EDITOR DE PERMISOS POR USUARIO  (Administrador)
 * -----------------------------------------------------------------------------
 * Sustituye al drawer heredado de `Admin/gestion_permisos.php`, que escribía
 * en `restricciones` / `usuario.restricciones` desde un formulario sin token
 * anti-CSRF. Este modal es el ÚNICO editor de permisos del sistema:
 *
 *     lee   api/obtener_permisos.php    (exige `gestionar_permisos`)
 *     escribe api/guardar_permisos.php   (CSRF + permiso + anti-auto-bloqueo)
 *
 * El catálogo se pide por API y se agrupa por módulo; cada casilla es un
 * permiso real de la tabla `permisos`, no una etiqueta decorativa.
 * -----------------------------------------------------------------------------
 */
$v = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
?>
<div class="sget-modal-wrap" id="modalGestionPermisos" data-sget-capa data-titulo="Permisos">
    <div class="sget-overlay"></div>

    <form class="sget-modal" id="formGuardarPermisos" data-sget-panel novalidate
          role="dialog" aria-modal="true" aria-labelledby="tituloModalPermisos">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalPermisos">
                    <span class="sget-modal__icono"
                          style="background:color-mix(in srgb,var(--sget-azul) 16%,transparent);color:var(--sget-azul)">
                        <i class="fas fa-sliders"></i>
                    </span>
                    <span>Permisos de <span id="modalNombreUsuario">—</span></span>
                </h2>
                <p class="sget-modal__sub">Lo que marques prima sobre el rol; lo que dejes vacío lo decide el rol.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <input type="hidden" id="modalIdUsuario" value="">

            <div id="loaderPermisos" class="sget-sin-resultados" hidden>
                <i class="fas fa-circle-notch fa-spin"></i> Cargando permisos…
            </div>

            <div id="contenedorPermisos" style="display:flex;flex-direction:column;gap:1rem"></div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-floppy-disk"></i> Guardar permisos
            </button>
        </footer>
    </form>
</div>

<script>
(function () {
    'use strict';

    var API_OBTENER = '../api/obtener_permisos.php';
    var API_GUARDAR = '../api/guardar_permisos.php';

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();

    function iniciar() {
        var form      = document.getElementById('formGuardarPermisos');
        var contenedor = document.getElementById('contenedorPermisos');
        var loader    = document.getElementById('loaderPermisos');
        if (!form || !contenedor) return;

        /* ------------------------------------------------------------------ */
        /* Abrir desde el botón de la tabla                                  */
        /* ------------------------------------------------------------------ */
        document.addEventListener('click', function (e) {
            var boton = e.target.closest('[data-sget-accion="abrirPermisos"]');
            if (!boton) return;
            e.preventDefault();
            abrirPermisos(boton.dataset.sgetUsuario, boton.dataset.sgetNombre || '');
        });

        /* ------------------------------------------------------------------ */
        /* Carga del catálogo                                                */
        /* ------------------------------------------------------------------ */
        function abrirPermisos(idUsuario, nombre) {
            document.getElementById('modalIdUsuario').value = idUsuario;
            document.getElementById('modalNombreUsuario').textContent = nombre;

            contenedor.textContent = '';
            loader.hidden = false;
            window.SGETModal.abrir('modalGestionPermisos');

            fetch(API_OBTENER + '?id_usu=' + encodeURIComponent(idUsuario), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    loader.hidden = true;
                    if (j.status !== 'ok') {
                        contenedor.textContent = j.mensaje || 'No se pudieron cargar los permisos.';
                        return;
                    }
                    var lista = j.data || j.permisos || [];
                    if (!lista.length) {
                        contenedor.textContent = 'No hay permisos registrados en el catálogo.';
                        return;
                    }
                    renderizar(lista);
                })
                .catch(function () {
                    loader.hidden = true;
                    contenedor.textContent = 'Error de conexión al cargar los permisos.';
                });
        }

        /* ------------------------------------------------------------------ */
        /* Render: se construye con nodos, no con innerHTML + plantilla.       */
        /* `textContent` para TODO el texto: nombre de permiso y descripción  */
        /* vienen de la base de datos y son texto libre.                    */
        /* ------------------------------------------------------------------ */
        function renderizar(permisos) {
            contenedor.textContent = '';

            var porModulo = {};
            permisos.forEach(function (p) {
                (porModulo[p.modulo] = porModulo[p.modulo] || []).push(p);
            });

            Object.keys(porModulo).sort().forEach(function (modulo) {
                var bloque = document.createElement('section');
                bloque.className = 'sget-card';
                bloque.style.padding = '1rem';

                var titulo = document.createElement('h3');
                titulo.className = 'sget-label';
                titulo.style.marginBottom = '.75rem';
                titulo.textContent = 'Módulo: ' + modulo;
                bloque.appendChild(titulo);

                var rejilla = document.createElement('div');
                rejilla.style.display = 'grid';
                rejilla.style.gridTemplateColumns = 'repeat(auto-fit,minmax(15rem,1fr))';
                rejilla.style.gap = '.6rem';

                porModulo[modulo].forEach(function (p) {
                    var etiqueta = document.createElement('label');
                    etiqueta.className = 'sget-check';
                    etiqueta.style.padding = '.6rem .7rem';
                    etiqueta.style.border = '1px solid var(--sget-borde)';
                    etiqueta.style.borderRadius = 'var(--sget-radio-sm)';

                    var caja = document.createElement('input');
                    caja.type = 'checkbox';
                    caja.name = 'permisos[]';
                    caja.value = String(Number(p.id_permiso) || 0);
                    caja.checked = !!p.permitido;

                    var texto = document.createElement('span');
                    texto.style.minWidth = '0';

                    var nombre = document.createElement('strong');
                    nombre.style.display = 'block';
                    nombre.textContent = String(p.nombre_permiso || '').replace(/_/g, ' ');
                    texto.appendChild(nombre);

                    var desc = document.createElement('span');
                    desc.className = 'sget-help';
                    desc.textContent = p.descripcion || 'Sin descripción';
                    texto.appendChild(desc);

                    if (p.efectivo && !p.permitido) {
                        var aviso = document.createElement('span');
                        aviso.className = 'sget-help';
                        aviso.style.color = 'var(--sget-ambars)';
                        aviso.style.fontWeight = '700';
                        aviso.textContent = 'Concedido por su rol';
                        texto.appendChild(aviso);
                    }

                    etiqueta.appendChild(caja);
                    etiqueta.appendChild(texto);
                    rejilla.appendChild(etiqueta);
                });

                bloque.appendChild(rejilla);
                contenedor.appendChild(bloque);
            });
        }

        /* ------------------------------------------------------------------ */
        /* Guardado                                                            */
        /* ------------------------------------------------------------------ */
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var cuerpo = new FormData();
            cuerpo.append('_token', window.SGETModal.__token);
            cuerpo.append('id_usu', document.getElementById('modalIdUsuario').value);

            contenedor.querySelectorAll('input[name="permisos[]"]:checked').forEach(function (c) {
                cuerpo.append('permisos[]', c.value);
            });

            var boton = form.querySelector('button[type="submit"]');
            boton.disabled = true;

            fetch(API_GUARDAR, {
                method: 'POST',
                body: cuerpo,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    window.SGETModal.toast(j.mensaje || 'Permisos guardados.', j.status === 'ok' ? 'exito' : 'error');
                    if (j.status === 'ok') window.SGETModal.cerrar('modalGestionPermisos');
                })
                .catch(function () { window.SGETModal.toast('No se pudo comunicar con el servidor.', 'error'); })
                .finally(function () { boton.disabled = false; });
        });
    }
})();
</script>
