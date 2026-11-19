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
ALTER TABLE roles AUTO_INCREMENT = 7;

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

-- Encargados de calle/barrio
INSERT INTO detalles_encargado_calle (usuario_id, calle_id, dni, telefono) VALUES
(4, 1, '70856234', '987654321'),
(7, 4, '71258394', '986475832'),
(8, 6, '72369485', '985364721');

INSERT INTO detalles_encargado_barrio (usuario_id, barrio_id, dni, telefono, direccion) VALUES
(5, 2, '71582634', '984726351', 'Av. El Sol 123'),
(9, 1, '72693745', '983615842', 'Calle Hatun Rumiyoc 456');

-- Configuraciones barrio
INSERT INTO configuraciones_barrio (barrio_id, cuota_mensual, multa_renovacion, actualizado_por) VALUES
(1, 12.00, 6.00, 1),
(2, 10.00, 5.00, 1),
(3, 10.00, 5.00, 1),
(4, 12.00, 6.00, 1);

-- Viviendas
INSERT INTO viviendas (barrio_id, calle_id, propietario, cedula, telefono, direccion, numero_casa, referencia, estado_servicio) VALUES
(1, 1, 'Familia Yana Ccahuana', '45678912', '984512367', 'Calle Hatun Rumiyoc', '105', 'Casa con rejas verdes', 'Activo'),
(1, 1, 'Familia Puma Quispe', '46781293', '983621478', 'Calle Hatun Rumiyoc', '107', 'Frente a parque pequeño', 'Activo'),
(1, 2, 'Familia Condori Mamani', '47892314', '982734561', 'Calle Carmen Alto', '214', 'Casa de dos pisos', 'Activo'),
(1, 3, 'Familia Huamán Flores', '48903425', '981845672', 'Calle Tandapata', '328', 'Con jardín delantero', 'Activo'),
(2, 4, 'Familia Silba', '49014536', '980956783', 'Calle Principal', '312', 'Casa color amarillo', 'Activo'),
(2, 4, 'Familia Valero', '50125647', '989067894', 'Calle Principal', '321', '', 'Activo'),
(2, 4, 'Familia Moreno', '51236758', '988178905', 'Calle Principal', '343', '', 'Activo'),
(2, 4, 'Family uzcategui', '52347869', '987289016', 'Calle Principal', '432', '', 'Activo'),
(3, 5, 'Familia Cárdenas Loaiza', '53458970', '986390127', 'Av. El Sol', '156', 'Esquina con farmacia', 'Activo'),
(3, 6, 'Familia Sánchez Yana', '54569081', '985401238', 'Jr. Saphy', '243', 'Casa colonial', 'Activo'),
(4, 7, 'Familia Quispe Ccahuana', '55670192', '984512349', 'Calle Suecia', '87', 'Con garaje', 'Activo'),
(4, 8, 'Familia Ticona Puma', '56781203', '983623450', 'Av. De la Cultura', '542', 'Con cochera', 'Activo'),
(1, 2, 'Familia Apaza Mamani', '57892314', '982734561', 'Calle Carmen Alto', '216', 'Segundo piso', 'Suspendido');

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

-- Exenciones
INSERT INTO exenciones_cobro (vivienda_id, motivo, fecha_inicio, fecha_fin, estado, autorizado_por, observaciones) VALUES
(13, 'Situación económica vulnerable', '2026-07-01', '2026-12-31', 'Activa', 1, 'Exención temporal aprobada por administración');

-- Empresas
INSERT INTO empresas (nombre, descripcion, contacto, materiales) VALUES
('EcoReciclaje S.A.C.', 'Empresa dedicada al acopio, clasificación y comercialización de residuos reciclables.', '987654321 / ventas@ecoreciclaje.com', 'Plástico PET, Cartón, Papel, Vidrio, Aluminio'),
('GreenAndes SRL', 'Centro de acopio certificado con enfoque en economía circular.', '984123456 / contacto@greenandes.pe', 'Metal, Chatarra, Vidrio, Plástico industrial'),
('Andes Circular', 'Aliado estratégico para reciclaje electrónico y gestión responsable.', '976543210 / operaciones@andescircular.pe', 'RAEE, Baterías, Cables, Equipos informáticos'),
('ReciclaVerde EIRL', 'Cooperativa de recicladores con enfoque social inclusivo.', '975432108 / info@reciclaverde.pe', 'Cartón, Papel, Plástico, Aluminio');

