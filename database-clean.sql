-- =========================================================================
-- SGET · Script de limpieza y reinicio para despliegue de prueba
-- =========================================================================
-- Uso en phpMyAdmin / MySQL Workbench:
--     mysql -uroot -p sget < database-clean.sql
--     (o importar desde la pestaña Importar de phpMyAdmin)
--
-- NOTA: los hashes son BCRYPT generados con PHP
--       Password::hash('123456789', PASSWORD_DEFAULT).
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- 1) Truncar / eliminar registros secundarios y dependientes
-- -------------------------------------------------------------------------
TRUNCATE TABLE `calificacion`;
TRUNCATE TABLE `reserva`;
TRUNCATE TABLE `notificacion`;
TRUNCATE TABLE `programacion`;
TRUNCATE TABLE `asignacion`;
TRUNCATE TABLE `sget_logs_auditoria`;
TRUNCATE TABLE `reportes_pasajeros`;
TRUNCATE TABLE `restricciones`;
TRUNCATE TABLE `usuario_permiso_denegado`;
TRUNCATE TABLE `usuario_permisos`;
TRUNCATE TABLE `rutas`;   -- opcional; mantenemos para no romper viajes
TRUNCATE TABLE `vehiculo`; -- opcional; mantenemos para no romper viajes
TRUNCATE TABLE `viaje`;    -- opcional; mantenemos para no romper viajes
TRUNCATE TABLE `anuncio`;

-- -------------------------------------------------------------------------
-- 2) Eliminar todos los usuarios existentes
-- -------------------------------------------------------------------------
DELETE FROM `usuario`;

-- -------------------------------------------------------------------------
-- 3) Restablecer AUTO_INCREMENT de las tablas principales
-- -------------------------------------------------------------------------
ALTER TABLE `reserva` AUTO_INCREMENT = 1;
ALTER TABLE `calificacion` AUTO_INCREMENT = 1;
ALTER TABLE `asignacion` AUTO_INCREMENT = 1;
ALTER TABLE `programacion` AUTO_INCREMENT = 1;
ALTER TABLE `notificacion` AUTO_INCREMENT = 1;
ALTER TABLE `sget_logs_auditoria` AUTO_INCREMENT = 1;
ALTER TABLE `reportes_pasajeros` AUTO_INCREMENT = 1;
ALTER TABLE `restricciones` AUTO_INCREMENT = 1;
ALTER TABLE `anuncio` AUTO_INCREMENT = 1;
ALTER TABLE `viaje` AUTO_INCREMENT = 1;
ALTER TABLE `vehiculo` AUTO_INCREMENT = 1;
ALTER TABLE `usuario` AUTO_INCREMENT = 1;
ALTER TABLE `rutas` AUTO_INCREMENT = 1;

-- -------------------------------------------------------------------------
-- 4) Insertar los 3 usuarios de prueba con contraseñas BCRYPT
-- -------------------------------------------------------------------------
-- Administrador (Rol 1)
INSERT INTO `usuario`
  (`tip_doc_usu`, `num_doc_usu`, `nom_usu`, `corre_usu`, `tel_usu`, `id_rol_usu`, `pass_usu`, `estado`, `est_con_usu`, `google_id`, `acepta_politica`, `fecha_acepta_politica`)
VALUES
  ('CC', '100001', 'Administrador SGET', 'admin@sget.local', '3000000000', 1, '$2y$10$44N08sPAVLd6sE3lZ8s39.MocRIPGxWZ.p7bsCP45i7tXCqGxtFea', 1, NULL, NULL, 1, NOW());

-- Conductor (Rol 2)
INSERT INTO `usuario`
  (`tip_doc_usu`, `num_doc_usu`, `nom_usu`, `corre_usu`, `tel_usu`, `id_rol_usu`, `pass_usu`, `estado`, `est_con_usu`, `google_id`, `acepta_politica`, `fecha_acepta_politica`)
VALUES
  ('CC', '100002', 'Conductor de Prueba', 'conductor@sget.local', '3000000001', 2, '$2y$10$KZoNq7xhbHlQn8./.7dlROn0MGmpFhqtvyjyw4VO2JzbtTQIWpgIa', 1, 1, NULL, 1, NOW());

-- Pasajero (Rol 3)
INSERT INTO `usuario`
  (`tip_doc_usu`, `num_doc_usu`, `nom_usu`, `corre_usu`, `tel_usu`, `id_rol_usu`, `pass_usu`, `estado`, `est_con_usu`, `google_id`, `acepta_politica`, `fecha_acepta_politica`)
VALUES
  ('CC', '100003', 'Pasajero de Prueba', 'pasajero@sget.local', '3000000002', 3, '$2y$10$pqkiiRk8ralxiCPn3OU/GeH4OQRsZIE4U.g.FMNkfrRPKROOaGB7K', 1, NULL, NULL, 1, NOW());

-- -------------------------------------------------------------------------
-- 5) Finalizar
-- -------------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;
