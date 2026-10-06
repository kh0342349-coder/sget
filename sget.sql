-- =============================================================================
--  sget.sql  ·  VOLCADO COMPLETO DE SGET (Sistema de Gestión de Transporte)
-- =============================================================================
--  Este archivo crea la base de datos, la deja creada y la puebla. Es todo lo
--  que hace falta para levantar el proyecto en una máquina limpia:
--
--      mysql -uroot < sget.sql
--      php migraciones/migrar.php          <-- OBLIGATORIO, ver más abajo
--
-- -----------------------------------------------------------------------------
--  USUARIOS DE PRUEBA INCLUIDOS
-- -----------------------------------------------------------------------------
--  Tres cuentas, una por rol, con las MISMAS credenciales para que probar sea
--  cómodo. Las軌 contrasenas se guardan con
--  `password_hash(..., PASSWORD_DEFAULT)` (bcrypt): en este archivo no aparece
--  ningún texto plano.
--
--      Rol            Documento    Correo                  Contrasena
--      ------------   ----------   ---------------------   -----------
--      Administrador   900000001    admin@sget.local        Sget#2026!
--      Conductor       900000002    conductor@sget.local    Sget#2026!
--      Pasajero        900000003    pasajero@sget.local     Sget#2026!
--
--  En un entorno real hay que cambiar la del administrador.
--
-- -----------------------------------------------------------------------------
--  ESQUEMA · LO QUE CONTIENE Y POR QUÉ
-- -----------------------------------------------------------------------------
--  · `usuario`
--      UNIQUE en `num_doc_usu` (la llave con la que se entra), en `corre_usu` y
--      en `google_id`. Antes solo lo comprobaba el PHP y dos altas simultáneas
--      podían colarse.
--  · `vehiculo.est_veh` es un ENUM de cuatro estados, no un 0/1:
--        Disponible · Asignado · Mantenimiento · Fuera de servicio
--      Con 0/1, «en mantenimiento» y «averiada» eran lo mismo, y asignar un viaje
--      dejaba la unidad marcada como averiada.
--  · `viaje` no tiene índices duplicados (se eliminó `idx_viaje_cierre`, que era
--      idéntico a `idx_viaje_salida`) y sí tiene índice por conductor.
--  · `permisos` + `rol_permiso` + `usuario_permisos` implementan la cascada de
--      autorización (principio de mínimo privilegio):
--          1. `usuario_permisos` gana siempre (concedido/denegado explícito);
--          2. si no hay decisión, decide `rol_permiso`;
--          3. si tampoco, se DENIEGA. Nunca «sin permiso = permitido».
--  · `restricciones` es una tabla legacy vacía: se conserva solo para no romper
--      instalaciones antiguas que la referencien.
--
-- -----------------------------------------------------------------------------
--  POR QUÉ HAY QUE CORRER LAS MIGRACIONES DESPUÉS DE IMPORTAR
-- -----------------------------------------------------------------------------
--  La tabla `migracion` se crea VACÍA a propósito. Un volcado SQL no puede
--  llevar dentro un archivo de imagen, y la migración 007 es la que copia
--  `img/anuncios/portada.svg` a `uploads/anuncios/`: sin ella, el anuncio de
--  ejemplo se queda apuntando a una imagen inexistente y la portada sale en
--  negro. Al venir la tabla vacía, `migrar.php` aplica 002 a 009, que son
--  idempotentes: sobre datos ya correctos no cambian nada.
--
-- -----------------------------------------------------------------------------
--  SE EXCLUYEN A PROPÓSITO
-- -----------------------------------------------------------------------------
--  · `migracion`                    → vacía (ver arriba).
--  · `_backup_pre_migracion_viaje`  → la crea y borra la propia migración 002.
--
--  Las contrasenas de `usuario.pass_usu` son hashes bcrypt, nunca texto plano.
-- =============================================================================

