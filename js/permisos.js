// js/permisos.js

function abrirModalPermisos(idUsuario, nombreUsuario) {
    // 1. Asignar datos del usuario al modal
    /* textContent, no innerHTML: el nombre viene de la base de datos. */
    document.getElementById('modalNombreUsuario').textContent = nombreUsuario;
    document.getElementById('modalIdUsuario').value = idUsuario;
    
    const loader = document.getElementById('loaderPermisos');
    const contenedor = document.getElementById('contenedorPermisos');
    
    // 2. Estado inicial de carga
    if (loader) loader.classList.remove('hidden');
    if (contenedor) {
        contenedor.classList.add('hidden');
        contenedor.innerHTML = '';
    }
    
    // 3. Mostrar el modal en pantalla
    const modal = document.getElementById('modalGestionPermisos');
    if (modal) {
        modal.classList.remove('hidden');
    }

    // 4. Petición para obtener todos los permisos
    fetch(`../api/obtener_permisos.php?id_usu=${idUsuario}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                if (!res.data || res.data.length === 0) {
                    contenedor.innerHTML = '<p class="text-center text-color-mutado py-6 italic">No existen permisos registrados en la base de datos.</p>';
                } else {
                    renderizarSwitchesPermisos(res.data);
                }
            } else {
                contenedor.innerHTML = `<p class="text-center text-red-400 py-6">Error: ${res.mensaje}</p>`;
            }
        })
        .catch(err => {
            console.error('Error al obtener permisos:', err);
            contenedor.innerHTML = '<p class="text-center text-red-400 py-6">' + (window.SGET_I18N?.t('Error de conexión al cargar permisos.') || 'Connection error while loading permissions.') + '</p>';
        })
        .finally(() => {
            if (loader) loader.classList.add('hidden');
            if (contenedor) contenedor.classList.remove('hidden');
        });
}

function cerrarModalPermisos() {
    const modal = document.getElementById('modalGestionPermisos');
    if (modal) {
        modal.classList.add('hidden');
    }
}

/**
 * Escapa texto antes de meterlo en `innerHTML`.
 * Los permisos vienen de la base de datos y su descripción es texto libre:
 * sin esto, una descripción con `<` rompería el marcado del formulario.
 */
function escaparTexto(valor) {
    const d = document.createElement('div');
    d.textContent = valor == null ? '' : String(valor);
    return d.innerHTML;
}

function renderizarSwitchesPermisos(permisos) {
    const contenedor = document.getElementById('contenedorPermisos');
    contenedor.innerHTML = '';

    // Agrupar catálogo por modulo
    const modulos = {};
    permisos.forEach(p => {
        if (!modulos[p.modulo]) modulos[p.modulo] = [];
        modulos[p.modulo].push(p);
    });

    // Renderizar cada bloque de módulo
    for (const [modulo, listaPermisos] of Object.entries(modulos)) {
        let htmlModulo = `
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 space-y-3">
                <h4 class="text-xs font-black uppercase text-neon-azul tracking-wider flex items-center gap-2">
                    <i class="fas fa-folder text-[10px]"></i> Módulo: ${modulo}
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        `;

        listaPermisos.forEach(p => {
            /* `permitido` llega como booleo del backend. `por_rol` indica que el
               permiso se lo concede el ROL, no esta cuenta: si se desmarca, el
               módulo seguirá abierto hasta que se le quite el permiso al rol,
               así que se dice explícitamente en vez de dejar la sorpresa. */
            const isChecked = p.permitido ? 'checked' : '';
            const avisoRol = (p.efectivo && !p.permitido)
                ? '<span class="text-[9px] uppercase tracking-wider text-amber-400 font-bold block mt-1">Concedido por su rol</span>'
                : '';

            htmlModulo += `
                <label class="flex items-start gap-3 p-3 bg-[#0b0f19] rounded-xl border border-white/5 cursor-pointer hover:border-neon-azul/50 transition-all">
                    <input type="checkbox" name="permisos[]" value="${Number(p.id_permiso)}" ${isChecked}
                           class="mt-1 w-4 h-4 rounded text-neon-azul focus:ring-neon-azul border-white/20 bg-slate-800">
                    <div>
                        <span class="text-xs font-bold text-slate-200 block">${escaparTexto(p.nombre_permiso)}</span>
                        <span class="text-[10px] text-color-mutado block leading-tight mt-0.5">${escaparTexto(p.descripcion || 'Sin descripción')}</span>
                        ${avisoRol}
                    </div>
                </label>
            `;
        });

        htmlModulo += `
                </div>
            </div>
        `;

        contenedor.innerHTML += htmlModulo;
    }
}

// 5. Escuchar guardado de formulario
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formGuardarPermisos');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            /* El backend exige el token anti-CSRF; se añade explícitamente porque
               este formulario se envía por fetch y no lleva el campo oculto de
               la página por defecto. */
            if (window.SGET_CSRF) formData.set('_token', window.SGET_CSRF);

            fetch('../api/guardar_permisos.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(r => r.json().catch(() => ({
                status: 'error',
                mensaje: 'El servidor no devolvió una respuesta válida.'
            })))
            .then(res => {
                if (res.status === 'ok') {
                    if (window.SGETModal) SGETModal.toast(res.mensaje, 'exito');
                    cerrarModalPermisos();
                } else {
                    if (window.SGETModal) SGETModal.toast(res.mensaje, 'error');
                    else alert(res.mensaje);
                }
            })
            .catch(err => {
                console.error('Error al guardar permisos:', err);
                const msg = 'No se pudo comunicar con el servidor. Revisa tu conexión.';
                if (window.SGETModal) SGETModal.toast(msg, 'error');
                else alert(msg);
            });
        });
    }
});