USE reciclaje;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Limpiar todo ordenado por FK
DELETE FROM pagos;
DELETE FROM cobros;
DELETE FROM recaudaciones;
DELETE FROM recibos_finiquito;
DELETE FROM lotes_calle;
DELETE FROM lotes_barrio;
DELETE FROM solicitudes_vivienda;
DELETE FROM reportes;
DELETE FROM solicitudes_servicio;
DELETE FROM empresas;
DELETE FROM exenciones_cobro;
DELETE FROM configuraciones_barrio;
DELETE FROM detalles_encargado_calle;
DELETE FROM detalles_encargado_barrio;
DELETE FROM detalles_gestor;
DELETE FROM detalles_personal_obrero;
DELETE FROM viviendas;
DELETE FROM calles;
DELETE FROM barrios;
DELETE FROM usuarios WHERE id NOT IN (1,2,4,5);
DELETE FROM roles WHERE id > 6;

ALTER TABLE pagos AUTO_INCREMENT = 1;
ALTER TABLE cobros AUTO_INCREMENT = 1;
ALTER TABLE recaudaciones AUTO_INCREMENT = 1;
ALTER TABLE recibos_finiquito AUTO_INCREMENT = 1;
ALTER TABLE lotes_calle AUTO_INCREMENT = 1;
ALTER TABLE lotes_barrio AUTO_INCREMENT = 1;
ALTER TABLE solicitudes_vivienda AUTO_INCREMENT = 1;
ALTER TABLE reportes AUTO_INCREMENT = 1;
ALTER TABLE solicitudes_servicio AUTO_INCREMENT = 1;
ALTER TABLE empresas AUTO_INCREMENT = 1;
ALTER TABLE exenciones_cobro AUTO_INCREMENT = 1;
ALTER TABLE configuraciones_barrio AUTO_INCREMENT = 1;
ALTER TABLE detalles_encargado_calle AUTO_INCREMENT = 1;
ALTER TABLE detalles_encargado_barrio AUTO_INCREMENT = 1;
ALTER TABLE detalles_gestor AUTO_INCREMENT = 1;
ALTER TABLE detalles_personal_obrero AUTO_INCREMENT = 1;
ALTER TABLE viviendas AUTO_INCREMENT = 1;
ALTER TABLE calles AUTO_INCREMENT = 1;
ALTER TABLE barrios AUTO_INCREMENT = 1;
ALTER TABLE usuarios AUTO_INCREMENT = 6;

-- Barrios
INSERT INTO barrios (nombre, ciudad, codigo_postal) VALUES
('Barrio San Blas', '', '08003'),
('Santa Ana', '', '08004'),
('Wanchaq', '', '08006'),
('Santiago', '', '08001');

-- Calles
INSERT INTO calles (nombre, barrio_id) VALUES
('Calle Hatun Rumiyoc', 1),
('Calle Carmen Alto', 1),
('Calle Tandapata', 1),
('Calle Principal', 2),
('Av. El Sol', 3),
('Jr. Saphy', 3),
('Calle Suecia', 4),
('Av. De la Cultura', 4);

-- Usuarios adicionales
INSERT INTO usuarios (nombre, apellido, email, genero, fecha_nacimiento, password_hash, rol_id, creado_en) VALUES
('Carlos', 'Mamani', 'recolector2@gmail.com', 'M', '1990-05-12', '$2y$10$cGTJCOs8F3urGLa.nsuCweiQ3EomwGo3nc0ZmlKfWcUaUUFcUeCom', 6, NOW()),
('María', 'Flores', 'encargadacalle2@gmail.com', 'F', '1988-09-20', '$2y$10$cGTJCOs8F3urGLa.nsuCweiQ3EomwGo3nc0ZmlKfWcUaUUFcUeCom', 6, NOW()),
('Luis', 'Condori', 'encargadobarrio2@gmail.com', 'M', '1985-03-15', '$2y$10$cGTJCOs8F3urGLa.nsuCweiQ3EomwGo3nc0ZmlKfWcUaUUFcUeCom', 5, NOW()),
('Ana', 'Quispe', 'gestor2@gmail.com', 'F', '1987-11-08', '$2y$10$cGTJCOs8F3urGLa.nsuCweiQ3EomwGo3nc0ZmlKfWcUaUUFcUeCom', 2, NOW()),
('Pedro', 'Huamán', 'recolector3@gmail.com', 'M', '1992-07-25', '$2y$10$cGTJCOs8F3urGLa.nsuCweiQ3EomwGo3nc0ZmlKfWcUaUUFcUeCom', 3, NOW());

