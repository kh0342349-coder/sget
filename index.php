<?php
session_start();
// Ruta de conexión a la base de datos
require_once 'assets/conexion.php';

// Consulta SQL utilizando la relación directa entre viaje (v.id_veh) y vehiculo (veh.id_veh)
$query_viajes = "SELECT 
                    v.id_via,
                    r.ori_rut AS origen,
                    r.des_rut AS destino,
                    v.hor_sal_via AS hora_salida,
                    v.est_via AS estado_viaje,
                    veh.pla_veh AS placa_veh
                 FROM viaje v
                 INNER JOIN rutas r ON v.id_rut_via = r.id_rut
                 LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
                 WHERE v.est_via = 'Activo'
                 ORDER BY v.hor_sal_via ASC
                 LIMIT 6";

$resultado_viajes = mysqli_query($conexion, $query_viajes);

// Control de errores en la consulta
if (!$resultado_viajes) {
    die("Error en la consulta SQL: " . mysqli_error($conexion));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Sistema Inteligente de Transporte</title>

    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- SDK de Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7'
                    }
                }
            }
        }

        // Script Anti-Parpadeo de Tema
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Manejador del Token devuelto por Google
        function handleGoogleResponse(response) {
            fetch('controllers/auth_google.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token: response.credential })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert('Error en inicio de sesión con Google: ' + data.message);
                }
            })
            .catch(error => console.error('Error al comunicarse con el servidor:', error));
        }
    </script>

    <!-- ESTILOS DE ISLA FLOTANTE -->
    <style>
        .modal-isla-container {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        .modal-isla-card {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col antialiased transition-colors duration-300">

    <!-- HEADER MODULAR -->
    <?php include 'header.php'; ?>

    <main class="flex-grow pt-24">
        
        <!-- HERO SECTION -->
        <section id="inicio" class="relative py-12 px-6 overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-gradient-to-tr from-sky-500/20 to-blue-600/10 blur-[120px] rounded-full pointer-events-none"></div>

            <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-600 dark:text-sky-400 text-xs font-extrabold tracking-wide uppercase shadow-sm">
                    <i class="fas fa-bus-alt text-sm"></i> Plataforma Líder en Transporte
                </div>

                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                    Viaja Seguro y Monitorea tu Flota en <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-blue-500 to-indigo-600">Tiempo Real</span>
                </h1>

                <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto font-medium leading-relaxed">
                    Consulta horarios, rutas disponibles y asegura tu desplazamiento con la tecnología integral de **SGET**.
                </p>
            </div>
        </section>

        <!-- SECCIÓN 2: VIAJES EN VIVO -->
        <section id="viajes-disponibles" class="py-16 px-6 max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-6">
                <div>
                    <span class="text-xs font-extrabold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Salidas Programadas
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">Viajes Disponibles Ahora</h2>
                </div>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 max-w-md font-medium">
                    Consulta las rutas activas listas para abordar con asignación de vehículos en tiempo real.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($resultado_viajes && mysqli_num_rows($resultado_viajes) > 0): ?>
                    <?php while ($viaje = mysqli_fetch_assoc($resultado_viajes)): ?>
                        <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-xl hover:shadow-sky-500/10 border-t-4 border-t-sky-500 flex flex-col justify-between hover:-translate-y-1 transition-all duration-300 group">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold uppercase">
                                        <?= htmlspecialchars($viaje['estado_viaje']); ?>
                                    </span>
                                    <span class="text-xs font-bold text-slate-400">
                                        <i class="far fa-clock mr-1 text-sky-500"></i>
                                        <?= !empty($viaje['hora_salida']) ? date('h:i A', strtotime($viaje['hora_salida'])) : 'Por definir'; ?>
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between gap-2 pt-2 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-100 dark:border-white/5">
                                    <div class="text-left">
                                        <p class="text-[10px] font-extrabold uppercase text-slate-400">Origen</p>
                                        <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">
                                            <?= htmlspecialchars($viaje['origen']); ?>
                                        </p>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                                        <i class="fas fa-arrow-right text-xs"></i>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-extrabold uppercase text-slate-400">Destino</p>
                                        <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">
                                            <?= htmlspecialchars($viaje['destino']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                                    <i class="fas fa-bus-alt text-sky-500"></i>
                                    <span>Vehículo: <strong class="text-slate-900 dark:text-white"><?= htmlspecialchars($viaje['placa_veh'] ?? 'Sin Asignar'); ?></strong></span>
                                </div>
                                <button onclick="abrirPanel('panelLogin')" class="px-4 py-2 rounded-xl bg-slate-900 text-white dark:bg-sky-500 dark:text-slate-950 font-extrabold transition-all text-xs hover:opacity-90 cursor-pointer shadow-md">
                                    Reservar / Ver
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-12 text-center bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl">
                        <i class="fas fa-route text-4xl text-slate-400 dark:text-slate-600 mb-3"></i>
                        <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No hay viajes activos programados en este momento.</p>
                        <p class="text-xs text-slate-400 mt-1">Por favor, vuelve a consultar más tarde.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECCIÓN 3: RUTAS Y HORARIOS -->
        <section id="rutas" class="py-16 px-6 max-w-6xl mx-auto space-y-8">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-sky-500 uppercase tracking-widest">Frecuencias Fijas</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Rutas Principales y Cobertura</h2>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-xl mx-auto font-medium">Horarios continuos para garantizar el cumplimiento de tus itinerarios.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="p-6 bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-lg flex items-start gap-4 hover:border-sky-500/40 transition-all">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0 text-xl font-bold">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="space-y-1.5">
                        <span class="px-2.5 py-0.5 rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 text-[10px] font-extrabold uppercase">Ruta Expresa</span>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Troncal Norte - Sur</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Conexión directa entre puntos de alto tráfico urbano.</p>
                        <div class="pt-2 flex items-center gap-4 text-xs font-bold text-slate-700 dark:text-slate-300">
                            <span><i class="far fa-clock text-sky-500 mr-1"></i>05:00 AM - 10:00 PM</span>
                            <span><i class="fas fa-redo-alt text-sky-500 mr-1"></i>Cada 15 min</span>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-lg flex items-start gap-4 hover:border-purple-500/40 transition-all">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-500 flex items-center justify-center shrink-0 text-xl font-bold">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="space-y-1.5">
                        <span class="px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400 text-[10px] font-extrabold uppercase">Ruta Especial</span>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Conector Universitario & Sede Campestre</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Diseñada para traslados estudiantiles y de personal institucional.</p>
                        <div class="pt-2 flex items-center gap-4 text-xs font-bold text-slate-700 dark:text-slate-300">
                            <span><i class="far fa-clock text-purple-500 mr-1"></i>06:00 AM - 09:30 PM</span>
                            <span><i class="fas fa-redo-alt text-purple-500 mr-1"></i>Cada 20 min</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN 4: SERVICIOS Y VENTAJAS -->
        <section id="servicios" class="py-16 px-6 max-w-6xl mx-auto space-y-10">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-indigo-500 uppercase tracking-widest">¿Por qué SGET?</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Servicios Diseñados para la Eficiencia</h2>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div class="p-8 bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-xl space-y-4 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Gestión de Viajes</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Control automatizado de itinerarios, asignaciones e imprevistos de ruta al instante.</p>
                </div>
                
                <div class="p-8 bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-xl space-y-4 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Control de Conductores</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Monitoreo permanente de turnos, disponibilidades y estados del personal operativo.</p>
                </div>
                
                <div class="p-8 bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-xl space-y-4 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Reportes y Métricas</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Historiales detallados de uso de flota para la toma estratégica de decisiones.</p>
                </div>
            </div>
        </section>

    </main>

    <footer class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10 bg-white/50 dark:bg-[#0b0f19]/50">
        <p>&copy; 2026 SGET - Sistema de Gestión de Transporte. Todos los derechos reservados.</p>
    </footer>

    <!-- INCLUSIÓN DEL MODAL AUTENTICACIÓN -->
    <?php include 'modal_auth.php'; ?>

    <!-- SCRIPTS DE CONTROL DEL MODAL Y GOOGLE SIGN-IN -->
    <script>
        function inicializarBotonGoogle(panel) {
            if (window.google && google.accounts && google.accounts.id) {
                google.accounts.id.initialize({
                    client_id: "916674198156-4uh6adhaklk2bpsvli6hnmrgg0bgktlp.apps.googleusercontent.com",
                    callback: handleGoogleResponse
                });

                let googleContainer = panel.querySelector('.g_id_signin');
                
                if (!googleContainer) {
                    const form = panel.querySelector('form');
                    if (form) {
                        const divisor = document.createElement('div');
                        divisor.className = 'relative flex py-2 items-center my-4';
                        divisor.innerHTML = `
                            <div class="flex-grow border-t border-slate-200 dark:border-white/10"></div>
                            <span class="flex-shrink mx-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">O INICIA CON</span>
                            <div class="flex-grow border-t border-slate-200 dark:border-white/10"></div>
                        `;

                        googleContainer = document.createElement('div');
                        googleContainer.className = 'g_id_signin flex justify-center';

                        form.parentNode.insertBefore(divisor, form.nextSibling);
                        form.parentNode.insertBefore(googleContainer, divisor.nextSibling);
                    }
                }

                if (googleContainer) {
                    google.accounts.id.renderButton(googleContainer, {
                        theme: 'outline',
                        size: 'large',
                        type: 'standard',
                        shape: 'pill',
                        width: 250
                    });
                }
            }
        }

        function abrirPanel(idPanel) {
            const panel = document.getElementById(idPanel);
            if (!panel) return;
            const card = panel.querySelector('.modal-isla-card') || panel.querySelector('> div');
            
            panel.classList.remove('hidden');

            setTimeout(() => {
                inicializarBotonGoogle(panel);
            }, 50);

            setTimeout(() => {
                panel.classList.remove('opacity-0');
                if (card) {
                    card.classList.remove('scale-95');
                    card.classList.add('scale-100');
                }
            }, 10);
        }

        function cerrarPanel(idPanel) {
            const panel = document.getElementById(idPanel);
            if (!panel) return;
            const card = panel.querySelector('.modal-isla-card') || panel.querySelector('> div');
            if (card) {
                card.classList.remove('scale-100');
                card.classList.add('scale-95');
            }
            panel.classList.add('opacity-0');
            setTimeout(() => { panel.classList.add('hidden'); }, 300);
        }

        function cambiarAPanel(idDestino) {
            cerrarPanel('panelLogin');
            cerrarPanel('panelRegistro');
            setTimeout(() => { abrirPanel(idDestino); }, 200);
        }

        document.addEventListener("DOMContentLoaded", function () {
            <?php if (isset($_SESSION['abrir_login']) && $_SESSION['abrir_login'] === true): ?>
                abrirPanel('panelLogin');
                <?php unset($_SESSION['abrir_login']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>