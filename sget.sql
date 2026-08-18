-- Crear base de datos
CREATE DATABASE IF NOT EXISTS sget;
USE sget;

-- 1. Tabla: rol
CREATE TABLE `rol` (
  `id_rol` INT(11) AUTO_INCREMENT NOT NULL,
  `nom_rol` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla: usuario
CREATE TABLE `usuario` (
  `id_usu` INT(11) AUTO_INCREMENT NOT NULL,
  `tip_doc_usu` VARCHAR(300) NOT NULL,
  `num_doc_usu` VARCHAR(200) NOT NULL,
  `nom_usu` VARCHAR(100) NOT NULL,
  `corre_usu` VARCHAR(100) NOT NULL,
  `tel_usu` VARCHAR(20) DEFAULT NULL,
  `id_rol_usu` INT(11) NOT NULL,
  `pass_usu` VARCHAR(200) NOT NULL,
  `estado` TINYINT(1) DEFAULT 1,
  `est_con_usu` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id_usu`),
  KEY `fk_usuario_rol` (`id_rol_usu`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol_usu`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla: rutas
CREATE TABLE `rutas` (
  `id_rut` INT(11) AUTO_INCREMENT NOT NULL,
  `nom_rut` VARCHAR(100) NOT NULL,
  `dis_rut` DECIMAL(10,2) DEFAULT NULL,
  `val_rut` FLOAT DEFAULT NULL,
  `ori_rut` VARCHAR(100) NOT NULL,
  `des_rut` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id_rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla: vehiculo
CREATE TABLE `vehiculo` (
  `id_veh` INT(11) AUTO_INCREMENT NOT NULL,
  `pla_veh` VARCHAR(7) NOT NULL,
  `mode_veh` VARCHAR(50) DEFAULT NULL,
  `cap_veh` INT(11) DEFAULT NULL,
  `est_veh` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id_veh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla: viaje
CREATE TABLE `viaje` (
  `id_via` INT(11) AUTO_INCREMENT NOT NULL,
  `nom_via` VARCHAR(100) DEFAULT NULL,
  `fec_via` DATETIME DEFAULT NULL,
  `hor_sal_via` DATETIME DEFAULT NULL,
  `hor_lleg_via` DATETIME DEFAULT NULL,
  `val_via` FLOAT DEFAULT NULL,
  `id_rut_via` INT(11) NOT NULL,
  `id_usu_via` INT(11) NOT NULL,
  `est_via` VARCHAR(20) DEFAULT NULL,
  `id_veh` INT(11) DEFAULT NULL,
  `cup_tot` INT(11) DEFAULT NULL,
  `cup_dis` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id_via`),
  KEY `fk_viaje_rutas` (`id_rut_via`),
  KEY `fk_viaje_usuario` (`id_usu_via`),
  KEY `fk_viaje_vehiculo` (`id_veh`),
  CONSTRAINT `fk_viaje_rutas` FOREIGN KEY (`id_rut_via`) REFERENCES `rutas` (`id_rut`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_viaje_usuario` FOREIGN KEY (`id_usu_via`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_viaje_vehiculo` FOREIGN KEY (`id_veh`) REFERENCES `vehiculo` (`id_veh`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabla: calificacion
CREATE TABLE `calificacion` (
  `id_cal` INT(11) AUTO_INCREMENT NOT NULL,
  `id_via_cal` INT(11) NOT NULL,
  `id_usu_rem` INT(11) NOT NULL,
  `id_usu_des` INT(11) NOT NULL,
  `pun_cal` TINYINT(1) NOT NULL,
  `com_cal` VARCHAR(255) DEFAULT NULL,
  `fec_cal` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cal`),
  KEY `fk_calificacion_viaje` (`id_via_cal`),
  KEY `fk_calificacion_remitente` (`id_usu_rem`),
  KEY `fk_calificacion_destinatario` (`id_usu_des`),
  CONSTRAINT `fk_calificacion_viaje` FOREIGN KEY (`id_via_cal`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calificacion_remitente` FOREIGN KEY (`id_usu_rem`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calificacion_destinatario` FOREIGN KEY (`id_usu_des`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabla: asignacion
CREATE TABLE `asignacion` (
  `id_asig` INT(11) AUTO_INCREMENT NOT NULL,
  `id_usu_asig` INT(11) NOT NULL,
  `id_veh_asig` INT(11) NOT NULL,
  `nom_via_asig` VARCHAR(155) DEFAULT NULL,
  `fec_asig` DATE DEFAULT NULL,
  PRIMARY KEY (`id_asig`),
  KEY `fk_asignacion_usuario` (`id_usu_asig`),
  KEY `fk_asignacion_vehiculo` (`id_veh_asig`),
  CONSTRAINT `fk_asignacion_usuario` FOREIGN KEY (`id_usu_asig`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_vehiculo` FOREIGN KEY (`id_veh_asig`) REFERENCES `vehiculo` (`id_veh`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabla: programacion
CREATE TABLE `programacion` (
  `id_prog` INT(11) AUTO_INCREMENT NOT NULL,
  `id_veh_prog` INT(11) NOT NULL,
  `id_via_prog` INT(11) NOT NULL,
  PRIMARY KEY (`id_prog`),
  KEY `fk_programacion_vehiculo` (`id_veh_prog`),
  KEY `fk_programacion_viaje` (`id_via_prog`),
  CONSTRAINT `fk_programacion_vehiculo` FOREIGN KEY (`id_veh_prog`) REFERENCES `vehiculo` (`id_veh`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_programacion_viaje` FOREIGN KEY (`id_via_prog`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabla: reserva
CREATE TABLE `reserva` (
  `id_res` INT(11) AUTO_INCREMENT NOT NULL,
  `id_via_res` INT(11) NOT NULL,
  `id_usu_res` INT(11) NOT NULL,
  `fech_res` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `metodo_pago` VARCHAR(50) DEFAULT NULL,
  `valor_pagado` DECIMAL(10,2) DEFAULT NULL,
  `estado_pago` VARCHAR(20) DEFAULT NULL,
  `fecha_pago` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_res`),
  KEY `fk_reserva_viaje` (`id_via_res`),
  KEY `fk_reserva_usuario` (`id_usu_res`),
  CONSTRAINT `fk_reserva_viaje` FOREIGN KEY (`id_via_res`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reserva_usuario` FOREIGN KEY (`id_usu_res`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Tabla: reportes_pasajeros
CREATE TABLE `reportes_pasajeros` (
  `id_rep` INT(11) AUTO_INCREMENT NOT NULL,
  `id_usu_rep` INT(11) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `estado` VARCHAR(20) DEFAULT NULL,
  `id_via_rep` INT(11) NOT NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rep`),
  KEY `fk_reportes_usuario` (`id_usu_rep`),
  KEY `fk_reportes_viaje` (`id_via_rep`),
  CONSTRAINT `fk_reportes_usuario` FOREIGN KEY (`id_usu_rep`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reportes_viaje` FOREIGN KEY (`id_via_rep`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE rutas ADD COLUMN img_rut VARCHAR(255) NULL;