<?php
/**
 * core/Flash.php  +  core/Validator.php
 * -----------------------------------------------------------------------------
 * Flash: mensajes de una sola lectura entre dos peticiones (PRG pattern).
 * Validator: reglas declarativas para formularios (fin de los isset($_POST['x'])
 *           dispersos que producian datos vacios o formatos invalidos).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class Flash
{
    public const TIPOS = ['exito', 'error', 'aviso', 'info'];

    public static function set(string $tipo, string $mensaje): void
    {
        if (!in_array($tipo, self::TIPOS, true)) $tipo = 'info';
        $_SESSION['sget_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    public static function exito(string $m): void { self::set('exito', $m); }
    public static function error(string $m): void { self::set('error', $m); }
    public static function aviso(string $m): void { self::set('aviso', $m); }
    public static function info(string $m): void  { self::set('info', $m); }

    /** Devuelve y limpia los mensajes pendientes. */
    public static function tomar(): array
    {
        $msgs = $_SESSION['sget_flash'] ?? [];
        unset($_SESSION['sget_flash']);
        return $msgs;
    }

    public static function hay(): bool
    {
        return !empty($_SESSION['sget_flash']);
    }

    /** Renderiza los mensajes como HTML. */
    public static function render(): string
    {
        $html = '';
        foreach (self::tomar() as $m) {
            $estilo = self::estilo($m['tipo']);
            $icono  = self::icono($m['tipo']);
            $html .= "<div class=\"sget-flash sget-flash--{$m['tipo']}\" role=\"alert\">"
                   . "<i class=\"fas {$icono}\"></i><span>{$m['mensaje']}</span></div>";
        }
        return $html;
    }

    private static function estilo(string $tipo): string
    {
        return [
            'exito' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            'error' => 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400',
            'aviso' => 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400',
            'info'  => 'border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400',
        ][$tipo] ?? 'border-slate-300 bg-slate-100 text-slate-700';
    }

    private static function icono(string $tipo): string
    {
        return [
            'exito' => 'fa-check-circle',
            'error' => 'fa-triangle-exclamation',
            'aviso' => 'fa-circle-exclamation',
            'info'  => 'fa-circle-info',
        ][$tipo] ?? 'fa-circle-info';
    }
}

/* ========================================================================== */

final class Validator
{
    private array $datos;
    private array $errores = [];

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public static function de(array $datos): self { return new self($datos); }

    /* ------------------------------------------------------------------ */

    public function requerido(string $campo, string $etiqueta): self
    {
        $v = $this->datos[$campo] ?? null;
        if ($v === null || trim((string) $v) === '') {
            $this->errores[$campo] = "El campo {$etiqueta} es obligatorio.";
        }
        return $this;
    }

    public function texto(string $campo, string $etiqueta, int $min = 2, int $max = 255): self
    {
        $v = trim((string) ($this->datos[$campo] ?? ''));
        $len = mb_strlen($v);
        if ($len > 0 && ($len < $min || $len > $max)) {
            $this->errores[$campo] = "{$etiqueta} debe tener entre {$min} y {$max} caracteres.";
        }
        return $this;
    }

    public function entero(string $campo, string $etiqueta, ?int $min = null, ?int $max = null): self
    {
        $v = $this->datos[$campo] ?? '';
        if ($v === '' || $v === null) return $this;
        if (!is_numeric($v) || (string) (int) $v !== (string) $v) {
            $this->errores[$campo] = "{$etiqueta} debe ser un número entero.";
            return $this;
        }
        $n = (int) $v;
        if ($min !== null && $n < $min) $this->errores[$campo] = "{$etiqueta} no puede ser menor a {$min}.";
        if ($max !== null && $n > $max) $this->errores[$campo] = "{$etiqueta} no puede ser mayor a {$max}.";
        return $this;
    }

    public function decimal(string $campo, string $etiqueta, ?float $min = null, ?float $max = null): self
    {
        $v = $this->datos[$campo] ?? '';
        if ($v === '' || $v === null) return $this;
        if (!is_numeric($v)) {
            $this->errores[$campo] = "{$etiqueta} debe ser un valor numérico.";
            return $this;
        }
        $n = (float) $v;
        if ($min !== null && $n < $min) $this->errores[$campo] = "{$etiqueta} no puede ser menor a {$min}.";
        if ($max !== null && $n > $max) $this->errores[$campo] = "{$etiqueta} no puede ser mayor a {$max}.";
        return $this;
    }

    public function email(string $campo, string $etiqueta): self
    {
        $v = trim((string) ($this->datos[$campo] ?? ''));
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errores[$campo] = "{$etiqueta} no tiene un formato de correo válido.";
        }
        return $this;
    }

    public function fecha(string $campo, string $etiqueta, bool $requerida = true): self
    {
        $v = trim((string) ($this->datos[$campo] ?? ''));
        if ($v === '') {
            if ($requerida) $this->errores[$campo] = "El campo {$etiqueta} es obligatorio.";
            return $this;
        }
        try {
            Fecha::fecha($v, $etiqueta);
        } catch (ValueError $e) {
            $this->errores[$campo] = $e->getMessage();
        }
        return $this;
    }

    public function hora(string $campo, string $etiqueta, bool $requerida = true): self
    {
        $v = trim((string) ($this->datos[$campo] ?? ''));
        if ($v === '') {
            if ($requerida) $this->errores[$campo] = "El campo {$etiqueta} es obligatorio.";
            return $this;
        }
        try {
            Fecha::hora($v, $etiqueta, true);
        } catch (ValueError $e) {
            $this->errores[$campo] = $e->getMessage();
        }
        return $this;
    }

    public function enLista(string $campo, string $etiqueta, array $permitidos): self
    {
        $v = (string) ($this->datos[$campo] ?? '');
        if ($v !== '' && !in_array($v, array_map('strval', $permitidos), true)) {
            $this->errores[$campo] = "El valor de {$etiqueta} no es válido.";
        }
        return $this;
    }

    /** Regla de negocio: la anotacion de cancelacion es obligatoria. */
    public function anotacionObligatoria(string $campo, string $etiqueta, int $minimo): self
    {
        $v = trim((string) ($this->datos[$campo] ?? ''));
        if ($v === '') {
            $this->errores[$campo] = "Debes indicar el motivo ({$etiqueta}). Es un campo obligatorio.";
        } elseif (mb_strlen($v) < $minimo) {
            $this->errores[$campo] = "La {$etiqueta} debe tener al menos {$minimo} caracteres para explicar la cancelación.";
        }
        return $this;
    }

    public function distinto(string $campo, string $otroCampo, string $mensaje): self
    {
        if (($this->datos[$campo] ?? '') !== ($this->datos[$otroCampo] ?? '')) {
            $this->errores[$campo] = $mensaje;
        }
        return $this;
    }

    /* ------------------------------------------------------------------ */

    public function agrega(string $campo, string $mensaje): self
    {
        $this->errores[$campo] = $mensaje;
        return $this;
    }

    /** Agrega el error solo si la condicion es verdadera. */
    public function agregaSi(bool $condicion, string $campo, string $mensaje): self
    {
        if ($condicion) $this->errores[$campo] = $mensaje;
        return $this;
    }

    public function falla(): bool { return !empty($this->errores); }

    public function errores(): array { return $this->errores; }

    public function primerError(): ?string
    {
        return $this->errores === [] ? null : reset($this->errores);
    }
}
