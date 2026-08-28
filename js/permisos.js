// js/permisos.js

function abrirModalPermisos(idUsuario, nombreUsuario) {
    // 1. Asignar datos del usuario al modal
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
            if (res.status === 'success') {
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
            contenedor.innerHTML = '<p class="text-center text-red-400 py-6">Error de conexión al cargar permisos.</p>';
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
            const isChecked = parseInt(p.permitido) === 1 ? 'checked' : '';
            htmlModulo += `
                <label class="flex items-start gap-3 p-3 bg-[#0b0f19] rounded-xl border border-white/5 cursor-pointer hover:border-neon-azul/50 transition-all">
                    <input type="checkbox" name="permisos[]" value="${p.id_permiso}" ${isChecked} 
                           class="mt-1 w-4 h-4 rounded text-neon-azul focus:ring-neon-azul border-white/20 bg-slate-800">
                    <div>
                        <span class="text-xs font-bold text-slate-200 block">${p.nombre_permiso}</span>
                        <span class="text-[10px] text-color-mutado block leading-tight mt-0.5">${p.descripcion || 'Sin descripción'}</span>
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

            fetch('../api/guardar_permisos.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert('Permisos actualizados correctamente.');
                    cerrarModalPermisos();
                } else {
                    alert('Error al guardar: ' + res.mensaje);
                }
            })
            .catch(err => console.error('Error al guardar permisos:', err));
        });
    }
});