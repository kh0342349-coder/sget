<?php
session_start();
// Ruta de conexión a la base de datos
require_once 'assets/conexion.php';

// Consulta SQL ajustada para traer imagen de ruta (img_rut) y valor del viaje (val_via)
$query_viajes = "SELECT 
                    v.id_via,
                    v.val_via,
                    r.nom_rut,
                    r.img_rut,
                    r.ori_rut AS origen,
                    r.des_rut AS destino,
                    v.hor_sal_via AS hora_salida,
                    v.est_via AS estado_viaje,
                    veh.pla_veh AS placa_veh
                 FROM viaje v
                 INNER JOIN rutas r ON v.id_rut_via = r.id_rut
                 LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
                 WHERE v.est_via = 'Activo'
                 ORDER BY v.id_via DESC
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
    
    <!-- Hoja de Estilos del Index -->
    <link rel="stylesheet" href="index.css">

    <!-- SDK de Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'neon-azul': 'var(--neon-azul)',
                        'neon-morado': 'var(--neon-morado)'
                    }
                }
            }
        };

        // Script Anti-Parpadeo de Tema
        (function() {
            const theme = localStorage.getItem('theme') || 
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const html = document.documentElement;
            
            if (theme === 'dark') {
                html.classList.add('dark');
                html.classList.remove('light');
                html.setAttribute('data-theme', 'dark');
            } else {
                html.classList.remove('dark');
                html.classList.add('light');
                html.setAttribute('data-theme', 'light');
            }
        })();

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
    <script src="theme-toggle.js" defer></script>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col antialiased transition-colors duration-300">

    <!-- HEADER MODULAR -->
    <?php include 'includes/header_index.php'; ?>

    <main class="flex-grow pt-28">
        
        <!-- HERO SECTION -->
        <section id="inicio" class="hero-section py-16 px-6 relative overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-gradient-to-tr from-sky-500/20 to-blue-600/10 blur-[120px] rounded-full pointer-events-none"></div>

            <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-600 dark:text-sky-400 text-xs font-extrabold tracking-wide uppercase shadow-sm">
                    <i class="fas fa-bus-alt text-sm"></i> Plataforma Líder en Transporte
                </div>

                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black tracking-tight leading-tight text-slate-900 dark:text-white">
                    Viaja Seguro y Monitorea tu Flota en <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-blue-500 to-indigo-600">Tiempo Real</span>
                </h1>

                <p class="text-base sm:text-lg max-w-2xl mx-auto font-medium leading-relaxed text-slate-600 dark:text-slate-300">
                    Consulta horarios, rutas disponibles y asegura tu desplazamiento con la tecnología integral de SGET.
                </p>

                <!-- BOTONES DE ACCIÓN MEJORADOS -->
                <div class="flex flex-wrap justify-center items-center gap-4 pt-4">
                    <a href="#viajes-disponibles" class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-sm shadow-lg shadow-sky-500/25 hover:shadow-sky-500/40 hover:-translate-y-0.5 transition-all">
                        <i class="fas fa-eye mr-2"></i>Ver Viajes
                    </a>
                    <button onclick="abrirPanel('panelLogin')" class="px-8 py-3.5 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-white border border-slate-200 dark:border-white/10 font-extrabold text-sm hover:border-sky-500 dark:hover:border-sky-500 hover:-translate-y-0.5 transition-all shadow-sm cursor-pointer">
                        <i class="fas fa-sign-in-alt mr-2 text-sky-500"></i>Iniciar Sesión
                    </button>
                </div>
            </div>
        </section>

        <div class="max-w-7xl mx-auto px-6"><div class="divider-glow"></div></div>

        <!-- SECCIÓN 2: VIAJES EN VIVO (VISUAL Y MINIMALISTA) -->
        <section id="viajes-disponibles" class="py-20 px-6 max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-6">
                <div>
                    <span class="text-xs font-extrabold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> SALIDAS PROGRAMADAS
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">Viajes Disponibles Ahora</h2>
                </div>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 max-w-md font-medium">
                    Consulta las rutas activas listas para abordar con asignación de vehículos en tiempo real.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($resultado_viajes && mysqli_num_rows($resultado_viajes) > 0): ?>
                    <?php while ($viaje = mysqli_fetch_assoc($resultado_viajes)): ?>
                        <?php 
                            $nombreImagen = trim($viaje['img_rut'] ?? '');
                            $rutaImagen = !empty($nombreImagen) ? "img/rutas/" . $nombreImagen : "";
                        ?>
                        <!-- Tarjeta Limpia Enfocada en la Imagen de Destino -->
                        <div class="relative overflow-hidden rounded-3xl h-64 border border-slate-200 dark:border-white/10 shadow-xl group transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl flex flex-col justify-between p-5 bg-slate-950">
                            
                            <!-- Imagen de la ruta a pantalla completa con zoom suave al pasar el mouse -->
                            <?php if (!empty($nombreImagen) && file_exists("img/rutas/" . $nombreImagen)): ?>
                                <img src="<?php echo htmlspecialchars($rutaImagen); ?>" 
                                     alt="<?php echo htmlspecialchars($viaje['nom_rut'] ?? 'Ruta'); ?>" 
                                     class="absolute inset-0 w-full h-full object-cover object-center z-0 transition-transform duration-700 group-hover:scale-110">
                            <?php endif; ?>
                            
                            <!-- Degradado suave en los extremos para legibilidad -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-black/60 z-0"></div>

                            <!-- Header: Hora y Estado Activo -->
                            <div class="relative z-10 flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-white bg-black/50 backdrop-blur-md px-3 py-1 rounded-full border border-white/15 shadow-sm">
                                    <i class="far fa-clock text-sky-400 mr-1"></i>
                                    <?= !empty($viaje['hora_salida']) ? date('h:i A', strtotime($viaje['hora_salida'])) : 'En Breve'; ?>
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-300 bg-emerald-900/60 backdrop-blur-md px-3 py-1 rounded-full border border-emerald-500/40 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    ACTIVO
                                </span>
                            </div>

                            <!-- Bloque Inferior: Precio, Ruta y Botón Reservar -->
                            <div class="relative z-10 space-y-3 pt-4 border-t border-white/15">
                                <div>
                                    <?php if (!empty($viaje['val_via'])): ?>
                                        <p class="text-xs font-black uppercase tracking-wider text-amber-300 drop-shadow-md">
                                            $<?= number_format($viaje['val_via'], 0, ',', '.'); ?> COP
                                        </p>
                                    <?php endif; ?>

                                    <h3 class="font-black text-white text-xl sm:text-2xl tracking-tight leading-tight truncate drop-shadow-lg" title="<?= htmlspecialchars($viaje['nom_rut'] ?? ($viaje['origen'] . ' - ' . $viaje['destino'])); ?>">
                                        <?= htmlspecialchars($viaje['nom_rut'] ?? ($viaje['origen'] . ' - ' . $viaje['destino'])); ?>
                                    </h3>
                                    <p class="text-[11px] font-semibold text-slate-300 flex items-center gap-1 mt-0.5">
                                        <i class="fas fa-bus-alt text-sky-400"></i> Placa: <span class="font-mono text-white"><?= htmlspecialchars($viaje['placa_veh'] ?? 'Sin Asignar'); ?></span>
                                    </p>
                                </div>

                                <!-- Botón Reservar -->
                                <button onclick="abrirPanel('panelLogin')" class="w-full py-3 bg-sky-500 hover:bg-sky-400 active:bg-sky-600 text-slate-950 font-black text-xs uppercase tracking-widest rounded-2xl shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer group-hover:shadow-sky-500/30">
                                    <i class="fas fa-ticket-alt"></i> RESERVAR PASAJE
                                </button>
                            </div>

                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-16 px-6 text-center card-glass rounded-3xl">
                        <div class="w-16 h-16 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fas fa-route"></i>
                        </div>
                        <p class="text-base font-extrabold text-slate-800 dark:text-slate-200">No hay viajes activos programados en este momento</p>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Las nuevas salidas aparecerán aquí automáticamente tan pronto sean asignadas por la administración.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECCIÓN 3: SERVICIOS Y VENTAJAS -->
        <section id="servicios" class="py-16 px-6 max-w-6xl mx-auto space-y-10">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-indigo-500 uppercase tracking-widest">¿POR QUÉ SGET?</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Servicios Diseñados para la Eficiencia</h2>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div class="card-glass glow-hover rounded-3xl p-8 space-y-4 text-left group">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Gestión de Viajes</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Control automatizado de itinerarios, asignaciones e imprevistos de ruta al instante.</p>
                </div>
                
                <div class="card-glass glow-hover rounded-3xl p-8 space-y-4 text-left group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Control de Conductores</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Monitoreo permanente de turnos, disponibilidades y estados del personal operativo.</p>
                </div>
                
                <div class="card-glass glow-hover rounded-3xl p-8 space-y-4 text-left group">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Reportes y Métricas</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Historiales detallados de uso de flota para la toma estratégica de decisiones.</p>
                </div>
            </div>
        </section>

    </main>

    <!-- FOOTER CON ENLACE LEGAL -->
    <footer class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10 bg-white/50 dark:bg-[#0b0f19]/50 flex flex-col sm:flex-row items-center justify-between max-w-7xl mx-auto w-full gap-4">
        <p>&copy; 2026 SGET - Sistema de Gestión de Transporte. Todos los derechos reservados.</p>
        <div>
            <button onclick="abrirPanel('panelPolitica')" class="hover:text-sky-500 underline transition-colors cursor-pointer">
                Tratamiento de Datos Personales (Ley 1581)
            </button>
        </div>
    </footer>

    <!-- INCLUSIÓN DEL MODAL AUTENTICACIÓN -->
    <?php include 'modal_auth.php'; ?>

    <!-- MODAL POLÍTICA DE TRATAMIENTO DE DATOS -->
    <div id="panelPolitica" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-isla-container opacity-0 pointer-events-none hidden transition-opacity duration-300">
        <div class="modal-isla-card rounded-3xl p-6 sm:p-8 max-w-2xl w-full max-h-[85vh] flex flex-col transform scale-95 transition-transform duration-300">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center font-bold text-lg">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Tratamiento de Datos Personales</h3>
                        <p class="text-[11px] font-bold text-slate-400">Cumplimiento Ley 1581 de 2012</p>
                    </div>
                </div>
                <button onclick="cerrarPanel('panelPolitica')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-slate-600 dark:hover:text-white flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- CUERPO DE LA POLÍTICA -->
            <div class="my-4 overflow-y-auto pr-2 space-y-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed text-left">
                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">1. Responsable del Tratamiento</h4>
                    <p>El sistema **SGET (Sistema de Gestión de Transporte)** actúa como responsable del tratamiento de sus datos personales recolectados a través de esta plataforma digital.</p>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">2. Finalidad de la Recolección</h4>
                    <p>Los datos solicitados (nombre completo, documento de identidad, correo electrónico y número celular) serán tratados exclusivamente para:</p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5 ml-2">
                        <li>Creación y validación de la cuenta de usuario.</li>
                        <li>Gestión, reserva y control de cupos en viajes y rutas.</li>
                        <li>Notificaciones operativas sobre itinerarios y novedades del servicio.</li>
                        <li>Seguridad del sistema e identificación de perfiles de acceso.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">3. Derechos del Titular (Habeas Data)</h4>
                    <p>De conformidad con la normatividad vigente, como titular de los datos usted tiene derecho a:</p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5 ml-2">
                        <li>Conocer, actualizar y rectificar sus datos personales.</li>
                        <li>Solicitar prueba de la autorización otorgada.</li>
                        <li>Revocar la autorización y/o solicitar la supresión de sus datos cuando sea procedente.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">4. Seguridad y Confidencialidad</h4>
                    <p>SGET implementa protocolos técnicos de cifrado y medidas de seguridad digital para prevenir el acceso no autorizado, la alteración o la filtración de la información de los usuarios.</p>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-white/10 flex justify-end">
                <button onclick="cerrarPanel('panelPolitica')" class="px-5 py-2.5 rounded-xl bg-slate-900 text-white dark:bg-sky-500 dark:text-slate-950 font-extrabold text-xs hover:opacity-90 transition-all cursor-pointer">
                    Entendido
                </button>
            </div>
        </div>
    </div>

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
            panel.classList.remove('pointer-events-none');

            if (idPanel === 'panelLogin' || idPanel === 'panelRegistro') {
                setTimeout(() => {
                    inicializarBotonGoogle(panel);
                }, 50);
            }

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
            panel.classList.add('pointer-events-none');
            setTimeout(() => { panel.classList.add('hidden'); }, 300);
        }

        function cambiarAPanel(idDestino) {
            cerrarPanel('panelLogin');
            cerrarPanel('panelRegistro');
            cerrarPanel('panelPolitica');
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