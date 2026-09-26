<?php
/**
 * DESC: Restaurar la columna `usuario.restricciones` que usa el módulo de permisos
 *
 * CONTEXTO
 *   La migración 003 eliminó `usuario.restricciones` por estar aparentemente
 *   sin uso. Se comprobó después que SÍ la usan:
 *     · Admin/guardar_permisos.php
 *     · Admin/procesar_actualizacion.php
 *   (el módulo de permisos guarda ahí la lista de módulos restringidos por
 *   usuario, separada del sistema `usuario_permisos`).
 *
 *   Eliminarla rompía la gestión de permisos, así que se recupera. En una
 *   instalación donde realmente no se use, puede eliminarse de nuevo cuando
 *   ese módulo se migre al esquema `usuario_permisos`.
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    try {
        Database::query("ALTER TABLE usuario ADD COLUMN IF NOT EXISTS restricciones TEXT NULL DEFAULT NULL");
        $log('[OK] usuario.restricencias restaurada (TEXT NULL).');
    } catch (Throwable $e) {
        if (in_array($e->errorInfo[1] ?? 0, [1060, 1091], true)) {
            $log('[--] La columna ya existe.');
        } else {
            throw $e;
        }
    }
};
