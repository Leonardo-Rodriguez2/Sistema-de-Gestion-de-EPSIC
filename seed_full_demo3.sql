USE reciclaje;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Exenciones
INSERT INTO exenciones_cobro (vivienda_id, calle_id, barrio_id, tipo_exencion, descripcion, creado_por, estado, aprobado_por, fecha_creacion, fecha_revision) VALUES
(13,2,1,'pobreza','Situación económica vulnerable',1,'Aprobado',1,NOW(),NOW());


-- Reportes
INSERT INTO reportes (usuario_id, ubicacion_nombre, latitud, longitud, tipo_residuo, descripcion, cantidad, estado, fotos, fecha_reporte) VALUES
(4,'Calle Principal - Santa Ana',-13.51800000,-71.97800000,'Plástico PET','Acumulación de botellas plásticas junto al contenedor comunitario.',15.50,'Completado','uploads/reportes/reporte_001.jpg','2026-07-01 09:15:00'),
(5,'Av. El Sol - Wanchaq',-13.52250000,-71.96780000,'Cartón','Cajas de cartón apiladas tras mudanza, requieren recolección.',28.00,'En proceso','uploads/reportes/reporte_002.jpg','2026-07-02 11:20:00'),
(NULL,'Plaza San Blas',-13.51580000,-71.97720000,'Vidrio','Botellas y frascos de vidrio rotos en área pública.',12.25,'Pendiente','uploads/reportes/reporte_003.jpg','2026-07-03 14:45:00'),
(4,'Urb. Santa Ana Mz A',-13.52010000,-71.97050000,'Papel','Periódicos y papel archivo para reciclaje.',9.75,'Completado','uploads/reportes/reporte_004.jpg','2026-07-04 08:30:00'),
(5,'Calle Carmen Alto - San Blas',-13.51490000,-71.97910000,'Aluminio','Latas de aluminio almacenadas para acopio.',6.40,'En proceso','uploads/reportes/reporte_005.jpg','2026-07-05 10:10:00'),
(NULL,'Mercado Wanchaq',-13.52530000,-71.96590000,'Orgánico','Residuos orgánicos separados para compostaje comunitario.',35.80,'Pendiente','uploads/reportes/reporte_006.jpg','2026-07-06 07:55:00'),
(6,'Pasaje Hatun Rumiyoc - San Blas',-13.51620000,-71.97860000,'Plástico HDPE','Envases plásticos limpios listos para recolección.',14.30,'Completado','uploads/reportes/reporte_007.jpg','2026-07-07 13:25:00'),
(7,'Jr. Saphy - Wanchaq',-13.52380000,-71.96920000,'Tetra Pak','Envases Tetra Pak acumulados en punto limpio.',11.20,'En proceso','uploads/reportes/reporte_008.jpg','2026-07-08 15:40:00'),
(NULL,'Calle Tandapata - San Blas',-13.51450000,-71.98000000,'Vidrio','Frascos de vidrio limpios separados por color.',16.90,'Completado','uploads/reportes/reporte_009.jpg','2026-07-09 09:50:00'),
(8,'Av. Cultural - Santiago',-13.51970000,-71.97210000,'Cartón','Cartón corrugado de comercios para reciclaje.',42.60,'En proceso','uploads/reportes/reporte_010.jpg','2026-07-10 12:15:00'),
(NULL,'Parque San Blas',-13.51520000,-71.97790000,'Plástico PET','Botellas recolectadas en jornada de voluntariado.',19.40,'Pendiente','uploads/reportes/reporte_011.jpg','2026-07-11 16:05:00'),
(10,'Calle Ruinas - Wanchaq',-13.52460000,-71.96680000,'Metal','Piezas metálicas pequeñas para acopio.',8.75,'Completado','uploads/reportes/reporte_012.jpg','2026-07-12 08:40:00'),
(6,'Av. El Sol - Wanchaq',-13.52280000,-71.96750000,'Papel','Resma de papel para reciclaje.',7.25,'Pendiente','uploads/reportes/reporte_013.jpg','2026-07-13 10:30:00'),
(NULL,'Calle Carmen Alto - San Blas',-13.51500000,-71.97890000,'Vidrio','Frascos retornables separados.',13.60,'En proceso','uploads/reportes/reporte_014.jpg','2026-07-14 14:20:00');

-- Solicitudes servicio

-- Solicitudes vivienda
INSERT INTO solicitudes_vivienda (tipo, calle_id, vivienda_id, monto_deuda, detalles_deuda, propietario, cedula, numero_casa, referencia, estado, creado_por, revisado_por, fecha_creacion, fecha_revision) VALUES
('Alta',1,NULL,0.00,NULL,'Familia Yana Ccahuana','45678912','105','Casa con rejas verdes','Aprobado',4,5,'2026-07-04 13:55:56','2026-07-04 21:07:19'),
('Alta',1,NULL,0.00,NULL,'Familia Puma Quispe','46781293','107','Frente a parque pequeño','Aprobado',4,5,'2026-07-04 21:42:58','2026-07-04 21:43:06'),
('Alta',2,NULL,0.00,NULL,'Familia Condori Mamani','47892314','214','Casa de dos pisos','Aprobado',7,5,'2026-07-05 10:15:00','2026-07-05 12:32:47'),
('Alta',3,NULL,0.00,NULL,'Familia Huamán Flores','48903425','328','Con jardín delantero','Aprobado',7,9,'2026-07-05 13:11:58','2026-07-05 13:12:04'),
('Alta',5,NULL,0.00,NULL,'Familia Cárdenas Loaiza','53458970','156','Esquina con farmacia','Pendiente',8,NULL,'2026-07-12 14:20:00',NULL),
('Alta',6,NULL,0.00,NULL,'Familia Sánchez Yana','54569081','243','Casa colonial','Pendiente',8,NULL,'2026-07-13 09:35:00',NULL),
('Baja',2,13,24.00,'Dos meses de cuota pendientes','Familia Apaza Mamani','57892314','216','','Pendiente',7,NULL,'2026-07-14 11:42:00',NULL);

