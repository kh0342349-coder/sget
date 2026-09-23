<?php
// Archivo: Admin/rutas.php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'rutas');

$mensaje = "";

// PROCESAMIENTO DE CREACIÓN Y EDICIÓN DE RUTAS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $nom_rut = trim($_POST['nom_rut'] ?? '');
    $val_rut = floatval($_POST['val_rut'] ?? 0);
    $id_rut = intval($_POST['id_rut'] ?? 0);

    // Manejo de imagen
    $img_rut = $_POST['imagen_actual'] ?? '';
    if (isset($_FILES['img_rut']) && $_FILES['img_rut']['error'] == 0) {
        $ext = pathinfo($_FILES['img_rut']['name'], PATHINFO_EXTENSION);
        $img_rut = 'ruta_' . time() . '.' . $ext;
        if (!is_dir('../img/rutas/')) {
            mkdir('../img/rutas/', 0777, true);
        }
        move_uploaded_file($_FILES['img_rut']['tmp_name'], '../img/rutas/' . $img_rut);
    }

    if ($accion === 'crear') {
        $stmt = $conexion->prepare("INSERT INTO rutas (nom_rut, val_rut, img_rut) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $nom_rut, $val_rut, $img_rut);
        if ($stmt->execute()) {
            $mensaje = "
            <div class='bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl shadow-lg flex items-center gap-3 mb-6'>
                <i class='fas fa-check-circle text-lg'></i>
                <span class='text-sm font-semibold'>¡Ruta creada exitosamente!</span>
            </div>";
        } else {
            $mensaje = "
            <div class='bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl shadow-lg flex items-center gap-3 mb-6'>
                <i class='fas fa-times-circle text-lg'></i>
                <span class='text-sm font-semibold'>Error al crear la ruta.</span>
            </div>";
        }
    } elseif ($accion === 'editar' && $id_rut > 0) {
        $stmt = $conexion->prepare("UPDATE rutas SET nom_rut = ?, val_rut = ?, img_rut = ? WHERE id_rut = ?");
        $stmt->bind_param("sdsi", $nom_rut, $val_rut, $img_rut, $id_rut);
        if ($stmt->execute()) {
            $mensaje = "
            <div class='bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl shadow-lg flex items-center gap-3 mb-6'>
                <i class='fas fa-check-circle text-lg'></i>
                <span class='text-sm font-semibold'>¡Ruta actualizada exitosamente!</span>
            </div>";
        } else {
            $mensaje = "
            <div class='bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl shadow-lg flex items-center gap-3 mb-6'>
                <i class='fas fa-times-circle text-lg'></i>
                <span class='text-sm font-semibold'>Error al actualizar la ruta.</span>
            </div>";
        }
    }
}

