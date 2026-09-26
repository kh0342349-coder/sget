<?php
/**
 * DESC: Duración estimada por ruta + base del cierre automático correcto
 *
 * CONTEXTO
 *   El cierre automático anterior era:
 *       TIMESTAMP(fec_via, hor_sal_via) <= (NOW() - INTERVAL 24 HOUR)
 *   es decir, "24 horas después de la hora programada", sin importar cuánto
 *   durara el trayecto. Un viaje que sale a las 07:00 se cerraba al día
 *   siguiente a las 07:00 aunque el recorrido durara 2 horas.
 *
 *   Ahora la regla es: el viaje termina cuando se cumple la DURACIÓN REAL
 *   DEL TRAYECTO (`rutas.duracion_min`):
 *       instante_salida + duracion_min <= NOW()
 *   Un viaje que sale mañana a las 07:00 con 150 min de trayecto termina
 *   mañana a las 09:30, y NO al día siguiente.
 *
 *   Además se distingue el passage:
 *       · salio = 0  ->  la hora de salida aún no pasó      (Programado)
 *       · salio = 1  ->  ya arrancó                       (En curso)
 *       · vencido    ->  se cumplió salida + duración      (Finalizado)
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    /* ------------------------------------------------------------------ */
    /* 1) Duración estimada de cada ruta                                   */
    /* ------------------------------------------------------------------ */
    try {
        Database::query(
            "ALTER TABLE rutas ADD COLUMN IF NOT EXISTS duracion_min INT(11) NULL DEFAULT NULL
             COMMENT 'Duración estimada del trayecto en minutos'"
        );
        $log('[OK] rutas.duracion_min agregada (NULL = usar el valor por defecto del sistema).');
    } catch (Throwable $e) {
        if (!in_array($e->errorInfo[1] ?? 0, [1060, 1061], true)) throw $e;
        $log('[--] rutas.duracion_min ya existía.');
    }

    /* ------------------------------------------------------------------ */
    /* 2) Duración por defecto en las rutas que no la tienen              */
    /*    Estimación: 45 km/h de velocidad media en carretera.             */
    /* ------------------------------------------------------------------ */
    $defecto = (int) Config::DURACION_VIAJE_MIN_POR_DEFECTO;
    $n = Database::query(
        "UPDATE rutas r
            SET r.duracion_min = CASE
                  WHEN r.dis_rut IS NULL OR r.dis_rut <= 0 THEN {$defecto}
                  ELSE GREATEST(30, ROUND((r.dis_rut / 45) * 60))
                END
          WHERE r.duracion_min IS NULL OR r.duracion_min <= 0"
    )->rowCount();
    $log("RUTAS · {$n} ruta(s) con duración estimada según su distancia (45 km/h).");

    /* ------------------------------------------------------------------ */
    /* 3) Índice para la consulta de cierre automático                     */
    /* ------------------------------------------------------------------ */
    try {
        Database::query("CREATE INDEX idx_viaje_cierre ON viaje (est_via, fec_via, hor_sal_via)");
        $log('[OK] Índice idx_viaje_creado para el cierre automático.');
    } catch (Throwable $e) {
        if (!in_array($e->errorInfo[1] ?? 0, [1061], true)) throw $e;
        $log('[--] El índice de cierre ya existía.');
    }

    /* ------------------------------------------------------------------ */
    /* 4) Recalcular `salio` de los viajes que ya pasaron su hora de salida */
    /* ------------------------------------------------------------------ */
    $n = Database::query(
        "UPDATE viaje
            SET salio = 1
          WHERE salio = 0
            AND fec_via IS NOT NULL
            AND hor_sal_via IS NOT NULL
            AND TIMESTAMP(fec_via, hor_sal_via) <= NOW()"
    )->rowCount();
    $log("VIAJES · {$n} viaje(s) marcados como ya haber salido.");

    /* ------------------------------------------------------------------ */
    /* 5) Resumen de lo que tardará el cierre automático                    */
    /* ------------------------------------------------------------------ */
    $log('ESTADO · duración media de las rutas: ' . (int) Database::scalar(
        "SELECT ROUND(AVG(duracion_min)) FROM rutas WHERE duracion_min IS NOT NULL"
    ) . ' minutos.');
};
