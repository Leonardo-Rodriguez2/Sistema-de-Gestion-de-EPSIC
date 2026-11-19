USE reciclaje;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DELETE FROM pagos;
DELETE FROM cobros;
DELETE FROM recaudaciones;
DELETE FROM recibos_finiquito;
DELETE FROM lotes_calle;
DELETE FROM lotes_barrio;
DELETE FROM exenciones_cobro;
DELETE FROM reportes;
DELETE FROM solicitudes_vivienda;

ALTER TABLE pagos AUTO_INCREMENT = 1;
ALTER TABLE cobros AUTO_INCREMENT = 1;
ALTER TABLE recaudaciones AUTO_INCREMENT = 1;
ALTER TABLE recibos_finiquito AUTO_INCREMENT = 1;
ALTER TABLE lotes_calle AUTO_INCREMENT = 1;
ALTER TABLE lotes_barrio AUTO_INCREMENT = 1;
ALTER TABLE exenciones_cobro AUTO_INCREMENT = 1;
ALTER TABLE reportes AUTO_INCREMENT = 1;
ALTER TABLE solicitudes_vivienda AUTO_INCREMENT = 1;

DELETE FROM detalles_encargado_calle;
DELETE FROM detalles_encargado_barrio;
DELETE FROM detalles_personal_obrero;
DELETE FROM detalles_gestor;

INSERT INTO detalles_encargado_calle (usuario_id, calle_id, dni, telefono) VALUES
(4, 1, '70856234', '987654321'),
(6, 4, '71234568', '986543210'),
(7, 6, '72369485', '985364721'),
(8, 2, '73480596', '984253612');

INSERT INTO detalles_encargado_barrio (usuario_id, barrio_id, dni, telefono, direccion) VALUES
(5, 2, '71582634', '984726351', 'Calle Principal 321 - Santa Ana'),
(8, 1, '72693745', '983615842', 'Calle Hatun Rumiyoc 105 - San Blas'),
(9, 3, '73704859', '982541732', 'Av. El Sol 200 - Wanchaq');

INSERT INTO detalles_gestor (usuario_id, dni, telefono, area) VALUES
(2, '45632187', '976543210', 'Recaudación y Control Financiero'),
(9, '46789213', '975432109', 'Supervisión y Verificación de Lotes');

INSERT INTO detalles_personal_obrero (usuario_id, cargo, dni, telefono, turno, contacto_emergencia) VALUES
(4, 'Recolector', '70856234', '987654321', 'Mañana', 'Rosa Quispe - 987111222'),
(6, 'Recolector', '71234568', '986543210', 'Tarde', 'María Mamani - 986222333'),
(7, 'Recolector', '72369485', '985364721', 'Mañana', 'Carlos Flores - 985222333'),
(10,'Recolector', '72345679', '985432109', 'Mañana', 'José Huamán - 985333444');

