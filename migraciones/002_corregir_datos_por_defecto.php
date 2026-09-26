<?php
/**
 * DESC: Corregir la inconsistencia de datos y los valores por defecto (Data Default Fallback)
 *
 * CONTENIDO
 *   RUTAS   -> ori_rut / des_rut quedaban como '' (cadena vacía) porque el INSERT
 *              de Admin/rutas.php no los enviaba y el motor no estaba en modo estricto.
 *              Se recuperan del nombre de la ruta ("A - B") o se marcan "Por definir".
 *   VIAJES  -> hor_sal_via = '0000-00-00 00:00:00' (FECHA CERO) y fec_via con hora 00:00:00.
 *              Se reconstruyen con valores válidos y explícitos.
 *   USUARIOS-> estado = NULL dejaba al usuario invisible en la UI y est_con_usu
 *              mezclaba 0, 1, NULL y 'Disponible'.
 *   VEHICULO-> est_veh mezclaba 0/1 con las constantes globales 1/2.
 *
 * Esta migración SOLO sanea datos. El DDL (ALTER TABLE) vive en 003.
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    /* ---------------------------------------------------------------- */
    /* 1) RUTAS                                                          */
    /* ---------------------------------------------------------------- */
    $log('RUTAS · normalizando origen, destino, distancia y tarifa...');

    $rutas = Database::all("SELECT id_rut, nom_rut, ori_rut, des_rut, dis_rut, val_rut, img_rut FROM rutas");
    $fix = 0;

    foreach ($rutas as $r) {
        $nom   = trim((string)$r['nom_rut']);
        $ori   = trim((string)$r['ori_rut']);
        $des   = trim((string)$r['des_rut']);
        $oriDef = $ori !== '' ? $ori : null;
        $desDef = $des !== '' ? $des : null;

        // "Fusagasugá - Silvania"  ->  ori = Fusagasugá, des = Silvania
        if ($oriDef === null && mb_strpos($nom, '-') !== false) {
            $partes = array_map('trim', explode('-', $nom));
            if (count($partes) >= 2) {
                $oriDef = $partes[0];
                $desDef = $desDef ?? $partes[count($partes) - 1];
            }
        }
        $oriDef = $oriDef !== null && $oriDef !== '' ? $oriDef : 'Por definir';
        $desDef = $desDef !== null && $desDef !== '' ? $desDef : 'Por definir';

        $dis   = $r['dis_rut'] === null ? 0.0 : (float)$r['dis_rut'];
        $val   = $r['val_rut'] === null ? 0.0 : (float)$r['val_rut'];
        $img   = ($r['img_rut'] === null || trim((string)$r['img_rut']) === '') ? null : $r['img_rut'];

        if ($ori !== $oriDef || $des !== $desDef || $r['dis_rut'] === null || $r['val_rut'] === null) {
            Database::query(
                "UPDATE rutas SET ori_rut = ?, des_rut = ?, dis_rut = ?, val_rut = ?, img_rut = ? WHERE id_rut = ?",
                [$oriDef, $desDef, $dis, $val, $img, (int)$r['id_rut']]
            );
            $fix++;
        }
    }
    $log("RUTAS · {$fix} registro(s) corregido(s).");

    /* ---------------------------------------------------------------- */
    /* 2) VIAJES · fin de la fecha cero                                  */
    /* ---------------------------------------------------------------- */
    $log('VIAJES · eliminando fechas/horas cero (0000-00-00)...');

    $viajes = Database::all("SELECT id_via, fec_via, hor_sal_via, hor_lleg_via FROM viaje");
    $fecHoy = date('Y-m-d');
    $horaDef = '07:00:00';
    $fix = 0;

    foreach ($viajes as $v) {
        // Lectura CRUDA: MariaDB no puede castear fechas cero, por eso se
        // usa la representación textual que devuelve el driver en modo permisivo.
        $fec  = self_fecha_valida(self_leer($v['fec_via'], 10), $fecHoy);
        $sal  = self_hora_valida(self_leer($v['hor_sal_via'], 11), $horaDef);
        $lleg = self_leer($v['hor_lleg_via'], 11);
        $lleg = $lleg !== '' ? self_hora_valida($lleg, '') : '';

        // La columna sigue siendo DATETIME en esta migración (003 la estrecha a
        // DATE + TIME), así que se escribe siempre un DATETIME completo y válido.
        $nuevoFec = $fec . ' 00:00:00';
        $nuevoSal = $fec . ' ' . ($sal !== '' ? $sal : $horaDef);
        $nuevoLle = $lleg !== '' ? $fec . ' ' . $lleg : null;

        if (self_leer($v['fec_via'], 10) !== $fec || self_leer($v['hor_sal_via'], 11) !== $sal) {
            Database::query(
                "UPDATE viaje SET fec_via = ?, hor_sal_via = ?, hor_lleg_via = ? WHERE id_via = ?",
                [$nuevoFec, $nuevoSal, $nuevoLle, (int)$v['id_via']]
            );
            $fix++;
        }
    }
    $log("VIAJES · {$fix} registro(s) normalizado(s).");

    /* ---------------------------------------------------------------- */
    /* 3) USUARIOS                                                       */
    /* ---------------------------------------------------------------- */
    $log('USUARIOS · unificando estado y disponibilidad del conductor...');

    $n = Database::query("UPDATE usuario SET estado = 1 WHERE estado IS NULL")->rowCount();
    $log("USUARIOS · {$n} cuenta(s) con estado NULL -> Activo.");

    $n = Database::query(
        "UPDATE usuario SET est_con_usu = 1 WHERE id_rol_usu = 2 AND (est_con_usu IS NULL OR est_con_usu NOT IN (0,1))"
    )->rowCount();
    $n += Database::query("UPDATE usuario SET est_con_usu = NULL WHERE id_rol_usu <> 2")->rowCount();
    $log("USUARIOS · {$n} registro(s) de disponibilidad normalizado(s).");

    /* ---------------------------------------------------------------- */
    /* 4) VEHICULOS                                                     */
    /* ---------------------------------------------------------------- */
    $log('VEHICULOS · unificando el estado operativo (1 = Disponible / 0 = Fuera de servicio)...');

    $n = Database::query("UPDATE vehiculo SET est_veh = 1 WHERE est_veh IS NULL OR est_veh NOT IN (0,1)")->rowCount();
    $n += Database::query("UPDATE vehiculo SET mode_veh = 'Sin modelo' WHERE mode_veh IS NULL OR TRIM(mode_veh) = ''")->rowCount();
    $n += Database::query("UPDATE vehiculo SET cap_veh = 0 WHERE cap_veh IS NULL")->rowCount();
    $log("VEHICULOS · {$n} campo(s) normalizado(s).");

    /* ---------------------------------------------------------------- */
    /* 5) COHERENCIA CON LOS VIAJES REALES                               */
    /* ---------------------------------------------------------------- */
    $log('VIAJES · reconciliando conductor/vehículo con los viajes activos...');

    $n = Database::query(
        "UPDATE usuario u SET u.est_con_usu = 0
          WHERE EXISTS (SELECT 1 FROM viaje j
                         WHERE j.id_usu_via = u.id_usu AND j.est_via IN ('Programado','En curso'))"
    )->rowCount();
    $log("USUARIOS · {$n} conductor(es) marcado(s) como ocupado(s).");

    $n = Database::query(
        "UPDATE vehiculo v SET v.est_veh = 0
          WHERE EXISTS (SELECT 1 FROM viaje j
                         WHERE j.id_veh = v.id_veh AND j.est_via IN ('Programado','En curso'))"
    )->rowCount();
    $log("VEHICULOS · {$n} unidad(es) en servicio por viaje(s) activo(s).");

    /* ---------------------------------------------------------------- */
    /* 6) ESTADOS DE VIAJE (typos históricos como 'Activo')              */
    /* ---------------------------------------------------------------- */
    $n = Database::query(
        "UPDATE viaje SET est_via = 'Programado'
          WHERE est_via IS NULL OR TRIM(est_via) = ''
             OR est_via NOT IN ('Programado','En curso','Finalizado','Cancelado')"
    )->rowCount();
    if ($n > 0) $log("VIAJES · {$n} estado(s) inválido(s) -> Programado.");

    /* ---------------------------------------------------------------- */
    /* 6b) NUMERICOS DE VIAJE (NULL -> 0 explicito)                       */
    /* ---------------------------------------------------------------- */
    $log('VIAJES · dando valor por defecto explícito a tarifa y cupos...');
    $n = Database::query("UPDATE viaje SET val_via = 0 WHERE val_via IS NULL")->rowCount();
    $n += Database::query("UPDATE viaje SET cup_tot = 0 WHERE cup_tot IS NULL")->rowCount();
    $n += Database::query("UPDATE viaje SET cup_dis = 0 WHERE cup_dis IS NULL")->rowCount();
    $n += Database::query("UPDATE viaje SET nom_via = CONCAT('Viaje #', id_via) WHERE nom_via IS NULL OR TRIM(nom_via) = ''")->rowCount();
    $log("VIAJES · {$n} campo(s) normalizado(s).");

    /* ---------------------------------------------------------------- */
    /* 7) RESERVAS                                                       */
    /* ---------------------------------------------------------------- */
    Database::query("UPDATE reserva SET estado_pago = 'Pendiente' WHERE estado_pago IS NULL OR TRIM(estado_pago) = ''");
    Database::query("UPDATE reserva SET metodo_pago = 'Por definir' WHERE metodo_pago IS NULL OR TRIM(metodo_pago) = ''");

    /* ---------------------------------------------------------------- */
    /* 8) BACKUP DE SEGURIDAD                                           */
    /* ---------------------------------------------------------------- */
    Database::query("DROP TABLE IF EXISTS _backup_pre_migracion_viaje");
    Database::query("CREATE TABLE _backup_pre_migracion_viaje AS SELECT * FROM viaje");
    $log('Se guardó una copia de `viaje` en `_backup_pre_migracion_viaje`.');
};

