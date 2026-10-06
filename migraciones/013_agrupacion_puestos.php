<?php
/**
 * migraciones/013_agrupacion_puestos.php
 * DESC: Añade cantidad_puestos y asientos_asignados a reserva para agrupar puestos
 */
declare(strict_types=1);

$migrar = static function (): void {
    $existe = static function (string $col): bool {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['reserva', $col]
        ) > 0;
    };

    if (!$existe('cantidad_puestos')) {
        Database::query('ALTER TABLE reserva ADD COLUMN cantidad_puestos INT(11) NOT NULL DEFAULT 1 COMMENT "Total de puestos reservados en este registro agrupado"');
        echo "   cantidad_puestos añadida.\n";
    }
    if (!$existe('asientos_asignados')) {
        Database::query('ALTER TABLE reserva ADD COLUMN asientos_asignados VARCHAR(255) DEFAULT NULL COMMENT "Lista de asientos asignados (ej. 1,2,3)"');
        echo "   asientos_asignados añadida.\n";
    }
};
