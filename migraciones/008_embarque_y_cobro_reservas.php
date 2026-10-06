<?php
/**
 * DESC: Embarque real, no-presentación y valor pactado de las reservas
 *
 * CONTEXTO — LOS TRES AGUJEROS QUE ESTA MIGRACIÓN TAPA
 *
 *   1) COBRO QUE GUARDABA 0
 *      `ReservaService::crear()` guardaba `valor_pagado = NULL` cuando el
 *      pasajero reservaba online con «Efectivo al Abordar». El comprobante
 *      calculaba «Total a cancelar» en pantalla, pero ese número NUNCA se
 *      guardaba. En el recaudo, el botón de cobrar mandaba
 *      `data-valor="0"`, y `confirmarPago()` caía a `(float)NULL` = 0: el pago
 *      quedaba registrado como $0 y los informes de ganancias no sumaban nada.
 *      Con esta migración se guarda el VALOR PACTADO al crear la reserva (que
 *      es lo que el pasajero debe), y el cobro confirma ese importe o el que
 *      el administrador escriba. Los ingresos siguen sumando solo las
 *      reservas Confirmadas, así que un valor pactado no infla la caja.
 *
 *   2) NADIE SABÍA QUIÉN SUBIÓ AL BUS
 *      No habia forma de distinguir "pago y viajaron" de "reservo y
 *      no se presentó». Se añade `embarco`, ortogonal al estado de pago: da
 *      igual si se pagó antes o al abordar.
 *
 *   3) «¿POR QUÉ NO SE VE ESTE PASAJERO EN EL INFORME?»
 *      Cancelar una reserva no dejaba rastro de quién lo hizo ni por qué. Se
 *      añade motivo, autor y fecha, y `aviso_viaje_perdido` para no repetir el
 *      aviso de «perdiste el viaje» cada vez que se sincroniza el estado.
 *
 * IDEMPOTENTE: se puede ejecutar tantas veces como haga falta.
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    /* ================================================================== */
    /* 1) Embarque y trazabilidad de la cancelación                       */
    /* ================================================================== */
    $columnas = [
        // NULL = todavía no se ha decidido; 1 = embarcó; 0 = no se presentó.
        // Se deja NULL a propósito: "no sabemos" y "no vino" no son lo mismo,
        // y un informe que los mezcla miente.
        "embarco TINYINT(1) NULL DEFAULT NULL COMMENT 'NULL=sin decidir · 1=embarcó · 0=no se presentó'",

        "embarque_por INT(11) NULL DEFAULT NULL COMMENT 'Conductor o admin que registró el embarque'",
        "embarque_fec DATETIME NULL DEFAULT NULL COMMENT 'Momento en que se registró el embarque'",

        "motivo_cancelacion VARCHAR(120) NULL DEFAULT NULL",
        "cancelado_por INT(11) NULL DEFAULT NULL",
        "fec_cancelacion DATETIME NULL DEFAULT NULL",

        // Marca de que ya se le avisó de que su viaje salió sin él.
        "aviso_viaje_perdido TINYINT(1) NOT NULL DEFAULT 0",
    ];

    foreach ($columnas as $columna) {
        $nombre = trim(strtok($columna, ' '));
        try {
            Database::query("ALTER TABLE reserva ADD COLUMN IF NOT EXISTS {$columna}");
            $log("[OK] reserva.{$nombre} agregada.");
        } catch (Throwable $e) {
            if (!Database::errorEs($e, [1060, 1061, 121, 1005])) throw $e;
            $log("[--] reserva.{$nombre} ya existía.");
        }
    }

    /* ================================================================== */
    /* 2) Índices: los listados nuevos filtran por viaje + estado           */
    /* ================================================================== */
    // 121 = ER_DUP_KEYNAME: entra cuando la base YA VIENE de un volcado que
    // incluye estos índices. Sin él en la lista, una instalación nueva
    // importada desde sget.sql reventaba al aplicar esta migración.
    foreach ([
        'ix_reserva_viaje_estado' => 'ADD INDEX IF NOT EXISTS ix_reserva_viaje_estado (id_via_res, estado_pago)',
        'ix_reserva_usuario_viaje' => 'ADD INDEX IF NOT EXISTS ix_reserva_usuario_viaje (id_usu_res, id_via_res)',
        'ix_reserva_embarco'       => 'ADD INDEX IF NOT EXISTS ix_reserva_embarco (embarco)',
    ] as $nombre => $sql) {
        try {
            Database::query("ALTER TABLE reserva {$sql}");
            $log("[OK] Índice {$nombre} creado.");
        } catch (Throwable $e) {
            if (!Database::errorEs($e, [1060, 1061, 1062, 121])) throw $e;
            $log("[--] Índice {$nombre} ya existía.");
        }
    }

    /* ================================================================== */
    /* 3) Clave foránea del autor de la cancelación                        */
    /* ================================================================== */
    try {
        Database::query(
            "ALTER TABLE reserva
               ADD CONSTRAINT fk_reserva_cancelador
               FOREIGN KEY (cancelado_por) REFERENCES usuario (id_usu)
               ON DELETE SET NULL ON UPDATE CASCADE"
        );
        $log('[OK] Clave foránea fk_reserva_cancelador agregada.');
    } catch (Throwable $e) {
        // 1824 = la FK ya existe; 121 = nombre de clave duplicado (otra
        // restricción con el mismo nombre). Las dos son "ya estaba".
        if (!Database::errorEs($e, [1060, 1061, 121, 1824])) throw $e;
        $log('[--] fk_reserva_cancelador ya existía.');
    }

    /* ================================================================== */
    /* 4) Respaldo del valor pactado                                       */
    /* ================================================================== */
    /* Las reservas pendientes nacieron con valor_pagado NULL: el número que el
       pasajero debe no se guardaba en ninguna parte (ver contexto 1). Aquí se
       rellena con la tarifa del viaje, que es exactamente lo que la pantalla
       de reserva le prometo al pasajero. Con esto el botón de cobrar deja de
       tener que adivinar, y el KPI «por cobrar» empieza a mostrar una cifra
       real en vez de 0. */
    try {
        $n = Database::query(
            "UPDATE reserva res
               INNER JOIN viaje v ON v.id_via = res.id_via_res
                SET res.valor_pagado = v.val_via
              WHERE (res.valor_pagado IS NULL OR res.valor_pagado <= 0)
                AND v.val_via > 0
                AND res.estado_pago <> 'Cancelada'"
        )->rowCount();

        $log($n > 0
            ? "[OK] {$n} reserva(s) rellenaron su valor pactado con la tarifa del viaje."
            : '[--] Todas las reservas ya tenían valor pactado.');
    } catch (Throwable $e) {
        $log('[!!] No se pudo recalcular el valor pactado: ' . $e->getMessage());
    }

    /* ================================================================== */
    /* 5) Marcas coherentes en las filas que YA están canceladas            */
    /* ================================================================== */
    try {
        Database::query(
            "UPDATE reserva
                SET estado_pago = 'Cancelada',
                    embarco = 0,
                    motivo_cancelacion = COALESCE(motivo_cancelacion, 'Cancelada antes de este registro'),
                    fec_cancelacion = COALESCE(fec_cancelacion, NOW())
              WHERE estado_pago = 'Cancelada'
                AND motivo_cancelacion IS NULL"
        );
        $log('[OK] Las reservas ya canceladas quedan con motivo y fecha.');
    } catch (Throwable $e) {
        $log('[!!] No se pudieron fechar las cancelaciones antiguas: ' . $e->getMessage());
    }

    $log('[OK] Listo: campo embarque, valor pactado y trazabilidad de cancelaciones.');
};