// Consultar rutas registradas
$sql_rutas = "SELECT * FROM rutas ORDER BY id_rut DESC";
$resultado_rutas = mysqli_query($conexion, $sql_rutas);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Gestión de Rutas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style_admin.css">
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
    </script>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
    
    <?php include '../includes/sidebar.php'; ?>

    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        <?php include '../includes/header.php'; ?>

        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto w-full">
            
            <!-- ENCABEZADO CON BOTÓN NUEVA RUTA Y AYUDA -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Gestión de Rutas</h1>
                        
                        <!-- BOTÓN DE AYUDA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Administre los trayectos, origen, destino, tarifas base y fotos de despacho.</p>
                </div>
                
                <!-- BOTÓN NUEVA RUTA -->
                <button type="button" onclick="abrirDrawerCrear()" class="px-5 py-3 bg-gradient-to-r from-sky-500 to-blue-600 hover:opacity-90 text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 transition-all flex items-center gap-2 cursor-pointer">
                    <i class="fas fa-plus"></i> Nueva Ruta
                </button>
            </div>

            <?php if (!empty($mensaje)) echo $mensaje; ?>

            <!-- Listado en Tarjetas con Imágenes -->
            <?php if($resultado_rutas && mysqli_num_rows($resultado_rutas) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php while($r = mysqli_fetch_assoc($resultado_rutas)): ?>
                        <?php 
                            $nombreImagen = trim($r['img_rut'] ?? '');
                            $rutaImagen = !empty($nombreImagen) ? "../img/rutas/" . $nombreImagen : "";
                        ?>
                        <div class="relative overflow-hidden rounded-2xl h-52 border border-slate-200 dark:border-white/10 shadow-md group transition-all duration-300 hover:shadow-xl flex flex-col justify-between p-4 bg-slate-950">
                            
                            <?php if (!empty($nombreImagen) && file_exists("../img/rutas/" . $nombreImagen)): ?>
                                <img src="<?php echo htmlspecialchars($rutaImagen); ?>" 
                                    alt="<?php echo htmlspecialchars($r['nom_rut']); ?>" 
                                    class="absolute inset-0 w-full h-full object-cover object-center z-0 opacity-70 transition-transform duration-500 group-hover:scale-110">
                            <?php endif; ?>
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/40 to-black/60 z-0"></div>

                            <div class="relative z-10 flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold text-white/90 bg-black/60 px-2 py-0.5 rounded-md backdrop-blur-md border border-white/10">
                                    #<?php echo $r['id_rut']; ?>
                                </span>
                                <span class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-300 bg-emerald-900/60 px-2 py-0.5 rounded-full border border-emerald-500/40 flex items-center gap-1 backdrop-blur-md">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span> Activa
                                </span>
                            </div>

                            <div class="relative z-10 space-y-0.5 mt-auto mb-3">
                                <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 drop-shadow-md">
                                    $<?php echo number_format($r['val_rut'] ?? 0, 0, ',', '.'); ?> COP
                                </span>
                                <h3 class="font-black text-white text-lg tracking-tight leading-tight truncate drop-shadow-lg" title="<?php echo htmlspecialchars($r['nom_rut']); ?>">
                                    <?php echo htmlspecialchars($r['nom_rut']); ?>
                                </h3>
                            </div>

                            <div class="relative z-10 flex items-center gap-2 pt-2 border-t border-white/20">
                                <button type="button" 
                                        onclick="abrirDrawerEditar(<?php echo $r['id_rut']; ?>, '<?php echo htmlspecialchars($r['nom_rut'], ENT_QUOTES); ?>', <?php echo $r['val_rut'] ?? 0; ?>, '<?php echo htmlspecialchars($nombreImagen, ENT_QUOTES); ?>')"
                                        class="flex-1 text-center py-1.5 px-2 bg-blue-600/90 hover:bg-blue-600 text-white font-bold text-[10px] uppercase tracking-wider rounded-lg shadow-sm transition-all flex items-center justify-center gap-1 backdrop-blur-sm cursor-pointer">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <a href="eliminar.php?tipo=ruta&id=<?php echo $r['id_rut']; ?>" 
                                onclick="return confirm(window.SGET_I18N?.t('¿Confirma que desea eliminar esta ruta?') || 'Are you sure you want to delete this route?')"
                                class="flex-1 text-center py-1.5 px-2 bg-red-600/90 hover:bg-red-600 text-white font-bold text-[10px] uppercase tracking-wider rounded-lg shadow-sm transition-all flex items-center justify-center gap-1 backdrop-blur-sm">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                            </div>

                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-[#121826] rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl text-center">
                    <div class="w-16 h-16 rounded-2xl bg-sky-500/10 text-sky-400 flex items-center justify-center text-2xl mb-4">
                        <i class="fas fa-route"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">No hay rutas registradas</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Actualmente no existen trayectos creados en el sistema.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- PANEL LATERAL DESLIZANTE (DRAWER) DESDE LA DERECHA -->
    <div id="drawerOverlay" onclick="cerrarDrawerRuta()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="drawerRuta" class="fixed top-0 right-0 h-full w-full max-w-md bg-white dark:bg-[#121826] shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col border-l border-slate-200 dark:border-white/10 p-6 overflow-y-auto">
        
        <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-4 mb-6">
            <h3 id="drawerTitulo" class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                <i class="fas fa-route text-sky-400"></i> Registrar Nueva Ruta
            </h3>
            <button onclick="cerrarDrawerRuta()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <form action="rutas.php" method="POST" enctype="multipart/form-data" class="space-y-5 flex-grow flex flex-col justify-between">
            <div class="space-y-4">
                <input type="hidden" name="accion" id="input_accion" value="crear">
                <input type="hidden" name="id_rut" id="input_id_rut" value="">
                <input type="hidden" name="imagen_actual" id="input_imagen_actual" value="">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nombre del Trayecto / Ruta</label>
                    <input type="text" name="nom_rut" id="input_nom_rut" required placeholder="Ej.: Fusagasugá - Bogotá" data-i18n-placeholder-es="Ej.: Fusagasugá - Bogotá" data-i18n-placeholder-en="e.g. Fusagasugá - Bogotá" class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs text-slate-800 dark:text-white">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tarifa Base ($)</label>
                    <input type="number" name="val_rut" id="input_val_rut" step="0.01" required placeholder="0.00" data-i18n-placeholder-es="0.00" data-i18n-placeholder-en="0.00" class="w-full px-4 py-3 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs font-mono text-slate-800 dark:text-white">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Fotografía de la Ruta</label>
                    <input type="file" name="img_rut" accept="image/*" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl outline-none focus:border-sky-400 text-xs text-slate-400 file:mr-4 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-500/10 file:text-sky-400 hover:file:bg-sky-500/20 cursor-pointer">
                    <p class="text-[10px] text-slate-500 mt-1">Formatos permitidos: JPG, PNG. (Opcional al editar)</p>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-white/5 flex gap-3">
                <button type="button" onclick="cerrarDrawerRuta()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-300 rounded-xl text-xs font-bold uppercase tracking-wider cursor-pointer">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer">
                    Guardar Ruta
                </button>
            </div>
        </form>

    </div>

    <!-- MODAL DE AYUDA -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía de Gestión de Rutas
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-plus-circle text-sky-400 mt-0.5"></i>
                    <span><b>Nueva Ruta:</b> Despliega el panel lateral derecho para registrar un nuevo trayecto especificando su nombre, tarifa e imagen.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-edit text-blue-400 mt-0.5"></i>
                    <span><b>Editar Ruta:</b> Despliega el mismo panel lateral precargando los datos del trayecto seleccionado.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-trash text-red-400 mt-0.5"></i>
                    <span><b>Eliminar:</b> Remueve permanentemente el trayecto del sistema.</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL -->
    <script>
    function abrirDrawerCrear() {
        document.getElementById('drawerTitulo').innerHTML = '<i class="fas fa-route text-sky-400"></i> Registrar Nueva Ruta';
        document.getElementById('input_accion').value = 'crear';
        document.getElementById('input_id_rut').value = '';
        document.getElementById('input_nom_rut').value = '';
        document.getElementById('input_val_rut').value = '';
        document.getElementById('input_imagen_actual').value = '';

        document.getElementById('drawerOverlay').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('drawerOverlay').classList.add('opacity-100', 'pointer-events-auto');
        document.getElementById('drawerRuta').classList.remove('translate-x-full');
        document.getElementById('drawerRuta').classList.add('translate-x-0');
    }

    function abrirDrawerEditar(id, nombre, valor, imagen) {
        document.getElementById('drawerTitulo').innerHTML = '<i class="fas fa-edit text-sky-400"></i> Editar Ruta #' + id;
        document.getElementById('input_accion').value = 'editar';
        document.getElementById('input_id_rut').value = id;
        document.getElementById('input_nom_rut').value = nombre;
        document.getElementById('input_val_rut').value = valor;
        document.getElementById('input_imagen_actual').value = imagen;

        document.getElementById('drawerOverlay').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('drawerOverlay').classList.add('opacity-100', 'pointer-events-auto');
        document.getElementById('drawerRuta').classList.remove('translate-x-full');
        document.getElementById('drawerRuta').classList.add('translate-x-0');
    }

    function cerrarDrawerRuta() {
        document.getElementById('drawerRuta').classList.remove('translate-x-0');
        document.getElementById('drawerRuta').classList.add('translate-x-full');
        document.getElementById('drawerOverlay').classList.remove('opacity-100', 'pointer-events-auto');
        document.getElementById('drawerOverlay').classList.add('opacity-0', 'pointer-events-none');
    }

    function abrirModalAyuda() {
        document.getElementById('overlayAyuda').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('overlayAyuda').classList.add('opacity-100', 'pointer-events-auto');
        document.getElementById('modalAyuda').classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        document.getElementById('modalAyuda').classList.add('opacity-100', 'pointer-events-auto', 'scale-100');
    }

    function cerrarModalAyuda() {
        document.getElementById('modalAyuda').classList.remove('opacity-100', 'pointer-events-auto', 'scale-100');
        document.getElementById('modalAyuda').classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        document.getElementById('overlayAyuda').classList.remove('opacity-100', 'pointer-events-auto');
        document.getElementById('overlayAyuda').classList.add('opacity-0', 'pointer-events-none');
    }
    </script>
</body>
</html>