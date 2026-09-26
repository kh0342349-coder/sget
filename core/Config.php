<?php
/**
 * core/Config.php
 * -----------------------------------------------------------------------------
 * FUENTE UNICA DE VERDAD para credenciales, rutas y estados del sistema.
 * Cualquier otro archivo debe leer de aquí; nunca hardcodear valoresagain.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class Config
{
    /* ------------------------------------------------------------------ */
    /* Base de datos                                                       */
    /* (el nombre se puede sobreescribir con la variable de entorno        */
    /*  SGET_DB_NAME, útil para probar migraciones sobre una copia)         */
    /* ------------------------------------------------------------------ */
    public const DB_HOST = '127.0.0.1';
    public const DB_USER = 'root';
    public const DB_PASS = '';
    public const DB_NAME = 'sget';
    public const DB_CHARSET = 'utf8mb4';

    public static function dbName(): string
    {
        $env = getenv('SGET_DB_NAME');
        return ($env !== false && $env !== '') ? $env : self::DB_NAME;
    }

    /* ------------------------------------------------------------------ */
    /* Rutas base                                                          */
    /* ------------------------------------------------------------------ */
    public const BASE_URL = '/sget';
    public const RUTAS_IMG = 'img/rutas';

    /* ------------------------------------------------------------------ */
    /* Estados de USUARIO  (tabla usuario.estado)                          */
    /* antes: NULL / 0 / 1 mezclados  ->  ahora 1 = Activo, 0 = Inactivo   */
    /* ------------------------------------------------------------------ */
    public const USU_ACTIVO     = 1;
    public const USU_INACTIVO   = 0;

    /* ------------------------------------------------------------------ */
    /* Estados de CONDUCTOR (tabla usuario.est_con_usu)                    */
    /* Regla: SIEMPRE 1 = Disponible / 0 = Ocupado. Nunca NULL ni texto.     */
    /* ------------------------------------------------------------------ */
    public const CON_DISPONIBLE = 1;
    public const CON_OCUPADO    = 0;

    /* ------------------------------------------------------------------ */
    /* Estados de VEHICULO (tabla vehiculo.est_veh)                        */
    /* antes existian 0/1 y las constantes(global) de 1/2 -> colision.      */
    /* ------------------------------------------------------------------ */
    public const VEH_DISPONIBLE      = 1;
    public const VEH_FUERA_SERVICIO  = 0;
    public const VEH_MANTENIMIENTO   = 0;

    /* ------------------------------------------------------------------ */
    /* Estados de VIAJE (tabla viaje.est_via) - ENUM real                  */
    /* ------------------------------------------------------------------ */
    public const VIA_PROGRAMADO = 'Programado';
    public const VIA_EN_CURSO   = 'En curso';
    public const VIA_FINALIZADO = 'Finalizado';
    public const VIA_CANCELADO  = 'Cancelado';

    public const VIA_ESTADOS = [
        self::VIA_PROGRAMADO,
        self::VIA_EN_CURSO,
        self::VIA_FINALIZADO,
        self::VIA_CANCELADO,
    ];

    /* Estados que NO se consideran disponibles para el despacho */
    public const VIA_ESTADOS_CERRADOS = [
        self::VIA_FINALIZADO,
        self::VIA_CANCELADO,
    ];

    /* ------------------------------------------------------------------ */
    /* Estados de RESERVA                                                  */
    /* ------------------------------------------------------------------ */
    public const RES_CONFIRMADA = 'Confirmada';
    public const RES_CANCELADA  = 'Cancelada';
    public const RES_PENDIENTE  = 'Pendiente';

    /* ------------------------------------------------------------------ */
    /* Roles                                                               */
    /* ------------------------------------------------------------------ */
    public const ROL_ADMIN     = 1;
    public const ROL_CONDUCTOR = 2;
    public const ROL_PASAJERO  = 3;

    /* ------------------------------------------------------------------ */
    /* Reglas de negocio                                                   */
    /* ------------------------------------------------------------------ */
    /** Minutos de inactividad antes de bloquear la sesion. */
    public const MINUTOS_INACTIVIDAD = 15;

    /** Longitud minima (caracteres) de la anotacion obligatoria de cancelacion. */
    public const MIN_ANOTACION_CANCELACION = 15;

    /** Tolerancia (minutos) para considerar que un viaje "ya salio". */
    public const TOLERANCIA_SALIDA_MIN = 0;

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */
    /** Ruta base de la aplicación (detectada, no fija). */
    public static function basePath(): string
    {
        // Si el proyecto vive en /sget, devuelve '/sget'; si está en la raíz, ''.
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        // Sube hasta la carpeta que contiene core/ (sirve aunque se anide)
        $raiz = Config::raiz();
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? $raiz) ?: $raiz);
        $base = str_replace('\\', '/', $raiz);
        if ($base !== '' && str_starts_with($base, $docRoot)) {
            $rel = rtrim(substr($base, strlen($docRoot)), '/');
        } else {
            $rel = rtrim($dir, '/');
        }
        return $rel === '/' ? '' : $rel;
    }

    public static function raiz(string $sub = ''): string
    {
        $base = dirname(__DIR__);
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }
}