-- Recaudaciones
INSERT INTO recaudaciones (tipo, emisor_id, receptor_id, barrio_id, calle_id, monto_total, estado, fecha_recaudacion, comprobante, observaciones) VALUES
('Calle',4,2,2,4,40.00,'Verificado','2026-07-05 14:25:00','comprobantes/rec_001.pdf','Recaudación calle principal - Julio 2026'),
('Barrio',5,2,2,NULL,150.00,'Verificado','2026-07-06 10:40:00','comprobantes/rec_002.pdf','Consolidado Santa Ana - Julio 2026'),
('Calle',7,2,1,1,24.00,'Verificado','2026-07-07 15:20:00','comprobantes/rec_003.pdf','Recaudación Calle Hatun Rumiyoc - Julio 2026'),
('Calle',8,9,1,2,12.00,'Pendiente','2026-07-08 11:15:00','comprobantes/rec_004.pdf','Recaudación Calle Carmen Alto - Julio 2026');

-- Lotes
INSERT INTO lotes_calle (calle_id, mes, anio, monto_recolectado, estado, observaciones, fecha_envio, fecha_evaluacion) VALUES
(4,7,2026,40.00,'Aprobado','Lote completo con todos los cobros verificados.','2026-07-05 14:00:00','2026-07-05 15:10:00'),
(1,7,2026,24.00,'Aprobado','Lote completo.','2026-07-07 14:30:00','2026-07-07 16:00:00'),
(2,7,2026,12.00,'Enviado','Pendiente de revisión por gestor.','2026-07-08 10:45:00',NULL),
(3,7,2026,12.00,'Abierto','En proceso de recolección.',NULL,NULL);

INSERT INTO lotes_barrio (periodo_mes, periodo_anio, barrio_id, encargado_barrio_id, gestor_id, monto_total_esperado, monto_total_recolectado, total_calles, calles_completas, estado, alerta_deuda, comprobante_barrio, observaciones_barrio, observaciones_gestor, recibo_generado, fecha_creacion, fecha_envio, fecha_aprobacion) VALUES
(7,2026,2,5,2,40.00,40.00,1,1,'Aprobado',0,'comprobantes/lote_barrio_001.pdf','Todas las calles enviaron sus lotes completos.','Lote revisado y aprobado sin observaciones.',1,'2026-07-06 08:20:00','2026-07-06 09:00:00','2026-07-06 11:15:00'),
(7,2026,1,9,2,48.00,24.00,3,1,'Enviado',0,'comprobantes/lote_barrio_002.pdf','Se envían lotes aprobados, falta calle Carmen Alto.','Pendiente de revisión.',0,'2026-07-07 09:00:00','2026-07-07 10:30:00',NULL);

INSERT INTO recibos_finiquito (lote_barrio_id, barrio_id, periodo_mes, periodo_anio, monto_aprobado, generado_por, numero_recibo, fecha_emision) VALUES
(1,2,7,2026,40.00,2,'RF-2026-07-0001','2026-07-06 11:30:00');

-- Pagos
INSERT INTO pagos (cobro_id, recolectado_por_id, gestor_id, monto_pagado, fecha_pago, metodo_pago, comprobante) VALUES
(1,4,2,12.00,'2026-07-04 15:10:00','Efectivo','comprobantes/pago_001.jpg'),
(2,4,2,12.00,'2026-07-04 16:25:00','Transferencia','comprobantes/pago_002.jpg'),
(4,4,2,12.00,'2026-07-05 09:40:00','Efectivo','comprobantes/pago_003.jpg'),
(5,4,2,10.00,'2026-07-04 15:10:00','Efectivo','comprobantes/pago_004.jpg'),
(6,4,2,10.00,'2026-07-04 16:25:00','Transferencia','comprobantes/pago_005.jpg'),
(7,4,2,10.00,'2026-07-05 09:40:00','Efectivo','comprobantes/pago_006.jpg'),
(8,4,2,10.00,'2026-07-05 11:15:00','Efectivo','comprobante/pago_007.jpg'),
(11,6,2,12.00,'2026-07-06 10:20:00','Efectivo','comprobantes/pago_008.jpg'),
(14,4,2,12.00,'2026-06-28 14:30:00','Efectivo','comprobantes/pago_009.jpg'),
(15,4,2,10.00,'2026-06-29 15:45:00','Transferencia','comprobantes/pago_010.jpg');

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
UNION ALL SELECT 'Solicitudes Vivienda', COUNT(*) FROM solicitudes_vivienda
UNION ALL SELECT 'Exenciones', COUNT(*) FROM exenciones_cobro
UNION ALL SELECT 'Config Barrios', COUNT(*) FROM configuraciones_barrio;
