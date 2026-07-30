<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 

// Verificación de sesión y rol de administrador administrativo
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Registrar Nuevo Vehículo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'bg-principal': '#0b0f19',
                        'bg-tarjeta': '#1e293b',
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7',
                        'color-mutado': '#94a3b8'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght=400;500;600;700;800&display=swap');
    </style>
</head>
<body class="bg-bg-principal flex min-h-screen antialiased text-slate-100">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col">
        
        <header class="h-16 bg-bg-tarjeta/80 backdrop-blur-md border-b border-white/5 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="text-color-mutado font-medium text-sm tracking-wide">
                Flota &nbsp;/&nbsp; <span class="text-white font-semibold">Alta de Unidad</span>
            </div>
            <div class="flex items-center space-x-6">
                <div class="hidden md:block text-right">
                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($nombreReal); ?></p>
                    <p class="text-[10px] text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span> Admin
                    </p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-tr from-neon-azul to-neon-morado rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-md shadow-neon-azul/10">
                    <?php echo strtoupper(substr($nombreReal, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 flex items-center justify-center p-8">
            <div class="bg-bg-tarjeta w-full max-w-lg rounded-2xl shadow-2xl border border-white/5 overflow-hidden relative backdrop-blur-sm">
                
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-blue-500 via-indigo-500 to-neon-morado"></div>

                <div class="p-8 border-b border-white/5 text-center bg-gradient-to-b from-white/[0.01] to-transparent">
                    <div class="w-14 h-14 bg-gradient-to-tr from-blue-600/20 to-indigo-600/20 text-neon-azul rounded-2xl flex items-center justify-center mx-auto mb-4 border border-white/5 shadow-inner">
                        <i class="fas fa-car-side text-xl"></i>
                    </div>
                    <h2 class="text-xl font-extrabold text-white tracking-tight">Registrar Nuevo Vehículo</h2>
                    <p class="text-color-mutado text-xs mt-1">Ingrese los datos técnicos requeridos para vincular la unidad al sistema.</p>
                </div>

                <form action="guardar_vehiculo.php" method="POST" class="p-8 space-y-6">
                    
                    <div class="space-y-2">
                        <label class="block text-[10px] font-bold text-color-mutado uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-id-card text-neon-azul"></i> Matrícula / Placa Identificadora
                        </label>
                        <div class="relative group">
                            <input type="text" name="pla_veh" required placeholder="EJ: FUSA-123" maxlength="7"
                                   class="w-full pl-14 pr-4 py-3.5 bg-bg-principal/60 border border-white/5 rounded-xl outline-none focus:border-neon-azul/50 text-white font-mono font-bold text-lg tracking-widest uppercase transition-all shadow-inner placeholder-white/10">
                            <div class="absolute inset-y-0 left-0 flex items-center px-4 pointer-events-none text-white/30 border-r border-white/5 bg-white/[0.02] rounded-l-xl">
                                <i class="fas fa-hashtag text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-bold text-color-mutado uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-tag text-neon-azul"></i> Línea / Modelo / Descripción
                        </label>
                        <div class="relative">
                            <input type="text" name="mode_veh" required placeholder="Ej: Chevrolet Sail 2023"
                                   class="w-full pl-12 pr-4 py-3 bg-bg-principal/60 border border-white/5 rounded-xl outline-none focus:border-neon-azul/50 text-white text-sm transition-all placeholder-white/20">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-white/20">
                                <i class="fas fa-car text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-color-mutado uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-users text-neon-azul"></i> Capacidad Total
                            </label>
                            <div class="relative">
                                <input type="number" name="cap_veh" required placeholder="0" min="1"
                                       class="w-full pl-11 pr-16 py-3 bg-bg-principal/60 border border-white/5 rounded-xl outline-none focus:border-neon-azul/50 text-white font-mono font-bold text-sm transition-all placeholder-white/20">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-white/20">
                                    <i class="fas fa-layer-group text-xs"></i>
                                </div>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-[9px] font-bold uppercase text-color-mutado tracking-wider">
                                    puestos
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-color-mutado uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-info-circle text-neon-azul"></i> Estado Inicial
                            </label>
                            <div class="w-full px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl font-extrabold text-emerald-400 text-xs tracking-wider flex items-center gap-2 shadow-inner h-[46px]">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse inline-block"></span>
                                DISPONIBLE (Automático)
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-white/5">
                        <a href="vehiculos.php" class="flex-1 text-center py-3.5 bg-white/5 border border-white/10 hover:bg-white/10 text-color-mutado hover:text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                            Cancelar
                        </a>
                        <button type="submit" class="flex-1 py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/10 hover:opacity-95 transition-all">
                            Guardar Unidad
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>