-- Detalles personal
INSERT INTO detalles_personal_obrero (usuario_id, cargo, dni, telefono, turno, contacto_emergencia) VALUES
(4, 'Recolector', '70856234', '987654321', 'Mañana', 'Rosa Calle - 987111222'),
(6, 'Recolector', '71234568', '986543210', 'Tarde', 'María Mamani - 986222333'),
(10, 'Recolector', '72345679', '985432109', 'Mañana', 'José Huamán - 985333444');

-- Detalles gestor
INSERT INTO detalles_gestor (usuario_id, dni, telefono, area) VALUES
(2, '45632187', '976543210', 'Recaudación y Control Financiero'),
(9, '46789213', '975432109', 'Supervisión y Auditoría');

-- Encargados calle/barrio
INSERT INTO detalles_encargado_calle (usuario_id, calle_id, dni, telefono) VALUES
(4, 1, '70856234', '987654321'),
(7, 4, '71258394', '986475832'),
(8, 6, '72369485', '985364721');

INSERT INTO detalles_encargado_barrio (usuario_id, barrio_id, dni, telefono, direccion) VALUES
(5, 2, '71582634', '984726351', 'Av. El Sol 123'),
(9, 1, '72693745', '983615842', 'Calle Hatun Rumiyoc 456');

-- Config barrios
INSERT INTO configuraciones_barrio (barrio_id, cuota_mensual, multa_renovacion, actualizado_por) VALUES
(1,12.00,6.00,1),(2,10.00,5.00,1),(3,10.00,5.00,1),(4,12.00,6.00,1);

-- Viviendas
INSERT INTO viviendas (barrio_id, calle_id, propietario, cedula, telefono, direccion, numero_casa, referencia, estado_servicio) VALUES
(1,1,'Familia Yana Ccahuana','45678912','984512367','Calle Hatun Rumiyoc','105','Casa con rejas verdes','Activo'),
(1,1,'Familia Puma Quispe','46781293','983621478','Calle Hatun Rumiyoc','107','Frente a parque pequeño','Activo'),
(1,2,'Familia Condori Mamani','47892314','982734561','Calle Carmen Alto','214','Casa de dos pisos','Activo'),
(1,3,'Familia Huamán Flores','48903425','981845672','Calle Tandapata','328','Con jardín delantero','Activo'),
(2,4,'Familia Silba','49014536','980956783','Calle Principal','312','Casa color amarillo','Activo'),
(2,4,'Familia Valero','50125647','989067894','Calle Principal','321','','Activo'),
(2,4,'Familia Moreno','51236758','988178905','Calle Principal','343','','Activo'),
(2,4,'Family uzcategui','52347869','987289016','Calle Principal','432','','Activo'),
(3,5,'Familia Cárdenas Loaiza','53458970','986390127','Av. El Sol','156','Esquina con farmacia','Activo'),
(3,6,'Familia Sánchez Yana','54569081','985401238','Jr. Saphy','243','Casa colonial','Activo'),
(4,7,'Familia Quispe Ccahuana','55670192','984512349','Calle Suecia','87','Con garaje','Activo'),
(4,8,'Familia Ticona Puma','56781203','983623450','Av. De la Cultura','542','Con cochera','Activo'),
(1,2,'Familia Apaza Mamani','57892314','982734561','Calle Carmen Alto','216','Segundo piso','Suspendido');

-- Cobros
INSERT INTO cobros (vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento, estado, tipo_cobro) VALUES
(1,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(2,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(3,7,2026,12.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(4,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(5,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(6,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(7,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(8,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(9,7,2026,10.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(10,7,2026,10.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(11,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(12,7,2026,12.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(13,7,2026,12.00,'2026-07-01','2026-07-31','Vencido','Servicio'),
(1,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(5,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio');
COMMIT;