-- JUNIO 2026
INSERT INTO cobros (vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento, estado, tipo_cobro) VALUES
(1,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(2,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(3,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(4,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(5,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(6,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(7,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(8,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(9,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(10,6,2026,10.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(11,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(12,6,2026,12.00,'2026-06-01','2026-06-30','Pagado','Servicio'),
(13,6,2026,12.00,'2026-06-01','2026-06-30','Vencido','Servicio');

-- JULIO 2026
INSERT INTO cobros (vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento, estado, tipo_cobro) VALUES
(1,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(2,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(3,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(4,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(5,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(6,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(7,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(8,7,2026,10.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(9,7,2026,10.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(10,7,2026,10.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(11,7,2026,12.00,'2026-07-01','2026-07-31','Pagado','Servicio'),
(12,7,2026,12.00,'2026-07-01','2026-07-31','Pendiente','Servicio'),
(13,7,2026,12.00,'2026-07-01','2026-07-31','Vencido','Servicio');

-- AGOSTO 2026
INSERT INTO cobros (vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento, estado, tipo_cobro) VALUES
(1,8,2026,12.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(2,8,2026,12.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(3,8,2026,12.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(4,8,2026,12.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(5,8,2026,10.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(6,8,2026,10.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(7,8,2026,10.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(8,8,2026,10.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(9,8,2026,10.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(10,8,2026,10.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(11,8,2026,12.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(12,8,2026,12.00,'2026-08-01','2026-08-31','Pendiente','Servicio'),
(13,8,2026,12.00,'2026-08-01','2026-08-31','Vencido','Servicio');

INSERT INTO pagos (cobro_id, recolectado_por_id, gestor_id, monto_pagado, fecha_pago, metodo_pago, comprobante) VALUES
(1,4,2,12.00,'2026-06-10 09:25:00','Efectivo','comprobantes/pago_101.jpg'),
(2,4,2,12.00,'2026-06-10 10:15:00','Transferencia','comprobantes/pago_102.jpg'),
(3,6,2,12.00,'2026-06-11 09:40:00','Efectivo','comprobantes/pago_103.jpg'),
(4,6,2,12.00,'2026-06-11 11:20:00','Efectivo','comprobantes/pago_104.jpg'),
(5,6,2,10.00,'2026-06-12 09:15:00','Efectivo','comprobantes/pago_105.jpg'),
(6,6,2,10.00,'2026-06-12 10:30:00','Transferencia','comprobantes/pago_106.jpg'),
(7,7,2,10.00,'2026-06-13 09:25:00','Efectivo','comprobantes/pago_107.jpg'),
(8,7,2,10.00,'2026-06-13 11:05:00','Efectivo','comprobantes/pago_108.jpg'),
(9,7,2,10.00,'2026-06-14 09:40:00','Transferencia','comprobantes/pago_109.jpg'),
(10,7,2,10.00,'2026-06-14 10:55:00','Efectivo','comprobantes/pago_110.jpg'),
(11,4,2,12.00,'2026-06-15 09:30:00','Efectivo','comprobantes/pago_111.jpg'),
(12,4,2,12.00,'2026-06-15 11:15:00','Efectivo','comprobantes/pago_112.jpg'),
(14,4,2,12.00,'2026-07-08 09:20:00','Efectivo','comprobantes/pago_201.jpg'),
(15,4,2,12.00,'2026-07-08 10:25:00','Transferencia','comprobantes/pago_202.jpg'),
(16,6,2,12.00,'2026-07-09 09:35:00','Efectivo','comprobantes/pago_203.jpg'),
(17,6,2,12.00,'2026-07-09 11:10:00','Efectivo','comprobantes/pago_204.jpg'),
(18,6,2,10.00,'2026-07-10 09:25:00','Efectivo','comprobantes/pago_205.jpg'),
(19,6,2,10.00,'2026-07-10 10:40:00','Transferencia','comprobantes/pago_206.jpg'),
(20,7,2,10.00,'2026-07-11 09:30:00','Efectivo','comprobantes/pago_207.jpg'),
(21,7,2,10.00,'2026-07-11 11:00:00','Efectivo','comprobantes/pago_208.jpg'),
(24,4,2,12.00,'2026-07-12 09:25:00','Efectivo','comprobantes/pago_209.jpg');

INSERT INTO recaudaciones (tipo, emisor_id, receptor_id, barrio_id, calle_id, monto_total, estado, fecha_recaudacion, comprobante, observaciones) VALUES
('Calle',4,2,1,1,48.00,'Verificado','2026-07-12 14:20:00','comprobantes/rec_101.pdf','Recaudación Calle Hatun Rumiyoc - Junio/Julio 2026'),
('Calle',6,2,2,4,40.00,'Verificado','2026-07-13 14:25:00','comprobantes/rec_102.pdf','Recaudación Calle Principal - Junio/Julio 2026'),
('Calle',7,2,3,6,20.00,'En revisión','2026-07-14 11:15:00','comprobantes/rec_103.pdf','Recaudación Jr. Saphy - Julio 2026'),
('Barrio',5,2,2,NULL,190.00,'Verificado','2026-07-15 10:40:00','comprobantes/rec_201.pdf','Consolidado Barrio Santa Ana - Julio 2026'),
('Barrio',8,2,1,NULL,156.00,'Pendiente','2026-07-16 09:35:00','comprobantes/rec_202.pdf','Consolidado Barrio San Blas - Julio 2026');

INSERT INTO lotes_calle (calle_id, mes, anio, monto_recolectado, estado, observaciones, fecha_envio, fecha_evaluacion) VALUES
(1,7,2026,48.00,'Aprobado','Lote completo con todos los cobros pagados.','2026-07-12 13:00:00','2026-07-12 15:10:00'),
(4,7,2026,40.00,'Aprobado','Lote completo con todos los cobros pagados.','2026-07-13 13:00:00','2026-07-13 15:15:00'),
(6,7,2026,20.00,'Enviado','Enviado al Encargado de Barrio para consolidación.','2026-07-14 10:30:00',NULL),
(2,7,2026,24.00,'Abierto','Aún se recepcionan pagos en calle.','2026-07-16 09:00:00',NULL),
(3,7,2026,12.00,'Abierto','Pendiente de recolección de pagos.','2026-07-16 09:20:00',NULL);

INSERT INTO lotes_barrio (periodo_mes, periodo_anio, barrio_id, encargado_barrio_id, gestor_id, monto_total_esperado, monto_total_recolectado, total_calles, calles_completas, estado, alerta_deuda, comprobante_barrio, observaciones_barrio, observaciones_gestor, recibo_generado, fecha_creacion, fecha_envio, fecha_aprobacion) VALUES
(7,2026,2,5,2,190.00,190.00,2,2,'Aprobado',0,'comprobantes/lote_barrio_101.pdf','Consolidado con lotes verificados.','Revisado y aprobado sin observaciones.',1,'2026-07-15 08:20:00','2026-07-15 09:15:00','2026-07-15 11:25:00'),
(7,2026,1,8,2,156.00,48.00,3,1,'Enviado',0,'comprobantes/lote_barrio_102.pdf','Se remiten lotes aprobados, pendientes 2 calles.','Pendiente de revisión por Gestor de Pagos.',0,'2026-07-16 08:30:00','2026-07-16 09:40:00',NULL);

INSERT INTO recibos_finiquito (lote_barrio_id, barrio_id, periodo_mes, periodo_anio, monto_aprobado, generado_por, numero_recibo, fecha_emision) VALUES
(1,2,7,2026,190.00,2,'RF-2026-07-0001','2026-07-15 11:40:00');

INSERT INTO exenciones_cobro (vivienda_id, calle_id, barrio_id, tipo_exencion, descripcion, creado_por, estado, aprobado_por, fecha_creacion, fecha_revision) VALUES
(13,2,1,'pobreza','Situación económica vulnerable - Exención temporal',1,'Aprobado',1,NOW(),NOW());


INSERT INTO reportes (usuario_id, ubicacion_nombre, latitud, longitud, tipo_residuo, descripcion, cantidad, estado, fotos, fecha_reporte) VALUES
(4,'Calle Principal - Santa Ana',-13.51800000,-71.97800000,'Plástico PET','Acumulación de botellas plásticas junto al contenedor comunitario.',15.50,'Completado','uploads/reportes/reporte_001.jpg','2026-07-01 09:15:00'),
(5,'Av. El Sol - Wanchaq',-13.52250000,-71.96780000,'Cartón','Cajas de cartón apiladas tras mudanza.',28.00,'En proceso','uploads/reportes/reporte_002.jpg','2026-07-02 11:20:00'),
(NULL,'Plaza San Blas',-13.51580000,-71.97720000,'Vidrio','Botellas y frascos de vidrio rotos en área pública.',12.25,'Pendiente','uploads/reportes/reporte_003.jpg','2026-07-03 14:45:00'),
(6,'Urb. Santa Ana Mz A',-13.52010000,-71.97050000,'Papel','Periódicos y papel archivo para reciclaje.',9.75,'Completado','uploads/reportes/reporte_004.jpg','2026-07-04 08:30:00'),
(7,'Calle Carmen Alto - San Blas',-13.51490000,-71.97910000,'Aluminio','Latas de aluminio almacenadas para acopio.',6.40,'En proceso','uploads/reportes/reporte_005.jpg','2026-07-05 10:10:00'),
(NULL,'Mercado Wanchaq',-13.52530000,-71.96590000,'Orgánico','Residuos orgánicos separados para compostaje comunitario.',35.80,'Pendiente','uploads/reportes/reporte_006.jpg','2026-07-06 07:55:00'),
(4,'Pasaje Hatun Rumiyoc - San Blas',-13.51620000,-71.97860000,'Plástico HDPE','Envases plásticos limpios listos para recolección.',14.30,'Completado','uploads/reportes/reporte_007.jpg','2026-07-07 13:25:00'),
(8,'Jr. Saphy - Wanchaq',-13.52380000,-71.96920000,'Tetra Pak','Envases Tetra Pak acumulados en punto limpio.',11.20,'En proceso','uploads/reportes/reporte_008.jpg','2026-07-08 15:40:00'),
(NULL,'Calle Tandapata - San Blas',-13.51450000,-71.98000000,'Vidrio','Frascos de vidrio limpios separados por color.',16.90,'Completado','uploads/reportes/reporte_009.jpg','2026-07-09 09:50:00'),
(10,'Av. Cultural - Santiago',-13.51970000,-71.97210000,'Cartón','Cartón corrugado de comercios para reciclaje.',42.60,'En proceso','uploads/reportes/reporte_010.jpg','2026-07-10 12:15:00'),
(NULL,'Parque San Blas',-13.51520000,-71.97790000,'Plástico PET','Botellas recolectadas en jornada de voluntariado.',19.40,'Pendiente','uploads/reportes/reporte_011.jpg','2026-07-11 16:05:00'),
(6,'Calle Ruinas - Wanchaq',-13.52460000,-71.96680000,'Metal','Piezas metálicas pequeñas para acopio.',8.75,'Completado','uploads/reportes/reporte_012.jpg','2026-07-12 08:40:00'),
(4,'Av. El Sol - Wanchaq',-13.52280000,-71.96750000,'Papel','Resma de papel limpia para reciclaje.',7.25,'Pendiente','uploads/reportes/reporte_013.jpg','2026-07-13 10:30:00'),
(NULL,'Calle Carmen Alto - San Blas',-13.51500000,-71.97890000,'Vidrio','Frascos retornables separados.',13.60,'En proceso','uploads/reportes/reporte_014.jpg','2026-07-14 14:20:00'),
(7,'Calle Hatun Rumiyoc - San Blas',-13.51680000,-71.97820000,'Cartón','Cajas de delivery para acopio.',24.30,'Pendiente','uploads/reportes/reporte_015.jpg','2026-07-15 09:10:00'),
(10,'Jr. Saphy - Wanchaq',-13.52400000,-71.96900000,'Plástico PET','Botellas PET compactadas.',18.90,'Completado','uploads/reportes/reporte_016.jpg','2026-07-16 11:05:00');


INSERT INTO solicitudes_vivienda (tipo, calle_id, vivienda_id, monto_deuda, detalles_deuda, propietario, cedula, numero_casa, referencia, estado, creado_por, revisado_por, fecha_creacion, fecha_revision) VALUES
('Alta',1,NULL,0.00,NULL,'Familia Yana Ccahuana','45678912','105','Casa con rejas verdes','Aprobado',4,5,'2026-07-04 13:55:56','2026-07-04 21:07:19'),
('Alta',1,NULL,0.00,NULL,'Familia Puma Quispe','46781293','107','Frente a parque pequeño','Aprobado',4,5,'2026-07-04 21:42:58','2026-07-04 21:43:06'),
('Alta',2,NULL,0.00,NULL,'Familia Condori Mamani','47892314','214','Casa de dos pisos','Aprobado',7,8,'2026-07-05 10:15:00','2026-07-05 12:32:47'),
('Alta',3,NULL,0.00,NULL,'Familia Huamán Flores','48903425','328','Con jardín delantero','Aprobado',7,8,'2026-07-05 13:11:58','2026-07-05 13:12:04'),
('Alta',5,NULL,0.00,NULL,'Familia Cárdenas Loaiza','53458970','156','Esquina con farmacia','Pendiente',8,NULL,'2026-07-12 14:20:00',NULL),
('Alta',6,NULL,0.00,NULL,'Familia Sánchez Yana','54569081','243','Casa colonial','Pendiente',8,NULL,'2026-07-13 09:35:00',NULL),
('Baja',2,13,24.00,'Dos meses de cuota pendientes','Familia Apaza Mamani','57892314','216','','Pendiente',7,NULL,'2026-07-14 11:42:00',NULL),
('Renovacion',4,5,0.00,NULL,'Familia Silba','49014536','312','','Pendiente',6,NULL,'2026-07-16 13:15:00',NULL);

COMMIT;

SELECT '=== RESUMEN COMPLETO PARA TODOS LOS ROLES ===' as TITULO;
SELECT 'Usuarios' as TABLA, COUNT(*) as TOTAL FROM usuarios
UNION ALL SELECT 'Barrios', COUNT(*) FROM barrios
UNION ALL SELECT 'Calles', COUNT(*) FROM calles
UNION ALL SELECT 'Viviendas', COUNT(*) FROM viviendas
UNION ALL SELECT 'Cobros', COUNT(*) FROM cobros
UNION ALL SELECT 'Pagos', COUNT(*) FROM pagos
UNION ALL SELECT 'Recaudaciones', COUNT(*) FROM recaudaciones
UNION ALL SELECT 'Lotes Calle', COUNT(*) FROM lotes_calle
UNION ALL SELECT 'Lotes Barrio', COUNT(*) FROM lotes_barrio
UNION ALL SELECT 'Recibos Finiquito', COUNT(*) FROM recibos_finiquito
UNION ALL SELECT 'Reportes', COUNT(*) FROM reportes
UNION ALL SELECT 'Solicitudes Vivienda', COUNT(*) FROM solicitudes_vivienda
UNION ALL SELECT 'Exenciones', COUNT(*) FROM exenciones_cobro
UNION ALL SELECT 'Encargados de Calle', COUNT(*) FROM detalles_encargado_calle
UNION ALL SELECT 'Encargados de Barrio', COUNT(*) FROM detalles_encargado_barrio
UNION ALL SELECT 'Recolectores', COUNT(*) FROM detalles_personal_obrero
UNION ALL SELECT 'Gestores', COUNT(*) FROM detalles_gestor;