-- Reportes
INSERT INTO reportes (usuario_id, ubicacion_nombre, latitud, longitud, tipo_residuo, descripcion, cantidad, estado, fotos, fecha_reporte) VALUES
(4, 'Calle Principal - Santa Ana', -13.51800000, -71.97800000, 'Plástico PET', 'Acumulación de botellas plásticas junto al contenedor comunitario.', 15.50, 'Completado', 'uploads/reportes/reporte_001.jpg', '2026-07-01 09:15:00'),
(5, 'Av. El Sol - Wanchaq', -13.52250000, -71.96780000, 'Cartón', 'Cajas de cartón apiladas tras mudanza, requieren recolección.', 28.00, 'En proceso', 'uploads/reportes/reporte_002.jpg', '2026-07-02 11:20:00'),
(NULL, 'Plaza San Blas', -13.51580000, -71.97720000, 'Vidrio', 'Botellas y frascos de vidrio rotos en área pública.', 12.25, 'Pendiente', 'uploads/reportes/reporte_003.jpg', '2026-07-03 14:45:00'),
(4, 'Urb. Santa Ana Mz A', -13.52010000, -71.97050000, 'Papel', 'Periódicos y papel archivo para reciclaje.', 9.75, 'Completado', 'uploads/reportes/reporte_004.jpg', '2026-07-04 08:30:00'),
(5, 'Calle Carmen Alto - San Blas', -13.51490000, -71.97910000, 'Aluminio', 'Latas de aluminio almacenadas para acopio.', 6.40, 'En proceso', 'uploads/reportes/reporte_005.jpg', '2026-07-05 10:10:00'),
(NULL, 'Mercado Wanchaq', -13.52530000, -71.96590000, 'Orgánico', 'Residuos orgánicos separados para compostaje comunitario.', 35.80, 'Pendiente', 'uploads/reportes/reporte_006.jpg', '2026-07-06 07:55:00'),
(6, 'Pasaje Hatun Rumiyoc - San Blas', -13.51620000, -71.97860000, 'Plástico HDPE', 'Envases plásticos limpios listos para recolección.', 14.30, 'Completado', 'uploads/reportes/reporte_007.jpg', '2026-07-07 13:25:00'),
(7, 'Jr. Saphy - Wanchaq', -13.52380000, -71.96920000, 'Tetra Pak', 'Envases Tetra Pak acumulados en punto limpio.', 11.20, 'En proceso', 'uploads/reportes/reporte_008.jpg', '2026-07-08 15:40:00'),
(NULL, 'Calle Tandapata - San Blas', -13.51450000, -71.98000000, 'Vidrio', 'Frascos de vidrio limpios separados por color.', 16.90, 'Completado', 'uploads/reportes/reporte_009.jpg', '2026-07-09 09:50:00'),
(8, 'Av. Cultural - Santiago', -13.51970000, -71.97210000, 'Cartón', 'Cartón corrugado de comercios para reciclaje.', 42.60, 'En proceso', 'uploads/reportes/reporte_010.jpg', '2026-07-10 12:15:00'),
(NULL, 'Parque San Blas', -13.51520000, -71.97790000, 'Plástico PET', 'Botellas recolectadas en jornada de voluntariado.', 19.40, 'Pendiente', 'uploads/reportes/reporte_011.jpg', '2026-07-11 16:05:00'),
(10, 'Calle Ruinas - Wanchaq', -13.52460000, -71.96680000, 'Metal', 'Piezas metálicas pequeñas para acopio.', 8.75, 'Completado', 'uploads/reportes/reporte_012.jpg', '2026-07-12 08:40:00'),
(6, 'Av. El Sol - Wanchaq', -13.52280000, -71.96750000, 'Papel', 'Resma de papel para reciclaje.', 7.25, 'Pendiente', 'uploads/reportes/reporte_013.jpg', '2026-07-13 10:30:00'),
(NULL, 'Calle Carmen Alto - San Blas', -13.51500000, -71.97890000, 'Vidrio', 'Frascos retornables separados.', 13.60, 'En proceso', 'uploads/reportes/reporte_014.jpg', '2026-07-14 14:20:00');

