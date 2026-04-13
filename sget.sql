CREATE DATABASE SGET;
USE SGET;

CREATE TABLE rol(
    id_rol int PRIMARY KEY AUTO_INCREMENT,
    nom_rol varchar(100) not null
);

CREATE TABLE usuario(
    id_usu int PRIMARY KEY AUTO_INCREMENT, 
    num_doc_usu varchar(20) not null,
    tip_doc_usu varchar(20) not null,
    nom_usu varchar(100) not null,
    corre_usu varchar(100) null,
    tel_usu varchar(20) not null,
    id_rol_usu int not null,
    pass_usu varchar(255) not null
);

CREATE TABLE vehiculo(
    id_veh int PRIMARY KEY AUTO_INCREMENT,
    pla_veh varchar(7) not null,
    mode_veh varchar(50) not null,
    cap_veh int not null
);

CREATE TABLE asignacion( 
    id_asig int PRIMARY KEY AUTO_INCREMENT,
    id_usu_asig int not null,
    id_veh_asig int not null
);

CREATE TABLE rutas(
    id_rut int PRIMARY KEY AUTO_INCREMENT,
    nom_rut varchar(100) not null,
    dis_rut decimal(10,2) not null, 
    ori_rut varchar(100) not null,
    des_rut varchar(100) not null
);

CREATE TABLE viaje(
    id_via int PRIMARY KEY AUTO_INCREMENT,
    nom_via varchar(100) not null,
    fec_via datetime DEFAULT CURRENT_TIMESTAMP,
    hor_sal_via datetime null,
    hor_lleg_via datetime null,
    val_via FLOAT not null, 
    id_rut_via int not null,
    id_usu_via int not null
);

CREATE TABLE programacion(
    id_prog int PRIMARY KEY AUTO_INCREMENT,
    id_veh_prog int not null,
    id_via_prog int not null
);

ALTER TABLE usuario ADD CONSTRAINT FK_id_rol_usu 
FOREIGN KEY (id_rol_usu) REFERENCES rol (id_rol);

ALTER TABLE asignacion ADD CONSTRAINT FK_id_usu_asig 
FOREIGN KEY (id_usu_asig) REFERENCES usuario (id_usu);

ALTER TABLE asignacion ADD CONSTRAINT FK_id_veh_asig 
FOREIGN KEY (id_veh_asig) REFERENCES vehiculo (id_veh);

ALTER TABLE programacion ADD CONSTRAINT FK_id_veh_prog 
FOREIGN KEY (id_veh_prog) REFERENCES vehiculo (id_veh);

ALTER TABLE programacion ADD CONSTRAINT FK_id_via_prog 
FOREIGN KEY (id_via_prog) REFERENCES viaje (id_via);

ALTER TABLE viaje ADD CONSTRAINT FK_id_rut_via 
FOREIGN KEY (id_rut_via) REFERENCES rutas (id_rut);

ALTER TABLE viaje ADD CONSTRAINT FK_id_usu_via 
FOREIGN KEY (id_usu_via) REFERENCES usuario (id_usu);

ALTER TABLE usuario 
ADD COLUMN estado TINYINT(1) DEFAULT 1;

ALTER TABLE viajes 
ADD COLUMN fec_sal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN fec_lleg DATETIME NULL,
ADD COLUMN est_via VARCHAR(20) DEFAULT 'Activo';

ALTER TABLE usuario
ADD COLUMN est_con_usu TINYINT(1) DEFAULT 0;

ALTER TABLE rutas ADD COLUMN val_rut FLOAT NOT NULL AFTER dis_rut;

ALTER TABLE vehiculo ADD COLUMN est_veh TINYINT(1) DEFAULT 1;