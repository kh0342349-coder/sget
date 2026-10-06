<?php
/**
 * core/Database.php
 * -----------------------------------------------------------------------------
 * Conexion PDO unica (singleton) con ERRORS DUPLICADOS en modo exception.
 * Se mantiene un alias mysqli ($conexion) por compatibilidad con el codigo
 * legado, pero toda la logica nueva debe usar Database::pdo().
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            Config::DB_HOST,
            Config::dbName(),
            Config::DB_CHARSET
        );

        try {
            self::$pdo = new PDO($dsn, Config::DB_USER, Config::DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // Regla anti-"Data Default Fallback": el motor RECHAZa fechas cero
            // y datos fuera de rango en vez de inventar un valor por defecto.
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            self::$pdo->exec("SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            self::fallarConexion($e->getMessage());
        }

        return self::$pdo;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers de consulta                                                 */
    /* ------------------------------------------------------------------ */

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Devuelve todas las filas. */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Devuelve la primera fila o null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $fila = self::query($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    /** Devuelve el primer valor escalar o null. */
    public static function scalar(string $sql, array $params = [])
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function begin(): void    { self::pdo()->beginTransaction(); }
    public static function commit(): void   { self::pdo()->commit(); }
    public static function rollback(): void { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }

    public static function enTransaccion(): bool
    {
        return self::pdo()->inTransaction();
    }

    /* ------------------------------------------------------------------ */
    /* Escape para el legado mysqli (nunca usar para construir SQL)         */
    /* ------------------------------------------------------------------ */
    public static function escapar(mysqli $c, $valor): string
    {
        return mysqli_real_escape_string($c, (string) $valor);
    }

    /* ------------------------------------------------------------------ */
    /**
     * Código de error REAL de MySQL de una excepción de PDO.
     *
     * POR QUÉ HACE FALTA
     *   Cuando una sentencia DDL falla, PDO devuelve `errorInfo[1] = 1005`
     *   (ER_CANT_CREATE_TABLE), que es un error genérico que no dice nada. La
     *   causa concreta —por ejemplo 121 «Duplicate key on write or update»— va
     *   escondida DENTRO del mensaje. Las migraciones comparan códigos para
     *   decidir si un «ya existía» es normal, y con 1005 no hay manera de
     *   distinguirlo de un fallo de verdad: por eso una migración que se
     *   ejecutaba bien sobre la base de desarrollo reventaba al aplicarse a una
     *   base recién importada de un volcado que ya traía la restricción.
     *
     * @return int  el número de MySQL, o 0 si no se puede averiguar
     */
    public static function errorCode(Throwable $e): int
    {
        $info = $e instanceof PDOException ? $e->errorInfo : null;
        if (is_array($info) && isset($info[1])) {
            $directo = (int)$info[1];
            // 1005/1054 y compañía son envoltorios: el número útil va en el texto.
            if ($directo !== 1005) return $directo;
        }

        if (preg_match('/errno:\s*(\d+)/', $e->getMessage(), $m) === 1) {
            return (int)$m[1];
        }

        return $info && isset($info[1]) ? (int)$info[1] : 0;
    }

    /** ¿Este error es uno de los que la migración debe ignorar? */
    public static function errorEs(Throwable $e, array $codigos): bool
    {
        return in_array(self::errorCode($e), $codigos, true);
    }

    /* ------------------------------------------------------------------ */
    private static function fallarConexion(string $mensaje): void
    {
        error_log('[SGET][DB] ' . $mensaje);
        if (PHP_SAPI === 'cli') {
            exit('Error de conexion: ' . $mensaje);
        }
        http_response_code(500);
        echo '<!DOCTYPE html><meta charset="utf-8">'
           . '<div style="font-family:sans-serif;text-align:center;margin-top:80px;background:#0f172a;color:#fff;'
           . 'padding:40px;border-radius:16px;max-width:520px;margin-left:auto;margin-right:auto;'
           . 'border:1px solid rgba(255,255,255,.1)">'
           . '<h2 style="color:#ef4444;margin-bottom:12px">Sin conexion con la base de datos</h2>'
           . '<p style="color:#94a3b8;font-size:14px;line-height:1.6">El sistema no puede operar sin conexión a MySQL. '
           . 'Verifica que Apache y MySQL estén iniciados en XAMPP y que las credenciales de '
           . '<code>core/Config.php</code> sean correctas.</p></div>';
        exit;
    }
}
