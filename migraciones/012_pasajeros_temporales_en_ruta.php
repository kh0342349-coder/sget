<?php
/**
 * migraciones/012_pasajeros_temporales_en_ruta.php
 * DESC: Añade trazabilidad de pasajeros temporales registrados durante un viaje
 *
 * Las reservas existentes mantienen es_temporal = 0. Los datos de origen,
 * destino y usuario que registró el pasajero solo se escriben para altas en ruta.
 */
declare(strict_types=1);

$migrar = static function (): void {
    $existeColumna = static function (string $columna): bool {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['reserva', $columna]
        ) > 0;
    };

    $columnas = [
        'es_temporal' => "ALTER TABLE reserva ADD COLUMN es_temporal TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Reserva creada por el conductor durante el viaje'",
        'punto_abordaje' => 'ALTER TABLE reserva ADD COLUMN punto_abordaje VARCHAR(120) DEFAULT NULL',
        'destino_abordaje' => 'ALTER TABLE reserva ADD COLUMN destino_abordaje VARCHAR(120) DEFAULT NULL',
        'registrada_por' => 'ALTER TABLE reserva ADD COLUMN registrada_por INT(11) DEFAULT NULL',
    ];

    foreach ($columnas as $nombre => $sql) {
        if (!$existeColumna($nombre)) {
            Database::query($sql);
            echo "   Columna reserva.{$nombre} anadida.\n";
        }
    }

    $indice = (int) Database::scalar(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
        ['reserva', 'ix_reserva_temporal_viaje']
    );
    if ($indice === 0) {
        Database::query('CREATE INDEX ix_reserva_temporal_viaje ON reserva (id_via_res, es_temporal)');
        echo "   Indice ix_reserva_temporal_viaje creado.\n";
    }
};
