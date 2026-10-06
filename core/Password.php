<?php
/**
 * core/Password.php
 * -----------------------------------------------------------------------------
 * ÚNICO lugar del sistema donde se comprueban contraseñas.
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   Antes cada página repetía su propio `if (password_verify(...) || md5(...) ||
 *   $plain === $hash)`, con tres formatos conviviendo en la base de datos:
 *   bcrypt (password_hash), MD5 heredado y texto plano. Cada copia era una
 *   oportunidad de dejar una comparación laxa o de saltarse la migración.
 *
 *   Aquí vive la regla única:
 *     · lo NUEVO se guarda SIEMPRE con password_hash(..., PASSWORD_DEFAULT);
 *     · lo VIEJO (MD5 o texto plano) se acepta SOLO para poder recuperar el
 *       acceso legítimo y se re-hashea en el acto, en la misma transacción que
 *       el login (ver `necesitaMigracion()`).
 *     · NINGÚN camino de escritura guarda contraseñas en claro: todas las
 *       altas y cambios de contraseña pasan por `hash()`.
 *
 * La comparación en tiempo constante la hace password_verify() (bcrypt ya es
 * constante); para los formatos heredados se usa hash_equals() para no filtrar
 * información por temporización.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class Password
{
    /** Longitud mínima exigida al CREAR una contraseña. */
    public const MIN = 8;

    /** Longitud máxima (evita DoS con bcrypt: se trunca a 72 bytes). */
    public const MAX = 72;

    /* ------------------------------------------------------------------ */

    /** Genera el hash que se guarda en `usuario.pass_usu`. */
    public static function hash(string $plain): string
    {
        return password_hash(self::normalizar($plain), PASSWORD_DEFAULT);
    }

    /**
     * Comprueba una contraseña contra lo que hay en la base de datos.
     *
     * Acepta los tres formatos históricos SOLO para no perder el acceso de las
     * cuentas ya creadas; el llamador debe usar `necesitaMigracion()` para
     * rehashear en el momento.
     */
    public static function verify(string $plain, ?string $guardado): bool
    {
        $plain = self::normalizar($plain);
        $guardado = (string) $guardado;

        if ($guardado === '' || $plain === '') {
            return false;
        }

        // 1) Formato moderno (bcrypt / argon2).
        if (self::esModerno($guardado)) {
            return password_verify($plain, $guardado);
        }

        // 2) MD5 heredado.
        if (preg_match('/^[a-f0-9]{32}$/i', $guardado) === 1) {
            return hash_equals(strtolower($guardado), md5($plain));
        }

        // 3) Texano plano (solo migración; nunca se vuelve a guardar así).
        return hash_equals($guardado, $plain);
    }

    /** ¿El hash guardado usa el mecanismo moderno? */
    public static function esModerno(string $guardado): bool
    {
        return (password_get_info($guardado)['algo'] ?? null) !== null;
    }

    /**
     * ¿Este hash se debe reescribir al iniciar sesión?
     * Devuelve true tanto si es legacy (MD5/plano) como si el algoritmo por
     * defecto de PHP ha cambiado (p. ej. de bcrypt a argon2).
     */
    public static function necesitaMigracion(string $guardado): bool
    {
        if (!self::esModerno($guardado)) {
            return true;
        }
        return password_needs_rehash($guardado, PASSWORD_DEFAULT);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Valida la ROBUSTEZ de una contraseña nueva.
     * @return string|null  mensaje de error, o null si es válida
     */
    public static function validar(string $plain, string $etiqueta = 'La contraseña'): ?string
    {
        $n = mb_strlen($plain);

        if ($n < self::MIN) {
            return "{$etiqueta} debe tener al menos " . self::MIN . " caracteres.";
        }
        if (mb_strlen($plain) > self::MAX) {
            return "{$etiqueta} no puede superar los " . self::MAX . " caracteres.";
        }
        if (preg_match('/\s/', $plain) === 1 && trim($plain) === $plain) {
            // Espacio interior permitido (frases), pero ni delante ni detrás.
            return null;
        }
        if (trim($plain) === '') {
            return "{$etiqueta} no puede estar vacía.";
        }
        if (preg_match('/^(123456|admin|administrador|password|clave123|sget123)$/i', $plain) === 1) {
            return "Esa {$etiqueta} es demasiado común. Elige una que no sea evidente.";
        }
        return null;
    }

    /**
     * Contraseña aleatoria e imposible de adivinar.
     * Se usa para las cuentas que NACEN sin contraseña utilizable (alta con
     * Google, pasajero ocasional): la cuenta queda bloqueada para acceso por
     * documento hasta que el usuario se registre con un método real.
     */
    public static function aleatoria(): string
    {
        return bin2hex(random_bytes(24));
    }

    private static function normalizar(string $plain): string
    {
        return trim($plain);
    }
}