-- Solicitudes servicio
INSERT INTO solicitudes_servicio (representante, telefono, barrio, tipo_contrato, correo, estado, fecha) VALUES
('María Quispe Ccahuana', '984562871', 'Barrio San Blas', 'Contrato Anual', 'maria.quispe@gmail.com', 'Pendiente', '2026-07-08 10:22:00'),
('José Condori Mamani', '976843215', 'Santa Ana', 'Contrato Semestral', 'jose.condori@hotmail.com', 'Atendido', '2026-07-09 14:18:00'),
('Rosa Huamán Flores', '987326548', 'Wanchaq', 'Contrato Anual', 'rosa.huaman@yahoo.com', 'Pendiente', '2026-07-10 09:35:00'),
('Carlos Sánchez Yana', '965874123', 'Barrio San Blas', 'Contrato Trimestral', 'carlos.sanchez@gmail.com', 'Atendido', '2026-07-11 11:47:00'),
('Lucía Puma Ticona', '974562398', 'Santa Ana', 'Contrato Anual', 'lucia.puma@gmail.com', 'Descartado', '2026-07-12 16:12:00'),
('Edgar Cárdenas Loaiza', '986742159', 'Wanchaq', 'Contrato Anual', 'edgar.cardenas@gmail.com', 'Pendiente', '2026-07-13 08:54:00'),
('Juana Apaza Mamani', '975863421', 'Santiago', 'Contrato Anual', 'juana.apaza@gmail.com', 'Pendiente', '2026-07-14 12:26:00'),
('Fernando Yana Ccahuana', '974752318', 'Barrio San Blas', 'Contrato Semestral', 'fernando.yana@gmail.com', 'Atendido', '2026-07-15 09:18:00');

-- Solicitudes vivienda
INSERT INTO solicitudes_vivienda (tipo, calle_id, vivienda_id, monto_deuda, detalles_deuda, propietario, cedula, numero_casa, referencia, estado, creado_por, revisado_por, fecha_creacion, fecha_revision) VALUES
('Alta', 1, NULL, 0.00, NULL, 'Familia Yana Ccahuana', '45678912', '105', 'Casa con rejas verdes', 'Aprobado', 4, 5, '2026-07-04 13:55:56', '2026-07-04 21:07:19'),
('Alta', 1, NULL, 0.00, NULL, 'Familia Puma Quispe', '46781293', '107', 'Frente a parque pequeño', 'Aprobado', 4, 5, '2026-07-04 21:42:58', '2026-07-04 21:43:06'),
('Alta', 2, NULL, 0.00, NULL, 'Familia Condori Mamani', '47892314', '214', 'Casa de dos pisos', 'Aprobado', 7, 5, '2026-07-05 10:15:00', '2026-07-05 12:32:47'),
('Alta', 3, NULL, 0.00, NULL, 'Familia Huamán Flores', '48903425', '328', 'Con jardín delantero', 'Aprobado', 7, 9, '2026-07-05 13:11:58', '2026-07-05 13:12:04'),
('Alta', 5, NULL, 0.00, NULL, 'Familia Cárdenas Loaiza', '53458970', '156', 'Esquina con farmacia', 'Pendiente', 8, NULL, '2026-07-12 14:20:00', NULL),
('Alta', 6, NULL, 0.00, NULL, 'Familia Sánchez Yana', '54569081', '243', 'Casa colonial', 'Pendiente', 8, NULL, '2026-07-13 09:35:00', NULL),
('Baja', 2, 13, 24.00, 'Dos meses de cuota pendientes', 'Familia Apaza Mamani', '57892314', '216', '', 'Pendiente', 7, NULL, '2026-07-14 11:42:00', NULL);

-- Recaudaciones
INSERT INTO recaudaciones (tipo, emisor_id, receptor_id, barrio_id, calle_id, monto_total, estado, fecha_recaudacion, comprobante, observaciones) VALUES
('Calle', 4, 2, 2, 4, 40.00, 'Verificado', '2026-07-05 14:25:00', 'comprobantes/rec_001.pdf', 'Recaudación mensual calle principal - Julio 2026'),
('Barrio', 5, 2, 2, NULL, 150.00, 'Verificado', '2026-07-06 10:40:00', 'comprobantes/rec_002.pdf', 'Consolidado mensual Santa Ana - Julio 2026'),
('Calle', 7, 2, 1, 1, 24.00, 'Verificado', '2026-07-07 15:20:00', 'comprobantes/rec_003.pdf', 'Recaudación Calle Hatun Rumiyoc - Julio 2026'),
('Calle', 8, 9, 1, 2, 12.00, 'Pendiente', '2026-07-08 11:15:00', 'comprobantes/rec_004.pdf', 'Recaudación Calle Carmen Alto - Julio 2026');

-- Lotes calle
INSERT INTO lotes_calle (calle_id, mes, anio, monto_recolectado, estado, observaciones, fecha_envio, fecha_evaluacion) VALUES
(4, 7, 2026, 40.00, 'Aprobado', 'Lote completo con todos los cobros verificados.', '2026-07-05 14:00:00', '2026-07-05 15:10:00'),
(1, 7, 2026, 24.00, 'Aprobado', 'Lote completo.', '2026-07-07 14:30:00', '2026-07-07 16:00:00'),
(2, 7, 2026, 12.00, 'Enviado', 'Pendiente de revisión por gestor.', '2026-07-08 10:45:00', NULL),
(3, 7, 2026, 12.00, 'Abierto', 'En proceso de recolección.', NULL, NULL);

