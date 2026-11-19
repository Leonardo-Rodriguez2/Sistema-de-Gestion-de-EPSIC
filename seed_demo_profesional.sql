-- Seed demo data para fotos/profesional del sistema
USE reciclaje;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Limpiar datos demo previos si existen (mantener estructura y usuarios base)
DELETE FROM reportes;
DELETE FROM solicitudes_servicio;
DELETE FROM empresas;
DELETE FROM detalles_recolector;
DELETE FROM detalles_gestor;
DELETE FROM pagos;
DELETE FROM lotes_calle;
DELETE FROM lotes_barrio;
DELETE FROM recibos_finiquito;
DELETE FROM recaudaciones;

-- Reset AUTO_INCREMENT
ALTER TABLE reportes AUTO_INCREMENT = 1;
ALTER TABLE solicitudes_servicio AUTO_INCREMENT = 1;
ALTER TABLE empresas AUTO_INCREMENT = 1;
ALTER TABLE detalles_recolector AUTO_INCREMENT = 1;
ALTER TABLE detalles_gestor AUTO_INCREMENT = 1;
ALTER TABLE pagos AUTO_INCREMENT = 1;
ALTER TABLE lotes_calle AUTO_INCREMENT = 1;
ALTER TABLE lotes_barrio AUTO_INCREMENT = 1;
ALTER TABLE recibos_finiquito AUTO_INCREMENT = 1;
ALTER TABLE recaudaciones AUTO_INCREMENT = 1;

-- Empresas recicladoras
INSERT INTO empresas (nombre, descripcion, contacto, materiales) VALUES
('EcoReciclaje S.A.C.', 'Empresa dedicada al acopio, clasificación y comercialización de residuos reciclables.', '987654321 / ventas@ecoreciclaje.com', 'Plástico PET, Cartón, Papel, Vidrio, Aluminio'),
('GreenAndes SRL', 'Centro de acopio certificado con enfoque en economía circular y RCDs.', '984123456 / contacto@greenandes.pe', 'Metal, Chatarra, Vidrio, Plástico industrial'),
('Andes Circular', 'Aliado estratégico para reciclaje electrónico y gestión responsable.', '976543210 / operaciones@andescircular.pe', 'RAEE, Baterías, Cables, Equipos informáticos');

-- Reportes ciudadanos (para que se vean completos en reportes)
INSERT INTO reportes (usuario_id, ubicacion_nombre, latitud, longitud, tipo_residuo, descripcion, cantidad, estado, fotos, fecha_reporte) VALUES
(4, 'Calle Principal - Santa Ana', -13.51800000, -71.97800000, 'Plástico PET', 'Acumulación de botellas plásticas junto al contenedor comunitario.', 15.50, 'Completado', 'uploads/reportes/reporte_001.jpg', '2026-07-01 09:15:00'),
(5, 'Av. El Sol - Wanchaq', -13.52250000, -71.96780000, 'Cartón', 'Cajas de cartón apiladas tras mudanza, requieren recolección.', 28.00, 'En proceso', 'uploads/reportes/reporte_002.jpg', '2026-07-02 11:20:00'),
(NULL, 'Plaza San Blas', -13.51580000, -71.97720000, 'Vidrio', 'Botellas y frascos de vidrio rotos en área pública.', 12.25, 'Pendiente', 'uploads/reportes/reporte_003.jpg', '2026-07-03 14:45:00'),
(4, 'Urb. Santa Ana Mz A', -13.52010000, -71.97050000, 'Papel', 'Periódicos y papel archivo para reciclaje.', 9.75, 'Completado', 'uploads/reportes/reporte_004.jpg', '2026-07-04 08:30:00'),
(5, 'Calle Carmen Alto - San Blas', -13.51490000, -71.97910000, 'Aluminio', 'Latas de aluminio almacenadas para acopio.', 6.40, 'En proceso', 'uploads/reportes/reporte_005.jpg', '2026-07-05 10:10:00'),
(NULL, 'Mercado Wanchaq', -13.52530000, -71.96590000, 'Orgánico', 'Residuos orgánicos separados para compostaje comunitario.', 35.80, 'Pendiente', 'uploads/reportes/reporte_006.jpg', '2026-07-06 07:55:00'),
(4, 'Pasaje Hatun Rumiyoc - San Blas', -13.51620000, -71.97860000, 'Plástico HDPE', 'Envases plásticos limpios listos para recolección.', 14.30, 'Completado', 'uploads/reportes/reporte_007.jpg', '2026-07-07 13:25:00'),
(5, 'Jr. Saphy - Wanchaq', -13.52380000, -71.96920000, 'Tetra Pak', 'Envases Tetra Pak acumulados en punto limpio.', 11.20, 'En proceso', 'uploads/reportes/reporte_008.jpg', '2026-07-08 15:40:00'),
(NULL, 'Calle Tandapata - San Blas', -13.51450000, -71.98000000, 'Vidrio', 'Frascos de vidrio limpios separados por color.', 16.90, 'Completado', 'uploads/reportes/reporte_009.jpg', '2026-07-09 09:50:00'),
(4, 'Av. Cultural - Santa Ana', -13.51970000, -71.97210000, 'Cartón', 'Cartón corrugado de comercios para reciclaje.', 42.60, 'En proceso', 'uploads/reportes/reporte_010.jpg', '2026-07-10 12:15:00'),
(NULL, 'Parque San Blas', -13.51520000, -71.97790000, 'Plástico PET', 'Botellas recolectadas en jornada de voluntariado.', 19.40, 'Pendiente', 'uploads/reportes/reporte_011.jpg', '2026-07-11 16:05:00'),
(5, 'Calle Ruinas - Wanchaq', -13.52460000, -71.96680000, 'Metal', 'Piezas metálicas pequeñas para acopio.', 8.75, 'Completado', 'uploads/reportes/reporte_012.jpg', '2026-07-12 08:40:00');