DROP DATABASE IF EXISTS `sget`;
CREATE DATABASE /*!32312 IF NOT EXISTS*/ `sget` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `sget`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `anuncio`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anuncio` (
  `id_ann` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) NOT NULL COMMENT 'Archivo dentro de uploads/anuncios',
  `enlace` varchar(255) DEFAULT NULL COMMENT 'URL o modulo interno (rutas.php, viajes.php…)',
  `boton_texto` varchar(60) DEFAULT NULL,
  `color_tema` varchar(20) NOT NULL DEFAULT 'azul' COMMENT 'azul|emerald|ambars|morado|rojo',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `destacado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = aparece como destacado en la landing',
  `fec_inicio` date DEFAULT NULL COMMENT 'NULL = desde ya',
  `fec_fin` date DEFAULT NULL COMMENT 'NULL = sin vencimiento',
  `Orden` int(11) NOT NULL DEFAULT 0,
  `veces_vista` int(11) NOT NULL DEFAULT 0,
  `creado_por` int(11) DEFAULT NULL,
  `fec_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ann`),
  KEY `ux_anuncio_activo` (`activo`,`fec_inicio`,`fec_fin`),
  KEY `ux_anuncio_orden` (`Orden`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anuncio`
--

LOCK TABLES `anuncio` WRITE;
/*!40000 ALTER TABLE `anuncio` DISABLE KEYS */;
INSERT INTO `anuncio` VALUES (21,'Bienvenido a SGET','Reserva tu cupo en línea y monitorea tu viaje en tiempo real','Consulta los viajes disponibles, asegura tu puesto y recibe avisos si algo cambia.','portada.svg','Admin/viajes.php','Ver viajes','azul',1,1,NULL,NULL,1,27,NULL,'2026-09-29 11:53:21');
/*!40000 ALTER TABLE `anuncio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asignacion`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asignacion` (
  `id_asig` int(11) NOT NULL AUTO_INCREMENT,
  `id_usu_asig` int(11) NOT NULL,
  `id_veh_asig` int(11) NOT NULL,
  `nom_via_asig` varchar(155) DEFAULT NULL,
  `fec_asig` date DEFAULT NULL,
  PRIMARY KEY (`id_asig`),
  KEY `fk_asignacion_usuario` (`id_usu_asig`),
  KEY `fk_asignacion_vehiculo` (`id_veh_asig`),
  CONSTRAINT `fk_asignacion_usuario` FOREIGN KEY (`id_usu_asig`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_vehiculo` FOREIGN KEY (`id_veh_asig`) REFERENCES `vehiculo` (`id_veh`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asignacion`
--

LOCK TABLES `asignacion` WRITE;
/*!40000 ALTER TABLE `asignacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `asignacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calificacion`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calificacion` (
  `id_cal` int(11) NOT NULL AUTO_INCREMENT,
  `id_via_cal` int(11) NOT NULL,
  `id_usu_rem` int(11) NOT NULL,
  `id_usu_des` int(11) NOT NULL,
  `pun_cal` tinyint(1) NOT NULL,
  `com_cal` varchar(255) DEFAULT NULL,
  `fec_cal` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cal`),
  KEY `fk_calificacion_viaje` (`id_via_cal`),
  KEY `fk_calificacion_remitente` (`id_usu_rem`),
  KEY `fk_calificacion_destinatario` (`id_usu_des`),
  CONSTRAINT `fk_calificacion_destinatario` FOREIGN KEY (`id_usu_des`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calificacion_remitente` FOREIGN KEY (`id_usu_rem`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calificacion_viaje` FOREIGN KEY (`id_via_cal`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calificacion`
--

LOCK TABLES `calificacion` WRITE;
/*!40000 ALTER TABLE `calificacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `calificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacion`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificacion` (
  `id_not` int(11) NOT NULL AUTO_INCREMENT,
  `id_usu` int(11) NOT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'aviso',
  `titulo` varchar(150) NOT NULL,
  `cuerpo` text NOT NULL,
  `id_via` int(11) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fec_envio` datetime NOT NULL DEFAULT current_timestamp(),
  `firma` varchar(160) DEFAULT NULL,
  PRIMARY KEY (`id_not`),
  UNIQUE KEY `ux_notif_firma` (`id_usu`,`firma`),
  KEY `fk_notif_usuario` (`id_usu`),
  KEY `fk_notif_viaje` (`id_via`),
  KEY `idx_notif_buzon` (`id_usu`,`leida`,`fec_envio`),
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usu`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_viaje` FOREIGN KEY (`id_via`) REFERENCES `viaje` (`id_via`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion`
--

LOCK TABLES `notificacion` WRITE;
/*!40000 ALTER TABLE `notificacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos` (
  `id_permiso` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_permiso` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `nombre_permiso` (`nombre_permiso`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

LOCK TABLES `permisos` WRITE;
/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
INSERT INTO `permisos` VALUES (1,'crear_ruta','rutas',1,'Permite registrar nuevas rutas'),(2,'editar_ruta','rutas',1,'Permite modificar rutas existentes'),(3,'eliminar_ruta','rutas',1,'Permite eliminar rutas del sistema'),(4,'crear_viaje','viaje',2,'Permite publicar/programar un nuevo viaje'),(5,'cancelar_viaje','viaje',2,'Permite cancelar un viaje programado'),(6,'redirigir_viaje','viaje',2,'Permite cambiar trayecto/destino de un viaje'),(7,'hacer_reserva','reserva',3,'Permite a los pasajeros reservar cupos'),(8,'cancelar_reserva','reserva',3,'Permite cancelar una reserva activa'),(9,'registrar_vehiculo','vehiculo',1,'Permite agregar vehículos a la flota'),(10,'asignar_conductor','asignacion',1,'Permite vincular un conductor a un vehículo'),(11,'gestionar_permisos','usuario',1,'Permite al administrador asignar permisos a los usuarios'),(12,'ver_reportes','reportes_pasajeros',1,'Permite gestionar y atender reportes de pasajeros'),(13,'suspender_ruta','rutas',1,'Permite suspender o reactivar una ruta'),(14,'suspender_usuario','usuario',1,'Permite suspender o reactivar una cuenta'),(15,'suspender_vehiculo','vehiculo',1,'Permite poner un vehículo fuera de servicio'),(16,'cancelar_viaje_admin','viaje',1,'Permite cancelar un viaje con anotación obligatoria'),(17,'ver_notificaciones','pasajero',3,'Permite ver el buzón de notificaciones'),(18,'gestionar_anuncios','anuncios',1,'Permite subir, editar, publicar y eliminar anuncios de la página de inicio'),(19,'gestionar_comunicados','comunicados',1,'Permite enviar avisos y comunicados al buzón de pasajeros y conductores'),(20,'gestionar_reportes_pasajeros','reportes_pasajeros',1,'Permite gestionar los reportes y quejas de los pasajeros'),(28,'acceder_admin','admin',1,'Permite entrar al panel general del administrador'),(29,'gestionar_usuarios','usuarios',1,'Permite crear, editar y eliminar usuarios'),(30,'gestionar_asignaciones','asignaciones',1,'Permite el recaudo en terminal y la corta de reservas'),(31,'gestionar_viajes','viajes',1,'Permite programar y editar viajes de toda la flota'),(32,'gestionar_vehiculos','vehiculos',1,'Permite administrar la flota de vehículos'),(33,'gestionar_permisos_mod','gestion_permisos',1,'Permite asignar permisos a los usuarios'),(34,'ver_logs','logs',1,'Permite consultar los registros de auditoría'),(35,'ver_reportes_panel','reportes',1,'Permite consultar el panel de información y sus PDF'),(36,'ver_calificaciones','ranking_conductores',1,'Permite ver el ranking y las reseñas de los conductores'),(37,'calificar_viaje','calificar',3,'Permite calificar un viaje terminado'),(38,'gestionar_reservas','reservas',3,'Permite reservar puesto en un viaje'),(39,'ver_buzon','notificaciones',3,'Permite consultar el buzón de notificaciones'),(40,'ver_manifiesto','manifiesto',2,'Permite ver y marcar el embarque de los viajes que conduce'),(41,'gestionar_propio_viaje','mis_viajes',2,'Permite consultar sus propios viajes'),(63,'operar_viaje','operar_viaje',2,'Permite marcar en curso y finalizar sus propios viajes');
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programacion`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programacion` (
  `id_prog` int(11) NOT NULL AUTO_INCREMENT,
  `id_veh_prog` int(11) NOT NULL,
  `id_via_prog` int(11) NOT NULL,
  PRIMARY KEY (`id_prog`),
  KEY `fk_programacion_vehiculo` (`id_veh_prog`),
  KEY `fk_programacion_viaje` (`id_via_prog`),
  CONSTRAINT `fk_programacion_vehiculo` FOREIGN KEY (`id_veh_prog`) REFERENCES `vehiculo` (`id_veh`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_programacion_viaje` FOREIGN KEY (`id_via_prog`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programacion`
--

LOCK TABLES `programacion` WRITE;
/*!40000 ALTER TABLE `programacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `programacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_pasajeros`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_pasajeros` (
  `id_rep` int(11) NOT NULL AUTO_INCREMENT,
  `id_usu_rep` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'Abierto',
  `id_via_rep` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rep`),
  KEY `fk_reportes_usuario` (`id_usu_rep`),
  KEY `fk_reportes_viaje` (`id_via_rep`),
  KEY `idx_reportes_estado_fecha` (`estado`,`fecha`),
  CONSTRAINT `fk_reportes_usuario` FOREIGN KEY (`id_usu_rep`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reportes_viaje` FOREIGN KEY (`id_via_rep`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_pasajeros`
--

LOCK TABLES `reportes_pasajeros` WRITE;
/*!40000 ALTER TABLE `reportes_pasajeros` DISABLE KEYS */;
/*!40000 ALTER TABLE `reportes_pasajeros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reserva`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reserva` (
  `id_res` int(11) NOT NULL AUTO_INCREMENT,
  `id_via_res` int(11) NOT NULL,
  `id_usu_res` int(11) NOT NULL,
  `fech_res` timestamp NOT NULL DEFAULT current_timestamp(),
  `metodo_pago` varchar(50) NOT NULL DEFAULT 'Por definir',
  `valor_pagado` decimal(10,2) DEFAULT NULL,
  `estado_pago` enum('Pendiente','Confirmada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `fecha_pago` timestamp NULL DEFAULT NULL,
  `embarco` tinyint(1) DEFAULT NULL COMMENT 'NULL=sin decidir · 1=embarcó · 0=no se presentó',
  `embarque_por` int(11) DEFAULT NULL COMMENT 'Conductor o admin que registró el embarque',
  `embarque_fec` datetime DEFAULT NULL COMMENT 'Momento en que se registró el embarque',
  `motivo_cancelacion` varchar(120) DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `fec_cancelacion` datetime DEFAULT NULL,
  `aviso_viaje_perdido` tinyint(1) NOT NULL DEFAULT 0,
  `es_temporal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Reserva creada por el conductor durante el viaje',
  `punto_abordaje` varchar(120) DEFAULT NULL,
  `destino_abordaje` varchar(120) DEFAULT NULL,
  `registrada_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_res`),
  KEY `fk_reserva_viaje` (`id_via_res`),
  KEY `fk_reserva_usuario` (`id_usu_res`),
  KEY `ix_reserva_viaje_estado` (`id_via_res`,`estado_pago`),
  KEY `ix_reserva_usuario_viaje` (`id_usu_res`,`id_via_res`),
  KEY `ix_reserva_usuario_estado_fecha` (`id_usu_res`,`estado_pago`,`fech_res`),
  KEY `ix_reserva_temporal_viaje` (`id_via_res`,`es_temporal`),
  KEY `ix_reserva_embarco` (`embarco`),
  KEY `fk_reserva_cancelador` (`cancelado_por`),
  CONSTRAINT `fk_reserva_cancelador` FOREIGN KEY (`cancelado_por`) REFERENCES `usuario` (`id_usu`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reserva_usuario` FOREIGN KEY (`id_usu_res`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reserva_viaje` FOREIGN KEY (`id_via_res`) REFERENCES `viaje` (`id_via`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=186 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reserva`
--

LOCK TABLES `reserva` WRITE;
/*!40000 ALTER TABLE `reserva` DISABLE KEYS */;
INSERT INTO `reserva` VALUES (18,28,21,'2026-09-27 02:46:40','Efectivo al Abordar',3500.00,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(19,28,21,'2026-09-27 02:46:40','Efectivo al Abordar',3500.00,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(20,28,21,'2026-09-27 02:47:02','Efectivo al Abordar',3500.00,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(22,31,21,'2026-09-28 12:13:38','Efectivo al Abordar',3500.00,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(23,31,21,'2026-09-28 12:13:38','Efectivo al Abordar',3500.00,'Pendiente',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0),(67,34,21,'2026-09-28 14:01:03','Efectivo',3500.00,'Cancelada',NULL,0,NULL,NULL,'Cancelada antes de este registro',NULL,'2026-09-29 08:56:40',0),(144,41,21,'2026-09-29 14:28:07','Efectivo',3500.00,'Confirmada','2026-09-29 14:28:07',NULL,NULL,NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `reserva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restricciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restricciones` (
  `id_res` int(11) NOT NULL AUTO_INCREMENT,
  `id_usu` int(11) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  PRIMARY KEY (`id_res`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restricciones`
--

LOCK TABLES `restricciones` WRITE;
/*!40000 ALTER TABLE `restricciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `restricciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nom_rol` varchar(100) NOT NULL,
  PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES (1,'Administrador'),(2,'Conductor'),(3,'Pasajero');
/*!40000 ALTER TABLE `rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_permiso`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol_permiso` (
  `id_rol` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL,
  PRIMARY KEY (`id_rol`,`id_permiso`),
  KEY `fk_rol_permiso_permiso` (`id_permiso`),
  CONSTRAINT `fk_rol_permiso_permiso` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rol_permiso_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_permiso`
--

LOCK TABLES `rol_permiso` WRITE;
/*!40000 ALTER TABLE `rol_permiso` DISABLE KEYS */;
INSERT INTO `rol_permiso` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,63),(2,4),(2,5),(2,17),(2,39),(2,40),(2,41),(2,63),(3,7),(3,8),(3,17),(3,37),(3,38),(3,39);
/*!40000 ALTER TABLE `rol_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rutas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rutas` (
  `id_rut` int(11) NOT NULL AUTO_INCREMENT,
  `nom_rut` varchar(100) NOT NULL,
  `dis_rut` decimal(10,2) NOT NULL DEFAULT 0.00,
  `val_rut` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ori_rut` varchar(100) NOT NULL DEFAULT 'Por definir',
  `des_rut` varchar(100) NOT NULL DEFAULT 'Por definir',
  `img_rut` varchar(255) DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `duracion_min` int(11) DEFAULT NULL COMMENT 'Duración estimada del trayecto en minutos',
  PRIMARY KEY (`id_rut`)
) ENGINE=InnoDB AUTO_INCREMENT=901 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rutas`
--

LOCK TABLES `rutas` WRITE;
/*!40000 ALTER TABLE `rutas` DISABLE KEYS */;
INSERT INTO `rutas` VALUES (1,'Silvania-Fusagasuga',10.00,3500.00,'Silvania','Fusagasuga','ruta_1790477095_1b7782e3.jpg',NULL,1,30),(900,'PruebaConc',0.00,0.00,'A','B',NULL,NULL,1,60);
/*!40000 ALTER TABLE `rutas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sget_logs_auditoria`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sget_logs_auditoria` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_usu` int(11) DEFAULT NULL,
  `nom_usu_log` varchar(100) DEFAULT NULL,
  `nom_rol_log` varchar(100) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `descripcion` text NOT NULL,
  `ip_origen` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `fec_log` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `fk_logs_usuario` (`id_usu`),
  CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`id_usu`) REFERENCES `usuario` (`id_usu`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=491 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sget_logs_auditoria`
--

LOCK TABLES `sget_logs_auditoria` WRITE;
/*!40000 ALTER TABLE `sget_logs_auditoria` DISABLE KEYS */;
INSERT INTO `sget_logs_auditoria` VALUES (1,2,'Kevin Hernández','Sin Rol','LOGOUT','El usuario \'Kevin Hernández\' cerró su sesión en SGET.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 12:49:45'),(2,3,'Derrick  Mendoza','Pasajero','CREAR_USUARIO','Auto-registro de nuevo Pasajero: \'Derrick  Mendoza\' (Doc: 110648).','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 12:50:27'),(3,NULL,'Anónimo / Sistema','Sin Rol','LOGIN_FALLIDO','Intento de ingreso con documento no registrado: 106972.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 12:50:37'),(4,NULL,'Anónimo / Sistema','Sin Rol','LOGIN_FALLIDO','Intento de ingreso con documento no registrado: 106972.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 12:50:49'),(5,3,'Derrick  Mendoza','Pasajero','LOGIN','El usuario \'Derrick  Mendoza\' (Doc: 110648) inició sesión exitosamente.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 12:51:12');
/*!40000 ALTER TABLE `sget_logs_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usu` int(11) NOT NULL AUTO_INCREMENT,
  `tip_doc_usu` varchar(10) NOT NULL,
  `num_doc_usu` varchar(20) NOT NULL,
  `nom_usu` varchar(100) NOT NULL,
  `corre_usu` varchar(100) NOT NULL,
  `tel_usu` varchar(20) DEFAULT NULL,
  `id_rol_usu` int(11) NOT NULL,
  `pass_usu` varchar(200) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `est_con_usu` int(11) DEFAULT NULL COMMENT '1 = Disponible, 0 = Ocupado. NULL para otros roles.',
  `google_id` varchar(255) DEFAULT NULL,
  `acepta_politica` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_acepta_politica` datetime DEFAULT NULL,
  `restricciones` text DEFAULT NULL,
  PRIMARY KEY (`id_usu`),
  UNIQUE KEY `uq_usuario_documento` (`num_doc_usu`),
  UNIQUE KEY `uq_usuario_correo` (`corre_usu`),
  UNIQUE KEY `uq_usuario_google` (`google_id`),
  KEY `fk_usuario_rol` (`id_rol_usu`),
  KEY `idx_usuario_rol_estado` (`id_rol_usu`,`estado`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol_usu`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'CC','107249','Administrador Principal','admin@sget.com','3000000000',1,'$2y$10$5KS5emk4zf9svcudBAjfPeX7NrlGRJSpTt1QnGTDxo73np50tWuPy',1,NULL,NULL,0,NULL,NULL),(2,'','G-393841','Kevin Hernández','kh0342349@gmail.com',NULL,1,'$2y$10$rwiuxHsIVP3dlxZApS0EMOUEFhmqY1i5ADptBAVoSXJFwRwHV6eZu',1,NULL,'106864215924894393841',0,NULL,NULL),(3,'CC','110648','Derrick  Mendoza','df@m.com',NULL,2,'$2y$10$saZlgqUuACpTyKPvobT0WeSjKFH7qd9Li6o4ng7bmGYv.eHVtMzAW',1,1,NULL,1,'2026-09-24 14:50:27',NULL),(21,'CC','123456','Pepe','pp@m.com',NULL,3,'$2y$10$8Xw.dkYG/LdH7GnPLkTgcuf8nVgAg32X2auZZrcQTII0opasF/TcG',1,NULL,NULL,1,'2026-09-26 20:20:14',NULL),(22,'CC','900000001','Administrador de Prueba','admin@sget.local',NULL,1,'$2y$10$tJmVhMKxex097Op65LHxqO8Ojced2gBEWvQXwF/TGANyhbFFkfRI.',1,NULL,NULL,1,'2026-10-02 20:02:56',NULL),(23,'CC','900000002','Conductor de Prueba','conductor@sget.local',NULL,2,'$2y$10$ZxTPXpNBFWwfJWeQP4vlie09/LSfkeDxL8Zp96GPBguECxZJ9m/q.',1,1,NULL,1,'2026-10-02 20:02:57',NULL),(24,'CC','900000003','Pasajero de Prueba','pasajero@sget.local',NULL,3,'$2y$10$cmcbcEIb6HnhwWlzdLi.5egH7cQqO6mFtYZ3csFw7M4/F2jEsdKIW',1,NULL,NULL,1,'2026-10-02 20:02:57',NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_permiso_denegado`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_permiso_denegado` (
  `id_usu` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL,
  PRIMARY KEY (`id_usu`,`id_permiso`),
  KEY `fk_denegado_permiso` (`id_permiso`),
  CONSTRAINT `fk_denegado_permiso` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_denegado_usuario` FOREIGN KEY (`id_usu`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_permiso_denegado`
--

LOCK TABLES `usuario_permiso_denegado` WRITE;
/*!40000 ALTER TABLE `usuario_permiso_denegado` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_permiso_denegado` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_permisos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_permisos` (
  `id_usu` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usu`,`id_permiso`),
  KEY `fk_permiso_catalogo` (`id_permiso`),
  CONSTRAINT `fk_permiso_catalogo` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_permiso_usuario` FOREIGN KEY (`id_usu`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_permisos`
--

LOCK TABLES `usuario_permisos` WRITE;
/*!40000 ALTER TABLE `usuario_permisos` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehiculo`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehiculo` (
  `id_veh` int(11) NOT NULL AUTO_INCREMENT,
  `pla_veh` varchar(10) NOT NULL,
  `mode_veh` varchar(50) NOT NULL DEFAULT 'Sin modelo',
  `cap_veh` int(11) NOT NULL DEFAULT 0,
  `est_veh` enum('Disponible','Asignado','Mantenimiento','Fuera de servicio') NOT NULL DEFAULT 'Disponible' COMMENT 'Estado operativo de la unidad',
  PRIMARY KEY (`id_veh`),
  KEY `idx_vehiculo_estado` (`est_veh`)
) ENGINE=InnoDB AUTO_INCREMENT=901 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehiculo`
--

LOCK TABLES `vehiculo` WRITE;
/*!40000 ALTER TABLE `vehiculo` DISABLE KEYS */;
INSERT INTO `vehiculo` VALUES (1,'ABC123','Toyota',23,'Fuera de servicio');
/*!40000 ALTER TABLE `vehiculo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viaje`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `viaje` (
  `id_via` int(11) NOT NULL AUTO_INCREMENT,
  `nom_via` varchar(100) DEFAULT NULL,
  `fec_via` date NOT NULL,
  `hor_sal_via` time NOT NULL,
  `hor_lleg_via` time DEFAULT NULL,
  `val_via` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_rut_via` int(11) NOT NULL,
  `id_usu_via` int(11) NOT NULL,
  `est_via` enum('Programado','En curso','Finalizado','Cancelado') NOT NULL DEFAULT 'Programado',
  `id_veh` int(11) DEFAULT NULL,
  `cup_tot` int(11) NOT NULL DEFAULT 0,
  `cup_dis` int(11) NOT NULL DEFAULT 0,
  `salio` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_cancelacion` varchar(60) DEFAULT NULL,
  `anotacion_cancelacion` text DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `fec_cancelacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_via`),
  KEY `fk_viaje_rutas` (`id_rut_via`),
  KEY `fk_viaje_usuario` (`id_usu_via`),
  KEY `fk_viaje_vehiculo` (`id_veh`),
  KEY `idx_viaje_salida` (`est_via`,`fec_via`,`hor_sal_via`),
  KEY `idx_viaje_ruta_estado` (`id_rut_via`,`est_via`,`fec_via`),
  KEY `idx_viaje_conductor` (`id_usu_via`,`est_via`,`fec_via`),
  CONSTRAINT `fk_viaje_rutas` FOREIGN KEY (`id_rut_via`) REFERENCES `rutas` (`id_rut`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_viaje_usuario` FOREIGN KEY (`id_usu_via`) REFERENCES `usuario` (`id_usu`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_viaje_vehiculo` FOREIGN KEY (`id_veh`) REFERENCES `vehiculo` (`id_veh`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viaje`
--

LOCK TABLES `viaje` WRITE;
/*!40000 ALTER TABLE `viaje` DISABLE KEYS */;
INSERT INTO `viaje` VALUES (1,'Viaje #1','2026-09-26','07:00:00',NULL,3500.00,1,3,'Finalizado',1,0,0,1,NULL,NULL,NULL,NULL),(28,'Viaje #2','2026-09-28','06:00:00',NULL,3500.00,1,3,'Finalizado',1,23,23,1,NULL,NULL,NULL,NULL),(31,'Viaje #29','2026-09-29','10:30:00',NULL,3500.00,1,3,'Finalizado',1,23,23,1,NULL,NULL,NULL,NULL),(32,'Viaje #32','2026-09-29','06:00:00',NULL,3500.00,1,3,'Finalizado',1,23,23,1,NULL,NULL,NULL,NULL),(33,'Viaje #33','2026-09-29','06:00:00',NULL,3500.00,1,3,'Cancelado',1,23,23,1,'falta_conductor','Conductor con incapasidad',2,'2026-09-28 08:28:59'),(34,'PRUEBA RECAUDO 2026-09-28 08:30','2026-09-29','06:00:00',NULL,3500.00,1,3,'Finalizado',1,25,23,1,NULL,NULL,NULL,NULL),(41,'PRUEBA RECAUDO 2026-09-29 06:47','2026-09-30','06:00:00',NULL,3500.00,1,3,'Finalizado',1,25,24,1,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `viaje` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
