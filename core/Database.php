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
