<?php
session_start();
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
    </script>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col antialiased transition-colors duration-300">

    <!-- HEADER MODULAR -->
    <?php include 'header.php'; ?>

    <main class="flex-grow pt-24">
        
        <!-- HERO SECTION + BUSCADOR INTERACTIVO -->
        <section id="inicio" class="relative py-12 px-6 overflow-hidden">
            <!-- Destello de fondo brillante -->
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

        <!-- SECCIÓN 2: VIAJES EN VIVO (TARJETAS DINÁMICAS) -->
        <section id="viajes-disponibles" class="py-16 px-6 max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-6">
                <div>
                    <span class="text-xs font-extrabold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Salidas Programadas
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">Viajes Disponibles Ahora</h2>
                </div>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 max-w-md font-medium">
                    Consulta las rutas listas para abordar con asignación de vehículos en tiempo real.
                </p>
            </div>

            <!-- Grilla de Tarjetas -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Tarjeta 1 -->
                <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-xl hover:shadow-sky-500/10 border-t-4 border-t-sky-500 flex flex-col justify-between hover:-translate-y-1 transition-all duration-300 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold uppercase">Disponible</span>
                            <span class="text-xs font-bold text-slate-400"><i class="far fa-clock mr-1 text-sky-500"></i>Sale en 15 min</span>
                        </div>
                        
                        <div class="flex items-center justify-between gap-2 pt-2 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-100 dark:border-white/5">
                            <div class="text-left">
                                <p class="text-[10px] font-extrabold uppercase text-slate-400">Origen</p>
                                <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">Terminal Norte</p>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                                <i class="fas fa-arrow-right text-xs"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-extrabold uppercase text-slate-400">Destino</p>
                                <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">Zona Industrial</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                            <i class="fas fa-bus-alt text-sky-500"></i>
                            <span>Vehículo: <strong class="text-slate-900 dark:text-white">BUS-204</strong></span>
                        </div>
                        <button onclick="abrirPanel('panelLogin')" class="px-4 py-2 rounded-xl bg-slate-900 text-white dark:bg-sky-500 dark:text-slate-950 font-extrabold transition-all text-xs hover:opacity-90 cursor-pointer shadow-md">
                            Reservar / Ver
                        </button>
                    </div>
                </div>

                <!-- Tarjeta 2 -->
                <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-xl hover:shadow-sky-500/10 border-t-4 border-t-emerald-500 flex flex-col justify-between hover:-translate-y-1 transition-all duration-300 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold uppercase">Disponible</span>
                            <span class="text-xs font-bold text-slate-400"><i class="far fa-clock mr-1 text-emerald-500"></i>Sale en 35 min</span>
                        </div>
                        
                        <div class="flex items-center justify-between gap-2 pt-2 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-100 dark:border-white/5">
                            <div class="text-left">
                                <p class="text-[10px] font-extrabold uppercase text-slate-400">Origen</p>
                                <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">Centro Urbano</p>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0">
                                <i class="fas fa-arrow-right text-xs"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-extrabold uppercase text-slate-400">Destino</p>
                                <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">Sede Campestre</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                            <i class="fas fa-shuttle-van text-emerald-500"></i>
                            <span>Vehículo: <strong class="text-slate-900 dark:text-white">VAN-102</strong></span>
                        </div>
                        <button onclick="abrirPanel('panelLogin')" class="px-4 py-2 rounded-xl bg-slate-900 text-white dark:bg-sky-500 dark:text-slate-950 font-extrabold transition-all text-xs hover:opacity-90 cursor-pointer shadow-md">
                            Reservar / Ver
                        </button>
                    </div>
                </div>

                <!-- Tarjeta 3 -->
                <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-xl hover:shadow-amber-500/10 border-t-4 border-t-amber-500 flex flex-col justify-between hover:-translate-y-1 transition-all duration-300 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-[10px] font-extrabold uppercase">Últimos Cupos</span>
                            <span class="text-xs font-bold text-slate-400"><i class="far fa-clock mr-1 text-amber-500"></i>Sale en 50 min</span>
                        </div>
                        
                        <div class="flex items-center justify-between gap-2 pt-2 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-100 dark:border-white/5">
                            <div class="text-left">
                                <p class="text-[10px] font-extrabold uppercase text-slate-400">Origen</p>
                                <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">Estación Sur</p>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0">
                                <i class="fas fa-arrow-right text-xs"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-extrabold uppercase text-slate-400">Destino</p>
                                <p class="font-black text-slate-800 dark:text-white text-sm sm:text-base">Aeropuerto</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                            <i class="fas fa-bus-alt text-amber-500"></i>
                            <span>Vehículo: <strong class="text-slate-900 dark:text-white">BUS-508</strong></span>
                        </div>
                        <button onclick="abrirPanel('panelLogin')" class="px-4 py-2 rounded-xl bg-slate-900 text-white dark:bg-sky-500 dark:text-slate-950 font-extrabold transition-all text-xs hover:opacity-90 cursor-pointer shadow-md">
                            Reservar / Ver
                        </button>
                    </div>
                </div>

            </div>
        </section>

        <!-- SECCIÓN 3: RUTAS Y HORARIOS CON MAPA VISUAL -->
        <section id="rutas" class="py-16 px-6 max-w-6xl mx-auto space-y-8">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-sky-500 uppercase tracking-widest">Frecuencias Fijas</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Rutas Principales y Cobertura</h2>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-xl mx-auto font-medium">Horarios continuos para garantizar el cumplimiento de tus itinerarios.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Ruta 1 -->
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

                <!-- Ruta 2 -->
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

    <!-- MODAL DE LOGIN (MANTIENE FUNCIONALIDAD ANTERIOR) -->
    <div id="panelLogin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md hidden opacity-0 transition-all duration-300">
        <div class="relative w-full max-w-4xl bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row transform scale-95 transition-all duration-300" id="contenidoLogin">
            <button type="button" onclick="cerrarPanel('panelLogin')" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">
                <i class="fas fa-times text-sm"></i>
            </button>
            <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center space-y-6 overflow-y-auto max-h-[85vh]">
                <div class="border-b border-slate-200 dark:border-white/10 pb-4">
                    <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">Bienvenido de nuevo</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Inicia sesión en SGET</p>
                </div>
                <form action="validar.php" method="POST" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">N° Documento</label>
                        <input type="text" name="documento" required placeholder="Ingrese su número" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl py-3 px-4 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Contraseña</label>
                        <input type="password" name="clave" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl py-3 px-4 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-slate-950 font-extrabold rounded-xl shadow-lg hover:opacity-90 transition-all text-xs tracking-wider uppercase cursor-pointer">Ingresar al Sistema</button>
                </form>
            </div>
            <div class="w-full md:w-1/2 bg-gradient-to-br from-slate-900 to-blue-950 p-8 sm:p-12 flex flex-col justify-between text-white relative overflow-hidden">
                <div>
                    <span class="px-3 py-1 bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-full text-[10px] font-bold uppercase tracking-wider">SGET Platform</span>
                    <h4 class="text-xl font-bold mt-4">Control total de tu flota</h4>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed my-4">Gestiona rutas, asignaciones y monitorea cada unidad de transporte de manera eficiente.</p>
                <div class="text-[11px] text-slate-400 border-t border-white/10 pt-4">¿No tienes cuenta? <button type="button" onclick="cambiarAPanel('panelRegistro')" class="text-sky-400 font-semibold hover:underline cursor-pointer">Regístrate aquí</button></div>
            </div>
        </div>
    </div>

    <!-- MODAL DE REGISTRO (MANTIENE FUNCIONALIDAD ANTERIOR) -->
    <div id="panelRegistro" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md hidden opacity-0 transition-all duration-300">
        <div class="relative w-full max-w-4xl bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row transform scale-95 transition-all duration-300" id="contenidoRegistro">
            <button type="button" onclick="cerrarPanel('panelRegistro')" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">
                <i class="fas fa-times text-sm"></i>
            </button>
            <div class="w-full md:w-1/2 bg-gradient-to-br from-blue-950 to-slate-900 p-8 sm:p-12 flex flex-col justify-between text-white relative overflow-hidden">
                <div>
                    <span class="px-3 py-1 bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-full text-[10px] font-bold uppercase tracking-wider">Únete hoy</span>
                    <h4 class="text-xl font-bold mt-4">Empieza a optimizar tu operación</h4>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed my-4">Crea una cuenta en SGET y descubre la forma más inteligente de administrar tus rutas.</p>
                <div class="text-[11px] text-slate-400 border-t border-white/10 pt-4">¿Ya tienes cuenta? <button type="button" onclick="cambiarAPanel('panelLogin')" class="text-sky-400 font-semibold hover:underline cursor-pointer">Inicia sesión</button></div>
            </div>
            <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center space-y-6 overflow-y-auto max-h-[85vh]">
                <div class="border-b border-slate-200 dark:border-white/10 pb-4">
                    <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">Nuevo Registro</h3>
                </div>
                <form action="nuevo_usuario.php" method="POST" class="space-y-3">
                    <input type="text" name="nom_usu" required placeholder="Nombre Completo" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white">
                    <input type="email" name="corre_usu" required placeholder="Correo Electrónico" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white">
                    <input type="password" name="clave_usu" required placeholder="Contraseña" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white">
                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-slate-950 font-extrabold rounded-xl shadow-lg hover:opacity-90 transition-all text-xs tracking-wider uppercase cursor-pointer">Registrarse Ahora</button>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPTS MODALES -->
    <script>
        function abrirPanel(idPanel) {
            const panel = document.getElementById(idPanel);
            if (!panel) return;
            const card = panel.querySelector('.relative');
            panel.classList.remove('hidden');
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
            const card = panel.querySelector('.relative');
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
    </script>
</body>
</html>