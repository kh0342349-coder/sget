<?php
/**
 * core/Fecha.php
 * -----------------------------------------------------------------------------
 * CORRECCION DEL ERROR "Data Default Fallback" (Formato/Inconsistencia de datos)
 * -----------------------------------------------------------------------------
 * El sistema almacena:
 *   - viaje.fec_via     -> DATE  (solo Y-m-d)
 *   - viaje.hor_sal_via -> TIME  (solo H:i:s)
 *
 * Antes ambos eran DATETIME y se enviaban desde <input type="date"> (2026-09-26)
 * y <input type="time"> (06:00). MySQL rellenaba los huecos por defecto y
 * terminaba guardando cosas como:
 *   fec_via     = 2026-09-26 00:00:00
 *   hor_sal_via = 0000-00-00 00:00:00   <-- fecha CERO (invalida)
 * lo que rompia TIMESTAMP(fec_via, hor_sal_via) y toda consulta de salida.
 *
 * Esta clase centraliza TODA conversion de fecha/hora. Nunca se deben usar
 * date()/strtotime() sueltos sobre datos de la BD: siempre pasar por aqui.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class Fecha
{
    public const FMT_FECHA = 'Y-m-d';
    public const FMT_HORA  = 'H:i:s';
    public const FMT_MYSQL = 'Y-m-d H:i:s';

    /* ------------------------------------------------------------------ */
    /* Validaciones de "datos por defecto"                                  */
    /* ------------------------------------------------------------------ */

    /** Detecta la fecha cero de MySQL ("0000-00-00 00:00:00") o valores vacios. */
    public static function esVacia($valor): bool
    {
        if ($valor === null) return true;
        $v = trim((string) $valor);
        if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return true;
        // MySQL devuelve "0000-00-00 00:00:00" en algunos drivers
        if (str_starts_with($v, '0000-00-00')) return true;
        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Normalizadores (entrada de formularios -> formato de BD)            */
    /* ------------------------------------------------------------------ */

    /**
     * Normaliza una fecha a 'Y-m-d'. Lanza ValueError si el dato es invalido
     * o si es una fecha cero: NO se permite el fallback silencioso.
     */
    public static function fecha(?string $entrada, string $campo = 'fecha'): string
    {
        $entrada = trim((string) $entrada);

        if ($entrada === '' || self::esVacia($entrada)) {
            throw new ValueError("El campo {$campo} es obligatorio.");
        }

        // Acepta '2026-09-26' o '2026-09-26 14:30:00' (tomo solo la parte de fecha)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $entrada, $m)) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                throw new ValueError("La {$campo} no es una fecha valida: {$entrada}");
            }
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        $ts = strtotime($entrada);
        if ($ts === false) {
            throw new ValueError("La {$campo} no es una fecha valida: {$entrada}");
        }
        return date(self::FMT_FECHA, $ts);
    }

    /**
     * Normaliza una hora a 'H:i:s'. Acepta '06', '06:30', '06:30:00'.
     * Devuelve null si el campo es opcional y viene vacio.
     */
    public static function hora(?string $entrada, string $campo = 'hora', bool $obligatoria = false): ?string
    {
        $entrada = trim((string) $entrada);

        if ($entrada === '' || self::esVacia($entrada)) {
            if ($obligatoria) {
                throw new ValueError("El campo {$campo} es obligatorio.");
            }
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $entrada, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = (int) ($m[3] ?? 0);
            if ($h > 23 || $i > 59 || $s > 59) {
                throw new ValueError("La {$campo} no es una hora valida: {$entrada}");
            }
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        throw new ValueError("La {$campo} no es una hora valida: {$entrada}");
    }

    /**
     * Convierte fecha + hora en un DATETIME completo y valido.
     * ESTE es el metodo que debe usarse para ordenar/salidas de viajes.
     */
    public static function combine(string $fecha, ?string $hora, string $defecto = '00:00:00'): string
    {
        $fecha = self::fecha($fecha);
        $hora  = self::hora($hora) ?? $defecto;
        return $fecha . ' ' . $hora;
    }

    /* ------------------------------------------------------------------ */
    /* Formateadores (BD -> interfaz)                                       */
    /* ------------------------------------------------------------------ */

    public static function soloFecha($valor): string
    {
        return self::esVacia($valor) ? '' : substr((string) $valor, 0, 10);
    }

    public static function soloHora($valor): string
    {
        if (self::esVacia($valor)) return '';
        $v = (string) $valor;
        return strlen($v) > 11 ? substr($v, 11, 5) : substr($v, 0, 5);
    }

    public static function legible($valor, bool $conHora = true): string
    {
        if (self::esVacia($valor)) return 'Sin definir';
        $ts = strtotime((string) $valor);
        if ($ts === false) return (string) $valor;
        return date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $ts);
    }

    public static function ahora(): string
    {
        return date(self::FMT_MYSQL);
    }

    /* ------------------------------------------------------------------ */
    /* Reglas de negocio                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Devuelve el instante real de salida de un viaje (fecha + hora unidas).
     * Rutas tables ya se guardan separadas; aqui se unen sin fechas cero.
     */
    public static function instanteSalida(?string $fecVia, ?string $horSal): ?string
    {
        if (self::esVacia($fecVia)) return null;
        $fecha = self::soloFecha($fecVia);
        $hora  = self::soloHora($horSal);
        if ($hora === '') $hora = '00:00:00';
        return $fecha . ' ' . $hora;
    }

    /**
     * Un viaje "ya salio" si su instante de salida ya paso.
     * Devuelve [bool $yaSalio, string $instanteISO].
     */
    public static function yaSalio(?string $fecVia, ?string $horSal, int $toleranciaMin = 0): array
    {
        $instante = self::instanteSalida($fecVia, $horSal);
        if ($instante === null) {
            return [false, ''];
        }
        $limite = time() - ($toleranciaMin * 60);
        return [strtotime($instante) <= $limite, $instante];
    }
}
