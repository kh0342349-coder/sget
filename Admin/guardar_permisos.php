<?php
// Archivo: Admin/guardar_permisos.php
session_start();
include '../assets/conexion.php';

// Validar que solo un Administrador (rol 1) pueda ejecutar esta acción[cite: 1]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usu = intval($_POST['id_usu'] ?? 0);
    
    // Si se marcaron restricciones, las une con coma. Si no se marcó ninguna, queda como NULL[cite: 1]
    $restricciones_array = $_POST['restricciones'] ?? [];
    $restricciones_str = !empty($restricciones_array) ? implode(',', array_map('trim', $restricciones_array)) : NULL;

    if ($id_usu > 0) {
        $stmt = $conexion->prepare("UPDATE usuario SET restricciones = ? WHERE id_usu = ?");
        $stmt->bind_param("si", $restricciones_str, $id_usu);
        
        if ($stmt->execute()) {
            $_SESSION['msg_admin'] = "Permisos actualizados correctamente.";
        } else {
            $_SESSION['msg_admin_error'] = "Error al actualizar los permisos.";
        }
        $stmt->close();
    }
}

header("Location: admin.php");
exit();
?>
```[cite: 1]

---

### 3. Cómo proteger los módulos principales (Ejemplos completos)

Para que el bloqueo surta efecto real, coloca la validación de `AuthHelper` al inicio de cada archivo de módulo principal.

#### A. Módulo de Viajes (`Admin/viajes.php`)[cite: 11]
```php
<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 
require_once '../helpers/AuthHelper.php';

// 1. Verificación de seguridad (Solo Admin)[cite: 11]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// 2. BLOQUEO DE SEGURIDAD POR RESTRICCIONES
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'viajes');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// ... (El resto de tus consultas y código HTML de viajes.php continúa aquí) ...
?>
```[cite: 11]

#### B. Módulo de Rutas (`Admin/rutas.php`)[cite: 8]
```php
<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// 1. Verificación de seguridad (Solo Admin)[cite: 8]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// 2. BLOQUEO DE SEGURIDAD POR RESTRICCIONES
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'rutas');

// Consultar rutas registradas
$sql_rutas = "SELECT * FROM rutas ORDER BY id_rut DESC";
$resultado_rutas = mysqli_query($conexion, $sql_rutas);

// ... (El resto de tu código HTML de rutas.php continúa aquí) ...
?>
```[cite: 8]

#### C. Módulo de Vehículos (`Admin/vehiculos.php`)[cite: 10]
```php
<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad (Solo Admin)[cite: 10]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'vehiculos');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Consultamos los vehículos registrados
$query = "SELECT * FROM vehiculo ORDER BY id_veh DESC";
$resultado = $conexion->query($query);

// ... (El resto de tu código HTML de vehiculos.php continúa aquí) ...
?>
```[cite: 10]

Con estos códigos completos ya implementados, cada vez que desmarques un módulo en la interfaz de permisos y lo guardes, al intentar ingresar a dicho módulo el sistema mostrará inmediatamente la pantalla de **Acceso Restringido**.