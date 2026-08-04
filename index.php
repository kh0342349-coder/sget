<?php
session_start();
require_once 'assets/conexion.php'; 

// Consulta exacta basada en tu diagrama ER de la BD SGET
$sql = "SELECT 
            v.id_via,
            v.nom_via,
            v.fec_via,
            v.hor_sal_via,
            v.val_via,
            v.est_via,
            v.cup_dis,
            r.ori_rut,
            r.des_rut,
            r.nom_rut,
            u.nom_usu AS conductor
        FROM viaje v
        INNER JOIN rutas r ON v.id_rut_via = r.id_rut
        LEFT JOIN usuario u ON v.id_usu_via = u.id_usu
        WHERE v.est_via IN ('Activo', 'Disponible', 'Programado')
        ORDER BY v.fec_via ASC, v.hor_sal_via ASC 
        LIMIT 12";

$resultado = mysqli_query($conexion, $sql);
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
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

        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        /* Animaciones personalizadas para el carrusel */
        @keyframes barraProgreso {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        .animar-barra {
            animation: barraProgreso 6s linear infinite;
        }

        .slide-in {
            animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(15px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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
                    Viaja Seguro y Monitorea tus Rutas en <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-blue-500 to-indigo-600">Tiempo Real</span>
                </h1>

                <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto font-medium leading-relaxed">
                    Consulta horarios, rutas disponibles y asegura tu desplazamiento con la tecnología integral de <strong>SGET</strong>.
                </p>
            </div>
        </section>

        <!-- SECCIÓN DE ANUNCIOS Y PROPAGANDA (CARRUSEL ANIMADO) -->
        <section id="anuncios" class="max-w-7xl mx-auto px-6 py-4">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 border border-white/10 shadow-2xl group">
                
                <!-- Contenedor del Banner/Anuncio -->
                <div id="carruselAnuncios" class="relative min-h-[220px] sm:min-h-[200px] flex items-center p-8 sm:p-12 text-white">
                    
                    <!-- Anuncio 1 -->
                    <div class="item-anuncio slide-in flex flex-col md:flex-row items-center justify-between gap-6 w-full">
                        <div class="space-y-3 text-center md:text-left max-w-xl">
                            <span class="px-3 py-1 bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-full text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-1.5 transition-transform hover:scale-105">
                                <i class="fas fa-bullhorn animate-bounce"></i> Aviso Importante
                            </span>
                            <h3 class="text-2xl sm:text-3xl font-black leading-tight text-white tracking-wide">
                                ¡Nuevas Salidas Especiales Fin de Semana!
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-300">
                                Asegura tu tiquete con anticipación en nuestras rutas más concurridas. Viaja cómodo, rápido y seguro con SGET.
                            </p>
                        </div>
                        <div class="shrink-0">
                            <a href="#viajes-disponibles" class="px-6 py-3 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-amber-500/20 transition-all duration-300 hover:scale-105 hover:-translate-y-0.5 inline-block">
                                Consultar Salidas
                            </a>
                        </div>
                    </div>

                    <!-- Anuncio 2 -->
                    <div class="item-anuncio hidden flex flex-col md:flex-row items-center justify-between gap-6 w-full">
                        <div class="space-y-3 text-center md:text-left max-w-xl">
                            <span class="px-3 py-1 bg-sky-500/20 text-sky-300 border border-sky-500/30 rounded-full text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-1.5 transition-transform hover:scale-105">
                                <i class="fas fa-shield-alt"></i> Compromiso de Seguridad
                            </span>
                            <h3 class="text-2xl sm:text-3xl font-black leading-tight text-white tracking-wide">
                                Vehículos e Itinerarios Monitoreados 24/7
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-300">
                                Todos nuestros viajes cuentan con control telemático en tiempo real para brindarte la mejor experiencia en ruta.
                            </p>
                        </div>
                        <div class="shrink-0">
                            <button onclick="abrirPanel('panelRegistro')" class="px-6 py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-sky-500/20 transition-all duration-300 hover:scale-105 hover:-translate-y-0.5 inline-block">
                                Regístrate Ahora
                            </button>
                        </div>
                    </div>

                    <!-- Anuncio 3 -->
                    <div class="item-anuncio hidden flex flex-col md:flex-row items-center justify-between gap-6 w-full">
                        <div class="space-y-3 text-center md:text-left max-w-xl">
                            <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-full text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-1.5 transition-transform hover:scale-105">
                                <i class="fas fa-ticket-alt"></i> Reserva Express
                            </span>
                            <h3 class="text-2xl sm:text-3xl font-black leading-tight text-white tracking-wide">
                                Evita Filas y Compra tu Cupo Online
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-300">
                                Inicia sesión, selecciona tu horario de preferencia y confirma tu reserva al instante desde cualquier dispositivo.
                            </p>
                        </div>
                        <div class="shrink-0">
                            <button onclick="abrirPanel('panelLogin')" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-500/20 transition-all duration-300 hover:scale-105 hover:-translate-y-0.5 inline-block">
                                Iniciar Sesión
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Botones de Navegación Lateral (Anterior / Siguiente) -->
                <button onclick="cambiarAnuncioManual(-1)" class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/20 hover:bg-black/50 text-white backdrop-blur-md flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 hover:scale-110">
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>
                <button onclick="cambiarAnuncioManual(1)" class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/20 hover:bg-black/50 text-white backdrop-blur-md flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 hover:scale-110">
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>

                <!-- Puntos Indicadores -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2">
                    <button onclick="seleccionarAnuncio(0)" class="dot-anuncio w-2.5 h-2.5 rounded-full bg-white opacity-100 transition-all duration-300 hover:scale-125"></button>
                    <button onclick="seleccionarAnuncio(1)" class="dot-anuncio w-2.5 h-2.5 rounded-full bg-white opacity-40 transition-all duration-300 hover:scale-125"></button>
                    <button onclick="seleccionarAnuncio(2)" class="dot-anuncio w-2.5 h-2.5 rounded-full bg-white opacity-40 transition-all duration-300 hover:scale-125"></button>
                </div>

                <!-- Barra de Progreso Temporal -->
                <div class="w-full bg-white/10 h-1 absolute bottom-0 left-0">
                    <div id="barraTemporizador" class="h-full bg-gradient-to-r from-sky-400 to-amber-400 animar-barra"></div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN: VIAJA CON NOSOTROS (VIAJES ACTIVOS Y DISPONIBLES) -->
        <section id="viajes-disponibles" class="py-12 px-6 max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-6">
                <div>
                    <span class="text-xs font-extrabold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Salidas Programadas
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">Viaja Con Nosotros</h2>
                </div>
                <div class="w-full md:w-72">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="text" id="buscadorViajes" onkeyup="filtrarViajes()" placeholder="Buscar por origen o destino..." class="w-full pl-9 pr-4 py-2 bg-white dark:bg-white/[0.03] border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500 shadow-sm">
                    </div>
                </div>
            </div>

            <!-- Grilla de Tarjetas Dinámicas -->
            <div id="contenedorTarjetas" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
                    <?php while ($viaje = mysqli_fetch_assoc($resultado)): 
                        $origen = !empty($viaje['ori_rut']) ? $viaje['ori_rut'] : ($viaje['nom_rut'] ?? $viaje['nom_via']);
                        $destino = !empty($viaje['des_rut']) ? $viaje['des_rut'] : '';
                        $nombreMostrar = (!empty($origen) && !empty($destino)) ? "$origen - $destino" : ($viaje['nom_via'] ?? $viaje['nom_rut']);
                    ?>
                        <div class="tarjeta-viaje bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-xl hover:shadow-sky-500/10 border-t-4 border-t-sky-500 flex flex-col justify-between hover:-translate-y-1 transition-all duration-300 group" 
                             data-origen="<?= strtolower(htmlspecialchars($origen)) ?>" 
                             data-destino="<?= strtolower(htmlspecialchars($destino)) ?>"
                             data-nombre="<?= strtolower(htmlspecialchars($nombreMostrar)) ?>">
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-lg">
                                        <i class="fas fa-route"></i>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php if (isset($viaje['cup_dis']) && $viaje['cup_dis'] !== null): ?>
                                            <span class="px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 text-[10px] font-extrabold uppercase">
                                                <?= htmlspecialchars($viaje['cup_dis']) ?> Cupos Libres
                                            </span>
                                        <?php endif; ?>
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold uppercase">
                                            <?= htmlspecialchars(strtoupper($viaje['est_via'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">
                                        <?= htmlspecialchars($nombreMostrar) ?>
                                    </h3>
                                    <?php if (!empty($viaje['conductor'])): ?>
                                        <p class="text-xs text-slate-400 font-bold mt-1 flex items-center gap-1.5">
                                            <i class="fas fa-user-circle text-sky-500"></i>
                                            <?= htmlspecialchars($viaje['conductor']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="space-y-2 pt-1">
                                    <?php if (!empty($viaje['fec_via'])): ?>
                                        <div class="flex items-center gap-2 bg-slate-50 dark:bg-white/[0.03] px-3 py-2 rounded-xl border border-slate-100 dark:border-white/5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            <i class="far fa-calendar-alt text-sky-500"></i>
                                            <span><?= date('Y-m-d', strtotime($viaje['fec_via'])) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($viaje['hor_sal_via'])): ?>
                                        <div class="flex items-center gap-2 bg-slate-50 dark:bg-white/[0.03] px-3 py-2 rounded-xl border border-slate-100 dark:border-white/5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            <i class="far fa-clock text-sky-500"></i>
                                            <span><?= date('h:i A', strtotime($viaje['hor_sal_via'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-extrabold uppercase text-slate-400">Pasaje</p>
                                    <p class="text-lg font-black text-emerald-500 dark:text-emerald-400">
                                        $<?= number_format($viaje['val_via'] ?? 0, 0, ',', '.') ?>
                                    </p>
                                </div>

                                <?php if (isset($_SESSION['usuario'])): ?>
                                    <a href="reservar.php?id=<?= $viaje['id_via'] ?>" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold transition-all text-xs shadow-md shadow-blue-500/20">
                                        Reservar
                                    </a>
                                <?php else: ?>
                                    <button onclick="abrirPanel('panelLogin')" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold transition-all text-xs shadow-md shadow-blue-500/20 cursor-pointer">
                                        Reservar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12 bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl">
                        <i class="fas fa-route text-4xl text-slate-400 mb-3"></i>
                        <p class="text-sm font-bold text-slate-500 dark:text-slate-400">No hay viajes programados en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECCIÓN: SERVICIOS Y VENTAJAS -->
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
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Control automatizado de itinerarios y reservas de ruta al instante.</p>
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
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Historiales detallados de uso de servicios para la toma estratégica de decisiones.</p>
                </div>
            </div>
        </section>

    </main>

    <!-- BOTÓN FLOTANTE PARA VOLVER AL INICIO -->
    <a href="#inicio" id="btnVolverArriba" class="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-sky-500 text-slate-950 shadow-lg shadow-sky-500/30 flex items-center justify-center text-lg font-bold opacity-0 pointer-events-none transition-all duration-300 hover:bg-sky-400 hover:scale-110 cursor-pointer group">
        <i class="fas fa-chevron-up group-hover:-translate-y-0.5 transition-transform"></i>
    </a>

    <footer class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10 bg-white/50 dark:bg-[#0b0f19]/50">
        <p>&copy; 2026 SGET - Sistema de Gestión de Transporte. Todos los derechos reservados.</p>
    </footer>

    <!-- MODAL DE LOGIN -->
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
                    <h4 class="text-xl font-bold mt-4">Control total de tus viajes</h4>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed my-4">Gestiona rutas, horarios y monitorea las opciones de transporte de manera eficiente.</p>
                <div class="text-[11px] text-slate-400 border-t border-white/10 pt-4">¿No tienes cuenta? <button type="button" onclick="cambiarAPanel('panelRegistro')" class="text-sky-400 font-semibold hover:underline cursor-pointer">Regístrate aquí</button></div>
            </div>
        </div>
    </div>

    <!-- MODAL DE REGISTRO OPTIMIZADO -->
    <div id="panelRegistro" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md hidden opacity-0 transition-all duration-300">
        <div class="relative w-full max-w-4xl bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row transform scale-95 transition-all duration-300" id="contenidoRegistro">
            <button type="button" onclick="cerrarPanel('panelRegistro')" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">
                <i class="fas fa-times text-sm"></i>
            </button>
            <div class="w-full md:w-1/2 bg-gradient-to-br from-blue-950 to-slate-900 p-8 sm:p-12 flex flex-col justify-between text-white relative overflow-hidden">
                <div>
                    <span class="px-3 py-1 bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-full text-[10px] font-bold uppercase tracking-wider">Únete hoy</span>
                    <h4 class="text-xl font-bold mt-4">Crea tu cuenta de usuario</h4>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed my-4">Registra tus datos personales para acceder a la reserva de tiquetes y consulta de itinerarios en SGET.</p>
                <div class="text-[11px] text-slate-400 border-t border-white/10 pt-4">¿Ya tienes cuenta? <button type="button" onclick="cambiarAPanel('panelLogin')" class="text-sky-400 font-semibold hover:underline cursor-pointer">Inicia sesión</button></div>
            </div>
            
            <div class="w-full md:w-1/2 p-6 sm:p-10 flex flex-col justify-center space-y-4 overflow-y-auto max-h-[85vh]">
                <div class="border-b border-slate-200 dark:border-white/10 pb-3">
                    <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">Nuevo Registro</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Diligencia la siguiente información</p>
                </div>
                <form action="nuevo_usuario.php" method="POST" class="space-y-3">
                    <div class="relative">
                        <i class="fas fa-user absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="text" name="nom_usu" required placeholder="Nombre Completo" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                    
                    <div class="relative">
                        <i class="fas fa-id-card absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="text" name="doc_usu" required placeholder="N° de Documento" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>

                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="email" name="corre_usu" required placeholder="Correo Electrónico" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>

                    <div class="relative">
                        <i class="fas fa-phone absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="tel" name="tel_usu" placeholder="Teléfono / Celular" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>

                    <div class="relative">
                        <i class="fas fa-lock absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="password" name="clave_usu" required placeholder="Contraseña" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>

                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-slate-950 font-extrabold rounded-xl shadow-lg hover:opacity-90 transition-all text-xs tracking-wider uppercase cursor-pointer mt-2">
                        Registrarse Ahora
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
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

        // BUSCADOR EN TIEMPO REAL
        function filtrarViajes() {
            const filtro = document.getElementById('buscadorViajes').value.toLowerCase();
            const tarjetas = document.querySelectorAll('.tarjeta-viaje');

            tarjetas.forEach(tarjeta => {
                const origen = tarjeta.getAttribute('data-origen') || '';
                const destino = tarjeta.getAttribute('data-destino') || '';
                const nombre = tarjeta.getAttribute('data-nombre') || '';

                if (origen.includes(filtro) || destino.includes(filtro) || nombre.includes(filtro)) {
                    tarjeta.style.display = "flex";
                } else {
                    tarjeta.style.display = "none";
                }
            });
        }

        // LÓGICA Y ANIMACIÓN DEL CARRUSEL DE ANUNCIOS
        let indiceAnuncioActual = 0;
        let temporizador;
        const anuncios = document.querySelectorAll('.item-anuncio');
        const puntos = document.querySelectorAll('.dot-anuncio');
        const barra = document.getElementById('barraTemporizador');

        function resetearBarraProgreso() {
            if (!barra) return;
            barra.classList.remove('animar-barra');
            void barra.offsetWidth; // Forzar reflow en el navegador
            barra.classList.add('animar-barra');
        }

        function mostrarAnuncio(index) {
            anuncios.forEach((anuncio, i) => {
                if (i === index) {
                    anuncio.classList.remove('hidden');
                    anuncio.classList.add('slide-in');
                    puntos[i].classList.remove('opacity-40');
                    puntos[i].classList.add('opacity-100', 'scale-125');
                } else {
                    anuncio.classList.add('hidden');
                    anuncio.classList.remove('slide-in');
                    puntos[i].classList.remove('opacity-100', 'scale-125');
                    puntos[i].classList.add('opacity-40');
                }
            });
            indiceAnuncioActual = index;
            resetearBarraProgreso();
        }

        function iniciarTemporizador() {
            clearInterval(temporizador);
            temporizador = setInterval(() => {
                let siguienteIndice = (indiceAnuncioActual + 1) % anuncios.length;
                mostrarAnuncio(siguienteIndice);
            }, 6000);
        }

        function seleccionarAnuncio(index) {
            mostrarAnuncio(index);
            iniciarTemporizador();
        }

        function cambiarAnuncioManual(direccion) {
            let nuevoIndice = (indiceAnuncioActual + direccion + anuncios.length) % anuncios.length;
            seleccionarAnuncio(nuevoIndice);
        }

        // Iniciar el carrusel al cargar
        iniciarTemporizador();

        // LÓGICA DE VISIBILIDAD DEL BOTÓN FLOTANTE
        const btnVolverArriba = document.getElementById('btnVolverArriba');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 250) {
                btnVolverArriba.classList.remove('opacity-0', 'pointer-events-none');
                btnVolverArriba.classList.add('opacity-100');
            } else {
                btnVolverArriba.classList.remove('opacity-100');
                btnVolverArriba.classList.add('opacity-0', 'pointer-events-none');
            }
        });
    </script>
</body>
</html>