/* ======================================================================== */
/* Helpers locales (no colisionan con el resto del proyecto)               */
/* ======================================================================== */

if (!function_exists('self_leer')) {
    /**
     * Devuelve la representación textual de un valor DATETIME/TIME sin
     * depender de DATE()/TIME(), que fallan con fechas cero.
     */
    function self_leer($valor, int $offset): string
    {
        if ($valor === null) return '';
        $s = (string)$valor;
        if (trim($s) === '' || str_starts_with($s, '0000-00-00')) return '';
        return $offset === 11 && strlen($s) > 11 ? substr($s, 11) : $s;
    }
}

if (!function_exists('self_fecha_valida')) {
    function self_fecha_valida(string $valor, string $defecto): string
    {
        if ($valor === '') return $defecto;
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $valor) ?: DateTime::createFromFormat('Y-m-d', $valor);
        if (!$d) return $defecto;
        return $d->format('Y-m-d');
    }
}

if (!function_exists('self_hora_valida')) {
    function self_hora_valida(string $valor, string $defecto): string
    {
        if ($valor === '') return $defecto;
        $d = DateTime::createFromFormat('H:i:s', $valor) ?: DateTime::createFromFormat('H:i', $valor);
        if (!$d) return $defecto;
        // 00:00:00 en un viaje significa "dato no informado" (fallback heredado)
        if ($d->format('H:i:s') === '00:00:00') return $defecto !== '' ? $defecto : '00:00:00';
        return $d->format('H:i:s');
    }
}