-- Lotes barrio
INSERT INTO lotes_barrio (periodo_mes, periodo_anio, barrio_id, encargado_barrio_id, gestor_id, monto_total_esperado, monto_total_recolectado, total_calles, calles_completas, estado, alerta_deuda, comprobante_barrio, observaciones_barrio, observaciones_gestor, recibo_generado, fecha_creacion, fecha_envio, fecha_aprobacion) VALUES
(7, 2026, 2, 5, 2, 40.00, 40.00, 1, 1, 'Aprobado', 0, 'comprobantes/lote_barrio_001.pdf', 'Todas las calles enviaron sus lotes completos.', 'Lote revisado y aprobado sin observaciones.', 1, '2026-07-06 08:20:00', '2026-07-06 09:00:00', '2026-07-06 11:15:00'),
(7, 2026, 1, 9, 2, 48.00, 24.00, 3, 1, 'Enviado', 0, 'comprobantes/lote_barrio_002.pdf', 'Se envían lotes aprobados, falta calle Carmen Alto.', 'Pendiente de revisión.', 0, '2026-07-07 09:00:00', '2026-07-07 10:30:00', NULL);

-- Recibos finiquito
INSERT INTO recibos_finiquito (lote_barrio_id, barrio_id, periodo_mes, periodo_anio, monto_aprobado, generado_por, numero_recibo, fecha_emision) VALUES
(1, 2, 7, 2026, 40.00, 2, 'RF-2026-07-0001', '2026-07-06 11:30:00');

-- Pagos
INSERT INTO pagos (cobro_id, recolectado_por_id, gestor_id, monto_pagado, fecha_pago, metodo_pago, comprobante) VALUES
(1, 4, 2, 12.00, '2026-07-04 15:10:00', 'Efectivo', 'comprobantes/pago_001.jpg'),
(2, 4, 2, 12.00, '2026-07-04 16:25:00', 'Transferencia', 'comprobantes/pago_002.jpg'),
(4, 4, 2, 12.00, '2026-07-05 09:40:00', 'Efectivo', 'comprobantes/pago_003.jpg'),
(5, 4, 2, 10.00, '2026-07-04 15:10:00', 'Efectivo', 'comprobantes/pago_004.jpg'),
(6, 4, 2, 10.00, '2026-07-04 16:25:00', 'Transferencia', 'comprobantes/pago_005.jpg'),
(7, 4, 2, 10.00, '2026-07-05 09:40:00', 'Efectivo', 'comprobantes/pago_006.jpg'),
(8, 4, 2, 10.00, '2026-07-05 11:15:00', 'Efectivo', 'comprobantes/pago_007.jpg'),
(11, 6, 2, 12.00, '2026-07-06 10:20:00', 'Efectivo', 'comprobantes/pago_008.jpg'),
(14, 4, 2, 12.00, '2026-06-28 14:30:00', 'Efectivo', 'comprobantes/pago_009.jpg'),
(15, 4, 2, 10.00, '2026-06-29 15:45:00', 'Transferencia', 'comprobantes/pago_010.jpg');

COMMIT;

SELECT '=== RESUMEN COMPLETO ===' as info;
SELECT 'Barrios' as tabla, COUNT(*) as total FROM barrios
UNION ALL SELECT 'Calles', COUNT(*) FROM calles
UNION ALL SELECT 'Usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'Viviendas', COUNT(*) FROM viviendas
UNION ALL SELECT 'Cobros', COUNT(*) FROM cobros
UNION ALL SELECT 'Pagos', COUNT(*) FROM pagos
UNION ALL SELECT 'Recaudaciones', COUNT(*) FROM recaudaciones
UNION ALL SELECT 'Lotes Calle', COUNT(*) FROM lotes_calle
UNION ALL SELECT 'Lotes Barrio', COUNT(*) FROM lotes_barrio
UNION ALL SELECT 'Recibos', COUNT(*) FROM recibos_finiquito
UNION ALL SELECT 'Reportes', COUNT(*) FROM reportes
UNION ALL SELECT 'Solicitudes Servicio', COUNT(*) FROM solicitudes_servicio
UNION ALL SELECT 'Solicitudes Vivienda', COUNT(*) FROM solicitudes_vivienda
UNION ALL SELECT 'Empresas', COUNT(*) FROM empresas
UNION ALL SELECT 'Exenciones', COUNT(*) FROM exenciones_cobro
UNION ALL SELECT 'Config Barrios', COUNT(*) FROM configuraciones_barrio;
