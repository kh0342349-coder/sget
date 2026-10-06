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
    /* ------------------------------------------------------------------ */
    /* Son TEXTO, no 0/1. El 0/1 anterior obligaba a que «en mantenimiento»   */
    /* y «fuera de servicio» fueran lo mismo, y a que al asignar un viaje la   */
    /* unidad quedara marcada como averiada. Ahora cada situación tiene su     */
    /* propio valor y `Asignado` lo pone y lo quita el sistema solo.           */
    public const VEH_DISPONIBLE     = 'Disponible';
    public const VEH_ASIGNADO       = 'Asignado';
    public const VEH_MANTENIMIENTO  = 'Mantenimiento';
    public const VEH_FUERA_SERVICIO = 'Fuera de servicio';

    /** Catálogo completo, en el orden en el que se muestran al administrador. */
    public const VEH_ESTADOS = [
        self::VEH_DISPONIBLE,
        self::VEH_ASIGNADO,
        self::VEH_MANTENIMIENTO,
        self::VEH_FUERA_SERVICIO,
    ];

    /** Estados en los que una unidad NO puede recibir un viaje nuevo. */
    public const VEH_ESTADOS_NO_ASIGNABLES = [
        self::VEH_ASIGNADO,
        self::VEH_MANTENIMIENTO,
        self::VEH_FUERA_SERVICIO,
    ];

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
    /* Freno a la fuerza bruta (persistencia en servidor)                   */
    /* ------------------------------------------------------------------ */
    /**
     * ANTES el contador vivía en `$_SESSION['sget_intentos']`, así que crear
     * una sesión nueva (borrar cookies, pestaña privada, `curl`) reiniciaba el
     * contador y el freno no protegía nada. Ahora el estado se guarda en la
     * tabla `sget_login_intentos`, con la clave `documento|IP`:
     *
     *   · 5  intentos fallidos  -> bloqueo de 5 minutos
     *   · 10 intentos fallidos  -> bloqueo de 15 minutos
     *   · la ventana se reinicia si pasan 30 min sin fallos
     */
    public const LOGIN_MAX_INTENTOS       = 5;
    public const LOGIN_MAX_INTENTOS_ALTO  = 10;
    public const LOGIN_BLOQUEO_SEGUNDOS   = 300;
    public const LOGIN_BLOQUEO_ALTO_SEG   = 900;
    public const LOGIN_VENTANA_SEGUNDOS   = 1800;

    /* ------------------------------------------------------------------ */
    /* reCAPTCHA                                                          */
    /* ------------------------------------------------------------------ */
    /**
     * El captcha está ACTIVO POR DEFECTO y se configura por entorno, no a fuego
     * en el código.
     *
     * POR QUÉ SIGUE SIENDO OBLIGATORIO (Y NO OPCIONAL)
     *   La versión anterior solo verificaba el token «si venía»:
     *
     *       if ($respuestaRecaptcha !== '') { …verificar… }
     *
     *   Es decir, sin token se entraba igual. Eso no era una protección, era una
     *   decoración: el widget estaba en pantalla y no bloqueaba nada.
     *   Ahora, cuando el captcha está activo, la ausencia de token es un RECHAZO
     *   siempre.
     *
     * QUÉ CAMBIÓ DESDE LA ÚLTIMA REVISIÓN
     *   El captcha se había dejado apagado por defecto, lo que hizo
     *   DESAPAREZER de la pantalla de acceso. Eso no era lo pedido: el control
     *   debe seguir ahí. Por eso vuelve a estar activo, pero con una diferencia
     *   importante: el sistema DECLARE si está protegiendo de verdad.
     *
     *   · Con claves reales (SGET_RECAPTCHA_SITEKEY / _SECRET)  -> protege.
     *   · Sin claves configuradas -> se usan las claves PÚBLICAS DE PRUEBA de
     *     Google, que validan cualquier token. En ese modo el aviso de
     *     `recaptchaEnModoPrueba()` se muestra en el propio formulario: no es
     *     una falsa sensación de seguridad, es un estado declarado.
     *
     * VARIABLES DE ENTORNO
     *   SGET_RECAPTCHA_ENABLED = 1|0     activa/desactiva (por defecto, 1)
     *   SGET_RECAPTCHA_SITEKEY = ...     clave de sitio real
     *   SGET_RECAPTCHA_SECRET  = ...     clave secreta real
     */
    public const RECAPTCHA_SITEKEY_PRUEBA = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
    public const RECAPTCHA_SECRET_PRUEBA  = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

    public static function recaptchaHabilitado(): bool
    {
        $flag = getenv('SGET_RECAPTCHA_ENABLED');

        // Sin variable: ACTIVO. Solo se apaga si se pide explícitamente.
        if ($flag === false || trim((string)$flag) === '') {
            return true;
        }
        return !in_array(strtolower(trim((string)$flag)), ['0', 'false', 'no', 'off'], true);
    }

    public static function recaptchaSecret(): string
    {
        $v = getenv('SGET_RECAPTCHA_SECRET');
        if ($v !== false && trim((string)$v) !== '') {
            return trim((string)$v);
        }
        return self::RECAPTCHA_SECRET_PRUEBA;
    }

    public static function recaptchaSiteKey(): string
    {
        $v = getenv('SGET_RECAPTCHA_SITEKEY');
        return ($v !== false && trim((string)$v) !== '') ? trim((string)$v) : self::RECAPTCHA_SITEKEY_PRUEBA;
    }

    /**
     * ¿Estamos con las claves de PRUEBA de Google?
     *
     * Es el único modo en el que el captcha NO bloquea nada: Google acepta
     * cualquier token. Se declara en pantalla para que nadie crea que el
     * acceso está protegido cuando en realidad no lo está.
     */
    public static function recaptchaEnModoPrueba(): bool
    {
        return self::recaptchaHabilitado()
            && self::recaptchaSiteKey() === self::RECAPTCHA_SITEKEY_PRUEBA;
    }

    /* ------------------------------------------------------------------ */
    /* Subida de imágenes                                                  */
    /* ------------------------------------------------------------------ */
    /** Formatos de imagen aceptados en todo el sistema (los mismos 4). */
    public const IMG_EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];

    /** MIME reales aceptados para cada extensión: no se confía en el nombre. */
    public const IMG_MIMES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public const IMG_MAX_BYTES      = 3 * 1024 * 1024;
    public const IMG_MAX_ANCHO      = 4000;
    public const IMG_MAX_ALTO       = 4000;
    public const IMG_MIN_ANCHO      = 40;
    public const IMG_MIN_ALTO       = 40;

    /* ------------------------------------------------------------------ */
    /* Reglas de negocio                                                   */
    /* ------------------------------------------------------------------ */
    /** Minutos de inactividad antes de bloquear la sesion. */
    public const MINUTOS_INACTIVIDAD = 2;

    /** Segundos de gracia para escribir la contraseña antes de expirar. */
    public const SEGUNDOS_GRACIA_INACTIVIDAD = 60;

    /* ------------------------------------------------------------------ */
    /* Duración de los viajes                                              */
    /* ------------------------------------------------------------------ */
    /** Minutos de trayecto por defecto si la ruta no define duración. */
    public const DURACION_VIAJE_MIN_POR_DEFECTO = 120;

    /** Minutos de margen antes de cerrar automáticamente un viaje vencido. */
    public const MARGEN_CIERRE_AUTOMATICO_MIN = 15;

    /**
     * Margen OPERATIVO entre dos viajes del mismo conductor o vehículo.
     *
     * Es lo que impide que un recurso quede «libre» justo en el mismo minuto en
     * que termina el viaje anterior: hace falta un respiro para bajar, limpiar
     * y volver a salir.
     *
     *     Salida 10:00 · Llegada 12:00 · Margen 15 min -> libre a las 12:15
     *
     * Antes este valor no existía: la disponibilidad se decidía solo con
     * `est_via IN ('Programado','En curso')`, así que un conductor quedaba
     * bloqueado desde que se programaba el viaje y hasta que acababa, sin
     * importar la hora, y no podía hacer dos viajes el mismo día.
     *
     * Vive AQUÍ y solo aquí: `DisponibilidadService` es su único consumidor.
     */
    public const MARGEN_DISPONIBILIDAD_MIN = 15;

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
