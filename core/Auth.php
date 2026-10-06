<?php
/**
 * core/Auth.php
 * -----------------------------------------------------------------------------
 * Sesion, roles y permisos. Sustituye a los chequeos inline dispersos en cada
 * pagina (`if (!isset($_SESSION['documento']) ... exit`), que era la causa
 * principal de modales/acciones accesibles sin autorizacion.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Password.php';

final class Auth
{
    public const ZONA_HORARIA = 'America/Bogota';

    private static bool $verificada = false;

    /* ------------------------------------------------------------------ */
    /**
     * Arranca (o reutiliza) la sesión.
     *
     * IMPORTANTE: NO se renombra la sesión. Muchas páginas heredadas hacen
     * `session_start()` ANTES de incluir `assets/conexion.php`, así que si
     * aquí se impusiera un nombre distinto, unas páginas y otras no verían la
     * misma sesión y el usuario aparecería “deslogueado” al navegar.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Ya había una sesión abierta (página heredada): solo se fija la zona
            // horaria, que es lo único que podría haber quedado inconsistente.
            date_default_timezone_set(self::ZONA_HORARIA);
            return;
        }

        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                // `secure` SOLO cuando la petición es realmente HTTPS.
                // Fijado a `true` sin mirar el protocolo (como hacia
                // `validar.php`), el navegador DESCARTA la cookie en la red
                // local (http://localhost/html/sget) y el login se pierde
                // justo después de iniciarse: el usuario entra y vuelve a la
                // portada como si la contraseña fuera incorrecta.
                'secure'   => self::esHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
        date_default_timezone_set(self::ZONA_HORARIA);
    }

    /** ¿La petición actual se sirve por HTTPS? */
    public static function esHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
        if (($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
        // Detrás de un proxy inverso (producción) el esquema llega en la cabecera.
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /* ------------------------------------------------------------------ */
    public static function id(): int
    {
        return (int) ($_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0);
    }

    public static function rol(): int
    {
        return (int) ($_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0);
    }

    public static function nombre(): string
    {
        return (string) ($_SESSION['nombre_usuario'] ?? $_SESSION['nom_usu'] ?? 'Usuario SGET');
    }

    public static function documento(): string
    {
        return (string) ($_SESSION['documento'] ?? '');
    }

    public static function estaLogueado(): bool
    {
        return self::id() > 0 && self::documento() !== '';
    }

    /* ------------------------------------------------------------------ */
    /* Guardias                                                            */
    /* ------------------------------------------------------------------ */

    /** Exige sesion activa; si no, redirige al login. */
    public static function requerirSesion(): void
    {
        if (!self::estaLogueado()) {
            self::redirigirLogin();
        }
        self::verificarInactividad();
    }

    /** Exige sesion + rol Admin (por defecto). */
    public static function requerirRol(int ...$roles): void
    {
        self::requerirSesion();
        $rolActual = self::rol();
        foreach ($roles as $r) {
            if ($rolActual === $r) {
                return;
            }
        }
        self::denegar();
    }

    public static function requerirAdmin(): void { self::requerirRol(Config::ROL_ADMIN); }

    /** Exige permiso sobre un modulo/recurso (reemplaza AuthHelper::requerirAcceso). */
    public static function requerirAcceso(string $recurso): void
    {
        self::requerirSesion();
        if (!self::tieneAcceso($recurso)) {
            self::denegar($recurso);
        }
    }

    /**
     * Exige una ACCION concreta sobre un modulo.
     *
     * Es la forma recomendada de proteger un endpoint: el boton puede seguir
     * oculto en el frontend, pero el endpoint exige el permiso aqui, en
     * backend. Ocultar el boton nunca es una medida de seguridad.
     */
    public static function exigir(string $modulo, string $accion): void
    {
        self::requerirSesion();
        $permiso = self::PERMISOS_ACCION[$modulo . '.' . $accion] ?? $modulo;
        if (!self::tieneAcceso($permiso)) {
            self::denegar($accion);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Mapa accion -> permiso requerido por cada endpoint                  */
    /* ------------------------------------------------------------------ */
    public const PERMISOS_ACCION = [
        // Rutas
        'ruta.guardar'       => 'crear_ruta',
        'ruta.eliminar'      => 'eliminar_ruta',
        'ruta.cambiarEstado' => 'suspender_ruta',

        // Vehiculos
        'vehiculo.guardar'      => 'registrar_vehiculo',
        'vehiculo.alternarEstado'=> 'suspender_vehiculo',
        'vehiculo.eliminar'     => 'registrar_vehiculo',

        // Usuarios y permisos
        'usuario.guardar'       => 'gestionar_usuarios',
        'usuario.cambiarEstado' => 'suspender_usuario',
        'usuario.eliminar'      => 'gestionar_usuarios',

        // Viajes
        'viaje.guardar'   => 'crear_viaje',
        // Cambiar el estado de un viaje propio lo puede hacer el CONDUCTOR con
        // su permiso `operar_viaje`; `gestionar_viajes` es el permiso de la
        // flota completa y sigue siendo del administrador.
        'viaje.enCurso'   => 'operar_viaje',
        'viaje.finalizar' => 'operar_viaje',
        'viaje.cancelar'  => 'cancelar_viaje_admin',

        // Anuncios
        'anuncio.guardar'           => 'gestionar_anuncios',
        'anuncio.alternarEstado'    => 'gestionar_anuncios',
        'anuncio.alternarDestacado' => 'gestionar_anuncios',
        'anuncio.eliminar'          => 'gestionar_anuncios',
        'anuncio.listar'            => 'gestionar_anuncios',

        // Notificaciones
        'notificacion.difundir' => 'gestionar_comunicados',

        // Reportes y quejas de pasajeros.
        //
        // OJO: aquí NO hay «un permiso para el módulo». Cada acción tiene la
        // suya porque el flujo es bidireccional:
        //     el PASAJERO crea  ·  el ADMIN revisa, actualiza y elimina
        // Antiguamente `Auth::requerirAdmin()` estaba al principio del `case`
        // y el pasajero NUNCA podía llegar a `reporte.crear`: la acción existía
        // pero era inalcanzable. Ahora cada rama exige lo suyo.
        'reporte.crear'      => 'crear_reporte',
        'reporte.misReportes'=> 'hacer_reserva',   // el pasajero solo ve los suyos
        'reporte.consultar'  => 'gestionar_reportes_pasajeros',
        'reporte.actualizar' => 'gestionar_reportes_pasajeros',
        'reporte.eliminar'   => 'gestionar_reportes_pasajeros',

        // Reservas
        'reserva.crear'      => 'hacer_reserva',
        'reserva.cobrar'     => 'gestionar_asignaciones',
        'reserva.cancelar'   => 'cancelar_reserva',
        'reserva.ocasional'  => 'gestionar_asignaciones',
        'reserva.avisarPerdidos' => 'gestionar_asignaciones',
        'reserva.porViaje'   => 'gestionar_asignaciones',
    ];

    /* ------------------------------------------------------------------ */
    /* Control de PROPIEDAD sobre un viaje                                 */
    /* ------------------------------------------------------------------ */

    /**
     * ¿El usuario de la sesión puede operar este viaje?
     *
     * El permiso dice QUÉ PUEDES HACER; esto dice SOBRE QUÉ. Un conductor con
     * permiso para finalizar viajes solo puede finalizar los SUYOS: sin esta
     * comprobación, `api/index.php?modulo=viaje&accion=finalizar&id=…` le
     * permitía cerrar el viaje de otro conductor con solo conocer el id.
     *
     * @return array{0:bool, 1:string}  [permitido, mensaje de error]
     */
    public static function puedeOperarViaje(int $idViaje): array
    {
        if ($idViaje <= 0) {
            return [false, 'El viaje indicado no es válido.'];
        }

        if (self::rol() === Config::ROL_ADMIN) {
            return [true, ''];
        }

        if (!class_exists('ViajeService')) {
            return [false, 'No se puede verificar el propietario del viaje.'];
        }

        if (ViajeService::esConductorDelViaje($idViaje)) {
            return [true, ''];
        }

        return [false, 'Solo puedes operar sobre los viajes que tú conduces.'];
    }

    /**
     * Exige permiso + propiedad sobre un viaje concreto.
     * Corta con 403 si algo falla, así que devuelve void.
     */
    public static function exigirViaje(string $accion, int $idViaje): void
    {
        self::requerirSesion();

        $permiso = self::PERMISOS_ACCION['viaje.' . $accion] ?? 'gestionar_viajes';

        // El conductor puede cambiar el estado de SUS viajes sin permiso
        // administrativo de «gestionar_viajes» (que implica ver la flota
        // completa), siempre que tenga el permiso de la acción.
        if (!self::tieneAcceso($permiso)) {
            self::denegar($accion);
        }

        [$ok, $motivo] = self::puedeOperarViaje($idViaje);
        if (!$ok) {
            self::denegar($accion);
        }
    }

    /**
     * Permisos que NUNCA pueden quedarse sin permiso «crítico».
     *
     * Sin esta lista, un administrador podía guardarse a sí mismo la lista
     * completa de permisos y quedarse fuera del sistema sin poder recuperar el
     * acceso. La comprobación es en BACKEND: el frontend puede esconder el
     * botón, pero la barrera real es esta.
     */
    public const PERMISOS_CRITICOS = [
        'gestionar_usuarios',
        'gestionar_permisos',
        'gestionar_viajes',
        'gestionar_vehiculos',
        'gestionar_asignaciones',
        'acceder_admin',
    ];

    /* ------------------------------------------------------------------ */
    /* Freno a la fuerza bruta                                             */
    /* ------------------------------------------------------------------ */

    /** Clave de la tabla `sget_login_intentos`: documento + IP, sin datos crudos. */
    private static function claveIntento(string $documento): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
        return hash('sha256', mb_strtolower(trim($documento)) . '|' . $ip);
    }

    private static function tablaIntentos(): bool
    {
        static $existe = null;
        if ($existe !== null) {
            return $existe;
        }
        try {
            $existe = (int) Database::scalar(
                'SELECT COUNT(*) FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = ?',
                ['sget_login_intentos']
            ) > 0;
        } catch (Throwable $e) {
            $existe = false;
        }
        return $existe;
    }

    /** Registra un intento de inicio de sesión fallido. */
    public static function registrarIntentoFallido(string $documento): void
    {
        $clave = self::claveIntento($documento);
        $ahora = time();

        if (self::tablaIntentos()) {
            try {
                Database::query(
                    'INSERT INTO sget_login_intentos (clave, documento, ip, intentos, ventana_inicio, ultimo_intento)
                     VALUES (?, ?, ?, 1, FROM_UNIXTIME(?), FROM_UNIXTIME(?))
                     ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = VALUES(ultimo_intento)',
                    [$clave, mb_substr(trim($documento), 0, 20), (string)($_SERVER['REMOTE_ADDR'] ?? ''), $ahora, $ahora]
                );
                return;
            } catch (Throwable $e) {
                error_log('[SGET][login] No se pudo registrar el intento: ' . $e->getMessage());
            }
        }

        // Respaldo en sesión (por si la tabla aún no existe): cubre el caso
        // del atacante que cambia de IP, no el de borrar cookies — para eso
        // está la tabla.
        $k = self::claveIntento($documento);
        $_SESSION['sget_intentos'][$k] = (int)($_SESSION['sget_intentos'][$k] ?? 0) + 1;
    }

    /**
     * ¿La cuenta está bloqueada temporalmente por intentos fallidos?
     * Bloqueo progresivo: 5 fallos -> 5 minutos, 10 o más -> 15 minutos.
     *
     * @return int segundos que faltan para volver a intentar (0 = libre)
     */
    public static function intentosBloqueados(string $documento): int
    {
        $clave = self::claveIntento($documento);

        $sesion = (int)($_SESSION['sget_intentos'][$clave] ?? 0);

        if (!self::tablaIntentos()) {
            if ($sesion < Config::LOGIN_MAX_INTENTOS) return 0;
            return $sesion >= Config::LOGIN_MAX_INTENTOS_ALTO
                ? Config::LOGIN_BLOQUEO_ALTO_SEG
                : Config::LOGIN_BLOQUEO_SEGUNDOS;
        }

        try {
            $fila = Database::one(
                'SELECT intentos, UNIX_TIMESTAMP(ventana_inicio) AS inicio
                   FROM sget_login_intentos WHERE clave = ?',
                [$clave]
            );
        } catch (Throwable $e) {
            return $sesion >= Config::LOGIN_MAX_INTENTOS ? Config::LOGIN_BLOQUEO_SEGUNDOS : 0;
        }

        if (!$fila) {
            return 0;
        }

        $intentos = (int)$fila['intentos'];
        $inicio   = (int)$fila['inicio'];

        // Ventana deslizante: si pasó mucho tiempo sin fallos, se reinicia.
        if ((time() - $inicio) > Config::LOGIN_VENTANA_SEGUNDOS) {
            self::limpiarIntentos($documento);
            return 0;
        }

        if ($intentos < Config::LOGIN_MAX_INTENTOS) {
            return 0;
        }

        return $intentos >= Config::LOGIN_MAX_INTENTOS_ALTO
            ? Config::LOGIN_BLOQUEO_ALTO_SEG
            : Config::LOGIN_BLOQUEO_SEGUNDOS;
    }

    public static function limpiarIntentos(string $documento): void
    {
        $clave = self::claveIntento($documento);
        unset($_SESSION['sget_intentos'][$clave]);

        if (!self::tablaIntentos()) {
            return;
        }
        try {
            Database::query('DELETE FROM sget_login_intentos WHERE clave = ?', [$clave]);
        } catch (Throwable $e) {
            error_log('[SGET][login] No se pudieron limpiar los intentos: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Establecimiento de sesion (login normal y login con Google)          */
    /* ------------------------------------------------------------------ */

    /**
     * Deja la sesion lista tras una autenticacion correcta.
     *
     * La usan TANTO `validar.php` como `controllers/auth_google.php`, para que
     * el acceso con Google tenga exactamente los mismos roles, permisos y
     * controles de inactividad que el acceso con documento y contrasena.
     *
     * @param array $fila  fila de `usuario`
     */
    public static function establecerSesion(array $fila): void
    {
        // Regenera el ID de sesion: evita el secuestro de sesion (fixation) y
        // descarta cualquier valor previo que hubiera en la sesion del visitante.
        session_regenerate_id(true);

        $_SESSION['id_usu']         = (int) $fila['id_usu'];
        $_SESSION['documento']      = (string) $fila['num_doc_usu'];
        $_SESSION['nombre_usuario'] = (string) $fila['nom_usu'];
        $_SESSION['rol']            = (int) $fila['id_rol_usu'];
        $_SESSION['ultimo_acceso']  = time();

        // Se descarta cualquier estado heredado de la sesion anterior.
        unset($_SESSION['restricciones'], $_SESSION['sesion_bloqueada'],
              $_SESSION['inactividad_bloqueada_en'], $_SESSION['url_redirect']);
    }

    /** A dónde debe ir cada rol después de iniciar sesión. */
    public static function inicioPorRol(?int $rol = null): string
    {
        return match ($rol ?? self::rol()) {
            Config::ROL_ADMIN     => Config::basePath() . '/Admin/admin.php',
            Config::ROL_CONDUCTOR => Config::basePath() . '/Conductor/conductor.php',
            Config::ROL_PASAJERO  => Config::basePath() . '/Pasajero/pasajero.php',
            default               => Config::basePath() . '/index.php',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    public static function tieneAcceso(string $recurso): bool
    {
        if (!self::estaLogueado()) return false;

        // Cache por peticion: una misma pantalla consulta tieneAcceso() una
        // docena de veces (sidebar, buscador, botones de la cabecera) y antes
        // eso disparaba una consulta SQL por cada llamada.
        static $cache = [];
        $clave = self::id() . '|' . $recurso;
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $recurso = trim($recurso);
        if ($recurso === '') {
            return $cache[$clave] = false;
        }

        /* ---------------------------------------------------------------
           PRINCIPIO DE MINIMO PRIVILEGIO
           ---------------------------------------------------------------
           Antes, si no habia ninguna fila de permiso la funcion devolvia
           `true`: cualquier usuario sin registros entraba en TODO, y el
           boton oculto del frontend era la unica barrera real.

           Ahora la decision es en cascada y el valor por defecto es NO:
             1. Decision explicita del USUARIO (`usuario_permisos`), buscada
                por nombre de permiso o por modulo:
                  · alguna fila permitida -> CONCEDE
                  · solo filas denegadas  -> DENIEGA
             2. Sin decision del usuario: decide el ROL (`rol_permiso`).
             3. Si tampoco el rol lo tiene -> DENIEGA.

           El Administrador NO es un atajo hardcodeado: se concede por
           `rol_permiso` igual que cualquier otro, de modo que una denegacion
           explicita sobre su cuenta tambien le quita el acceso.
        ---------------------------------------------------------------- */

        $decisiones = Database::all(
            "SELECT up.permitido
               FROM usuario_permisos up
               INNER JOIN permisos p ON p.id_permiso = up.id_permiso
              WHERE up.id_usu = ?
                AND (p.nombre_permiso = ? OR p.modulo = ?)",
            [self::id(), $recurso, $recurso]
        );

        if ($decisiones !== []) {
            foreach ($decisiones as $d) {
                if ((int) $d['permitido'] === 1) {
                    return $cache[$clave] = true;
                }
            }
            return $cache[$clave] = false;   // denegado explicitamente
        }

        return $cache[$clave] = self::rolConcede($recurso);
    }

    /** ¿El rol indicado tiene este recurso concedido en `rol_permiso`? */
    public static function rolConcede(string $recurso, ?int $rol = null): bool
    {
        $rol = $rol ?? self::rol();
        if ($rol <= 0) return false;

        return (int) Database::scalar(
            "SELECT COUNT(*)
               FROM rol_permiso rp
               INNER JOIN permisos p ON p.id_permiso = rp.id_permiso
              WHERE rp.id_rol = ?
                AND (p.nombre_permiso = ? OR p.modulo = ?)",
            [$rol, $recurso, $recurso]
        ) > 0;
    }

    /** Modulos concedidos al usuario (interfaz de permisos y auditoria). */
    public static function recursosConcedidos(?int $rol = null, ?int $usuario = null): array

    {
        $rol = $rol ?? self::rol();
        $usuario = $usuario ?? self::id();
        if ($usuario <= 0) return [];

        $filas = Database::all(
            "SELECT DISTINCT p.modulo
               FROM permisos p
               LEFT JOIN rol_permiso rp        ON rp.id_permiso = p.id_permiso AND rp.id_rol = ?
               LEFT JOIN usuario_permisos up  ON up.id_permiso = p.id_permiso AND up.id_usu = ?
              WHERE rp.id_rol IS NOT NULL OR COALESCE(up.permitido, 0) = 1
              ORDER BY p.modulo ASC",
            [$rol, $usuario]
        );

        $modulos = [];
        foreach ($filas as $f) {
            if (!empty($f['modulo'])) $modulos[] = (string) $f['modulo'];
        }
        return array_values(array_unique($modulos));
    }

    /* ------------------------------------------------------------------ */
    /* Protección de los permisos CRÍTICOS                                 */
    /* ------------------------------------------------------------------ */

    /**
     * ¿Qué permisos críticos dejarían de ser accesibles si el usuario indicado
     * se quedara SOLO con `$idsPermiso` más lo que su rol concede?
     *
     * Devuelve la lista de NOMBRES de permiso que se perderían (vacía = seguro).
     *
     * Se usa en `api/guardar_permisos.php` para que un administrador no pueda
     * quitarse a sí mismo el acceso administrativo. La comprobación es
     * estrictamente de BACKEND: en el frontend el botón puede estar
     * deshabilitado, pero si alguien envía el POST a mano, aquí se corta.
     *
     * Nota sobre el criterio: se mira el resultado EFECTIVO (rol + override),
     * no la fila. Si el rol ya concede el permiso, quitar la fila de
     * `usuario_permisos` no le quita el acceso, y por tanto no es un auto-bloqueo.
     *
     * @param int   $idUsuario  usuario al que se le van a guardar los permisos
     * @param int[] $idsPermiso ids de `permisos` que quedarán seleccionados
     * @return string[]
     */
    public static function permisosCriticosInaccesibles(int $idUsuario, array $idsPermiso): array
    {
        if ($idUsuario <= 0) {
            return self::PERMISOS_CRITICOS;
        }

        $rol = (int) Database::scalar('SELECT id_rol_usu FROM usuario WHERE id_usu = ?', [$idUsuario]);
        if ($rol <= 0) {
            return [];
        }

        $idsPermiso = array_map('intval', $idsPermiso);

        $nombres = Database::all(
            'SELECT id_permiso, nombre_permiso
               FROM permisos
              WHERE nombre_permiso IN (' . implode(',', array_fill(0, count(self::PERMISOS_CRITICOS), '?')) . ')',
            self::PERMISOS_CRITICOS
        );

        $perdidos = [];
        foreach ($nombres as $fila) {
            $id = (int)$fila['id_permiso'];
            $nombre = (string)$fila['nombre_permiso'];

            // Concedido explícitamente a este usuario -> sigue teniendo acceso.
            if (in_array($id, $idsPermiso, true)) {
                continue;
            }
            // Concedido por su rol -> tampoco se pierde.
            if ((int) Database::scalar(
                'SELECT COUNT(*) FROM rol_permiso WHERE id_rol = ? AND id_permiso = ?',
                [$rol, $id]
            ) > 0) {
                continue;
            }

            $perdidos[] = $nombre;
        }

        return $perdidos;
    }

    /* ------------------------------------------------------------------ */
    /* Inactividad                                                         */
    /* ------------------------------------------------------------------ */

    public static function verificarInactividad(int $minutos = 0): void
    {
        if (self::$verificada) return;

        if ($minutos <= 0) {
            $minutos = Config::MINUTOS_INACTIVIDAD;
        }

        $limite  = $minutos * 60;
        $ultimo  = $_SESSION['ultimo_acceso'] ?? null;
        $vence   = $ultimo !== null && (time() - (int) $ultimo) > $limite;

        // Conserva el instante original del bloqueo para que recargar la
        // página no reinicie el temporizador de 60 s del modal.
        if ($vence && empty($_SESSION['inactividad_bloqueada_en'])) {
            $_SESSION['inactividad_bloqueada_en'] = time();
        }

        if ($vence) {
            $_SESSION['sesion_bloqueada'] = true;
            $_SESSION['url_redirect']    = $_SERVER['REQUEST_URI'] ?? '';

            // Una petición AJAX/JSON no puede mostrar un modal: se corta aquí.
            if (self::peticionAjax()) {
                self::bloquear();
            }
            // En navegación normal se continúa: el modal de inactividad que
            // incluye includes/header.php bloquea la pantalla y pide la
            // contraseña. Redirigir a un endpoint JSON sería inútil.
        } else {
            $_SESSION['ultimo_acceso'] = time();
            unset($_SESSION['sesion_bloqueada']);
        }

        self::$verificada = true;
    }

    public static function bloquear(): void
    {
        if (self::peticionAjax()) {
            self::json([
                'status'   => 'bloqueado',
                'mensaje'  => 'La sesion ha sido bloqueada por inactividad.',
                'redirect' => Config::basePath() . '/index.php',
            ], 401);
        }
        // Navegación normal: se marca y el modal hace el resto.
        $_SESSION['sesion_bloqueada'] = true;
    }

    /* ------------------------------------------------------------------ */
    /* Desbloqueo por inactividad                                           */
    /* ------------------------------------------------------------------ */

    /** ¿La sesión está bloqueada en este momento? */
    public static function estaBloqueada(): bool
    {
        return !empty($_SESSION['sesion_bloqueada']);
    }

    /**
     * Comprueba la contraseña del usuario en sesión y desbloquea.
     * Devuelve [bool $ok, string $mensaje].
     */
    public static function desbloquear(string $password): array
    {
        $password = trim($password);
        if ($password === '') {
            return [false, 'Por favor ingresa tu contraseña.'];
        }

        $id = self::id();
        if ($id <= 0) {
            self::cerrar();
            return [false, 'La sesión expiró. Inicia sesión nuevamente.'];
        }

        $hash = Database::scalar("SELECT pass_usu FROM usuario WHERE id_usu = ?", [$id]);
        if ($hash === null) {
            return [false, 'No se encontró la cuenta. Inicia sesión nuevamente.'];
        }

        // La comparacion vive UNICA en core/Password.php (bcrypt y, solo para
        // la migracion de cuentas heredadas, MD5 o texto plano). Aqui no se
        // repite ninguna variante.
        if (!Password::verify($password, (string) $hash)) {
            Logger::registrar(Database::pdo(), 'DESBLOQUEO_FALLIDO',
                "Intento fallido de desbloqueo por inactividad del usuario #{$id}.");
            return [false, 'Contraseña incorrecta. Inténtalo de nuevo.'];
        }

        if (Password::necesitaMigracion((string) $hash)) {
            Database::query("UPDATE usuario SET pass_usu = ? WHERE id_usu = ?",
                [Password::hash($password), $id]);
        }

        unset($_SESSION['sesion_bloqueada'], $_SESSION['inactividad_bloqueada_en'],
              $_SESSION['inactividad_temporizador_inicia_en'], $_SESSION['inactividad_cierra_en']);
        $_SESSION['ultimo_acceso'] = time();

        Logger::registrar(Database::pdo(), 'DESBLOQUEO', "Sesión desbloqueada por el usuario #{$id}.");

        return [true, 'Sesión desbloqueada correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Fin de sesion                                                       */
    /* ------------------------------------------------------------------ */

    public static function cerrar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
    /* ------------------------------------------------------------------ */
    /* Token anti-CSRF                                                      */
    /* ------------------------------------------------------------------ */
    public static function token(): string
    {
        if (empty($_SESSION['sget_csrf'])) {
            $_SESSION['sget_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['sget_csrf'];
    }

    /** Campo oculto listo para insertar en cualquier formulario. */
    public static function campoToken(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validarToken(?string $recibido): bool
    {
        return !empty($recibido)
            && !empty($_SESSION['sget_csrf'])
            && hash_equals($_SESSION['sget_csrf'], $recibido);
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    public static function peticionAjax(): bool
    {
        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        $json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        return $ajax || $json;
    }

    public static function json(array $data, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirigirLogin(): void
    {
        if (self::peticionAjax()) {
            self::json(['status' => 'error', 'mensaje' => 'Sesion no iniciada.'], 401);
        }
        $_SESSION['url_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . Config::basePath() . '/index.php');
        exit;
    }

    public static function denegar(?string $recurso = null): void
    {
        if (self::peticionAjax()) {
            self::json([
                'status'  => 'error',
                'mensaje' => $recurso
                    ? sprintf('Acceso restringido: no tienes permiso para %s.', str_replace('_', ' ', $recurso))
                    : 'Acceso restringido.',
            ], 403);
        }
        http_response_code(403);
        $modulo = $recurso
            ? htmlspecialchars(str_replace('_', ' ', $recurso), ENT_QUOTES, 'UTF-8')
            : 'este módulo';
        echo "<!DOCTYPE html><meta charset='utf-8'>"
           . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
           . "<div style=\"font-family:system-ui,sans-serif;text-align:center;margin-top:10vh;background:#0f172a;color:#fff;"
           . "padding:40px 24px;border-radius:20px;max-width:480px;margin-left:auto;margin-right:auto;"
           . "border:1px solid rgba(255,255,255,.1);box-shadow:0 20px 40px rgba(0,0,0,.45)\">"
           . "<h2 style='color:#ef4444;margin-bottom:12px;font-size:22px'>&#128683; Acceso Restringido</h2>"
           . "<p style='color:#94a3b8;font-size:14px;line-height:1.6'>No tienes permisos para gestionar {$modulo}.</p>"
           . "<a href='javascript:history.back()' style='display:inline-block;margin-top:24px;padding:10px 24px;background:#3b82f6;"
           . "color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px'>Regresar</a></div>";
        exit;
    }
}