-- Solicitudes de servicio público
INSERT INTO solicitudes_servicio (representante, telefono, barrio, tipo_contrato, correo, estado, fecha) VALUES
('María Quispe Ccahuana', '984562871', 'Barrio San Blas', 'Contrato Anual', 'maria.quispe@gmail.com', 'Pendiente', '2026-07-08 10:22:00'),
('José Condori Mamani', '976843215', 'Santa Ana', 'Contrato Semestral', 'jose.condori@hotmail.com', 'Atendido', '2026-07-09 14:18:00'),
('Rosa Huamán Flores', '987326548', 'Wanchaq', 'Contrato Anual', 'rosa.huaman@yahoo.com', 'Pendiente', '2026-07-10 09:35:00'),
('Carlos Sánchez Yana', '965874123', 'Barrio San Blas', 'Contrato Trimestral', 'carlos.sanchez@gmail.com', 'Atendido', '2026-07-11 11:47:00'),
('Lucía Puma Ticona', '974562398', 'Santa Ana', 'Contrato Anual', 'lucia.puma@gmail.com', 'Descartado', '2026-07-12 16:12:00'),
('Edgar Cárdenas Loaiza', '986742159', 'Wanchaq', 'Contrato Anual', 'edgar.cardenas@gmail.com', 'Pendiente', '2026-07-13 08:54:00');

-- Detalles de recolector
INSERT INTO detalles_recolector (usuario_id, dni, telefono, turno, contacto_emergencia) VALUES
(4, '70856234', '987654321', 'Mañana', 'Ana Calle - 987111222');

-- Detalles de gestor
INSERT INTO detalles_gestor (usuario_id, dni, telefono, area) VALUES
(2, '45632187', '976543210', 'Recaudación y Control Financiero');

-- Recaudaciones demo
INSERT INTO recaudaciones (tipo, emisor_id, receptor_id, barrio_id, calle_id, monto_total, estado, fecha_recaudacion, comprobante, observaciones) VALUES
('Calle', 4, 2, 2, 1, 30.00, 'Verificado', '2026-07-05 14:25:00', 'comprobantes/rec_001.pdf', 'Recaudación mensual calle principal - Julio 2026'),
('Barrio', 5, 2, 2, NULL, 150.00, 'Verificado', '2026-07-06 10:40:00', 'comprobantes/rec_002.pdf', 'Consolidado mensual Santa Ana - Julio 2026');

-- Lotes de calle
INSERT INTO lotes_calle (calle_id, mes, anio, monto_recolectado, estado, observaciones, fecha_envio, fecha_evaluacion, creado_en) VALUES
(1, 7, 2026, 30.00, 'Aprobado', 'Lote completo con todos los cobros verificados.', '2026-07-05 14:00:00', '2026-07-05 15:10:00', '2026-07-05 09:30:00');

-- Lotes de barrio
INSERT INTO lotes_barrio (periodo_mes, periodo_anio, barrio_id, encargado_barrio_id, gestor_id, monto_total_esperado, monto_total_recolectado, total_calles, calles_completas, estado, alerta_deuda, comprobante_barrio, observaciones_barrio, observaciones_gestor, recibo_generado, fecha_creacion, fecha_envio, fecha_aprobacion) VALUES
(7, 2026, 2, 5, 2, 150.00, 150.00, 1, 1, 'Aprobado', 0, 'comprobantes/lote_barrio_001.pdf', 'Todas las calles enviaron sus lotes completos.', 'Lote revisado y aprobado sin observaciones.', 1, '2026-07-06 08:20:00', '2026-07-06 09:00:00', '2026-07-06 11:15:00');

-- Recibos de finiquito
INSERT INTO recibos_finiquito (lote_barrio_id, barrio_id, periodo_mes, periodo_anio, monto_aprobado, generado_por, numero_recibo, fecha_emision) VALUES
(1, 2, 7, 2026, 150.00, 2, 'RF-2026-07-0001', '2026-07-06 11:30:00');

-- Pagos demo (vinculados a cobros existentes)
INSERT INTO pagos (cobro_id, recolectado_por_id, gestor_id, monto_pagado, fecha_pago, metodo_pago, comprobante) VALUES
(1, 4, 2, 5.00, '2026-07-04 15:10:00', 'Efectivo', 'comprobantes/pago_001.jpg'),
(2, 4, 2, 5.00, '2026-07-04 16:25:00', 'Transferencia', 'comprobantes/pago_002.jpg'),
(3, 4, 2, 10.00, '2026-07-05 09:40:00', 'Efectivo', 'comprobantes/pago_003.jpg'),
(4, 4, 2, 10.00, '2026-07-05 11:15:00', 'Efectivo', 'comprobantes/pago_004.jpg');

COMMIT;

-- Ver resumen
SELECT 'Reportes' as tabla, COUNT(*) as total FROM reportes
UNION ALL SELECT 'Solicitudes', COUNT(*) FROM solicitudes_servicio
UNION ALL SELECT 'Empresas', COUNT(*) FROM empresas
UNION ALL SELECT 'Pagos', COUNT(*) FROM pagos
UNION ALL SELECT 'Lotes Calle', COUNT(*) FROM lotes_calle
UNION ALL SELECT 'Lotes Barrio', COUNT(*) FROM lotes_barrio
UNION ALL SELECT 'Recaudaciones', COUNT(*) FROM recaudaciones;
