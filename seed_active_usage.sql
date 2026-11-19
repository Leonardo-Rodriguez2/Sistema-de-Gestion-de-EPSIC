-- Datos incrementales de uso simulado para EPSIC.
-- No elimina ni inserta registros en usuarios.
USE reciclaje;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
START TRANSACTION;

-- Completar perfiles relacionados usando usuarios existentes.
INSERT INTO detalles_personal_obrero (usuario_id, cargo, dni, telefono, turno, contacto_emergencia)
SELECT u.id, 'Recolector', CONCAT('70', LPAD(u.id, 6, '0')), CONCAT('98', LPAD(u.id, 7, '0')),
       CASE MOD(u.id, 3) WHEN 0 THEN 'Mañana' WHEN 1 THEN 'Tarde' ELSE 'Noche' END,
       CONCAT('Contacto de emergencia de ', u.nombre)
FROM usuarios u
LEFT JOIN detalles_personal_obrero d ON d.usuario_id = u.id
WHERE u.rol_id = 3 AND d.id IS NULL;

INSERT INTO detalles_gestor (usuario_id, dni, telefono, area)
SELECT u.id, CONCAT('45', LPAD(u.id, 6, '0')), CONCAT('97', LPAD(u.id, 7, '0')), 'Recaudación y control financiero'
FROM usuarios u
LEFT JOIN detalles_gestor d ON d.usuario_id = u.id
WHERE u.rol_id = 2 AND d.id IS NULL;

INSERT INTO configuraciones_barrio (barrio_id, cuota_mensual, multa_renovacion, actualizado_por)
SELECT b.id, CASE WHEN MOD(b.id, 2) = 0 THEN 10.00 ELSE 12.00 END, 5.00, 1
FROM barrios b
LEFT JOIN configuraciones_barrio c ON c.barrio_id = b.id
WHERE c.id IS NULL;

-- Viviendas nuevas para simular crecimiento durante varios meses.
DROP TEMPORARY TABLE IF EXISTS seed_calles;
CREATE TEMPORARY TABLE seed_calles AS
SELECT id, barrio_id, ROW_NUMBER() OVER (ORDER BY id) AS orden
FROM calles;
CREATE TEMPORARY TABLE seed_barrios AS
SELECT id, nombre, ROW_NUMBER() OVER (ORDER BY id) AS orden
FROM barrios;

CREATE TEMPORARY TABLE seed_nums (n INT PRIMARY KEY);
INSERT INTO seed_nums (n) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13),(14),(15),(16),(17),(18),(19),(20),
(21),(22),(23),(24),(25),(26),(27),(28),(29),(30),(31),(32),(33),(34),(35),(36),(37),(38),(39),(40),
(41),(42),(43),(44),(45),(46),(47),(48),(49),(50),(51),(52),(53),(54),(55),(56),(57),(58),(59),(60);

INSERT INTO viviendas (
    barrio_id, calle_id, propietario, cedula, telefono, direccion,
    numero_casa, referencia, estado_servicio, exento_cobro, fecha_registro
)
SELECT sc.barrio_id, sc.id, CONCAT('Familia Demo ', LPAD(n.n, 3, '0')),
       CONCAT('58', LPAD(40000000 + n.n, 8, '0')),
       CONCAT('98', LPAD(7000000 + n.n, 7, '0')),
       CONCAT('Zona ', sc.barrio_id, ' - ', c.nombre),
       CONCAT(100 + n.n), IF(MOD(n.n, 4) = 0, 'Cerca del punto ecológico', 'Vivienda residencial'),
       CASE WHEN MOD(n.n, 13) = 0 THEN 'Suspendido' WHEN MOD(n.n, 29) = 0 THEN 'Anulado' ELSE 'Activo' END,
       IF(MOD(n.n, 17) = 0, 1, 0), DATE_SUB('2026-07-31', INTERVAL MOD(n.n, 6) MONTH)
FROM seed_nums n
JOIN seed_calles sc ON sc.orden = 1 + MOD(n.n - 1, (SELECT COUNT(*) FROM seed_calles))
JOIN calles c ON c.id = sc.id;

-- Cobros mensuales de enero a junio de 2026 para todas las viviendas.
INSERT INTO cobros (
    vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento,
    estado, tipo_cobro, estado_verificacion, verificado_por, verificado_en,
    referencia_pago, observaciones
)
SELECT v.id, m.n, 2026,
       CASE WHEN EXISTS (
           SELECT 1 FROM configuraciones_barrio cb WHERE cb.barrio_id = v.barrio_id AND cb.cuota_mensual = 12.00
       ) THEN 12.00 ELSE 10.00 END,
       STR_TO_DATE(CONCAT('2026-', LPAD(m.n, 2, '0'), '-01'), '%Y-%m-%d'),
       LAST_DAY(STR_TO_DATE(CONCAT('2026-', LPAD(m.n, 2, '0'), '-01'), '%Y-%m-%d')),
       CASE MOD(v.id + m.n, 5) WHEN 0 THEN 'Vencido' WHEN 1 THEN 'Pagado' WHEN 2 THEN 'Pagado' ELSE 'Pendiente' END,
       'Servicio',
       CASE MOD(v.id + m.n, 5) WHEN 1 THEN 'Verificado' WHEN 2 THEN 'Verificado' ELSE 'Pendiente' END,
       CASE MOD(v.id + m.n, 5) WHEN 1 THEN 2 WHEN 2 THEN 2 ELSE NULL END,
       CASE MOD(v.id + m.n, 5) WHEN 1 THEN CONCAT('2026-', LPAD(m.n, 2, '0'), '-25 16:00:00') WHEN 2 THEN CONCAT('2026-', LPAD(m.n, 2, '0'), '-26 10:00:00') ELSE NULL END,
       CASE MOD(v.id + m.n, 5) WHEN 1 THEN CONCAT('TRF-', 2026, LPAD(m.n, 2, '0'), LPAD(v.id, 4, '0')) ELSE NULL END,
       IF(MOD(v.id, 17) = 0, 'Vivienda exonerada registrada para revisión', NULL)
FROM viviendas v
CROSS JOIN (SELECT n FROM seed_nums WHERE n <= 6) m
WHERE NOT EXISTS (
    SELECT 1 FROM cobros c WHERE c.vivienda_id = v.id AND c.mes = m.n AND c.anio = 2026
);

CREATE TEMPORARY TABLE seed_viviendas AS
SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS orden
FROM viviendas;

-- Recaudaciones por calle y por barrio de cada mes.
INSERT INTO recaudaciones (tipo, emisor_id, receptor_id, barrio_id, calle_id, monto_total, estado, fecha_recaudacion, observaciones)
SELECT 'Calle', COALESCE((SELECT id FROM usuarios WHERE rol_id = 6 ORDER BY id LIMIT 1), 1), 2,
       c.barrio_id, c.id, ROUND(35 + MOD(c.id * m.mes, 45), 2),
       CASE WHEN m.mes IN (2, 4, 6) THEN 'Verificado' ELSE 'Pendiente' END,
       CONCAT('2026-', LPAD(m.mes, 2, '0'), '-27 14:00:00'),
       CONCAT('Cierre mensual de ', c.nombre)
FROM calles c
CROSS JOIN (SELECT 1 AS mes UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) m;

INSERT INTO recaudaciones (tipo, emisor_id, receptor_id, barrio_id, calle_id, monto_total, estado, fecha_recaudacion, observaciones)
SELECT 'Barrio', COALESCE((SELECT id FROM usuarios WHERE rol_id = 5 ORDER BY id LIMIT 1), 1), 2,
       b.id, NULL, ROUND(120 + b.id * 18 + m.mes * 4, 2),
       CASE WHEN MOD(b.id + m.mes, 3) = 0 THEN 'Pendiente' ELSE 'Verificado' END,
       CONCAT('2026-', LPAD(m.mes, 2, '0'), '-29 10:00:00'),
       CONCAT('Consolidado mensual de ', b.nombre)
FROM barrios b
CROSS JOIN (SELECT 1 AS mes UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) m;

-- Pagos para cobros marcados como pagados que aún no tienen detalle.
INSERT INTO pagos (cobro_id, recolectado_por_id, gestor_id, monto_pagado, fecha_pago, metodo_pago, comprobante)
SELECT c.id,
       COALESCE((SELECT id FROM usuarios WHERE rol_id = 6 ORDER BY id LIMIT 1), 1),
       2, c.monto, CONCAT(c.anio, '-', LPAD(c.mes, 2, '0'), '-25 17:30:00'),
       CASE MOD(c.id, 3) WHEN 0 THEN 'Efectivo' WHEN 1 THEN 'Transferencia' ELSE 'Tarjeta' END,
       CONCAT('comprobantes/pago_demo_', c.id, '.jpg')
FROM cobros c
LEFT JOIN pagos p ON p.cobro_id = c.id
WHERE c.estado = 'Pagado' AND p.id IS NULL;

-- Lotes mensuales de cada calle, con estados variados para el panel de gestión.
INSERT IGNORE INTO lotes_calle (
    calle_id, mes, anio, monto_recolectado, estado, observaciones, fecha_envio,
    fecha_evaluacion, periodo_mes, periodo_anio, barrio_id, encargado_calle_id,
    encargado_barrio_id, monto_esperado, total_casas, casas_pagadas, casas_morosas,
    alerta_deuda, observaciones_calle, observaciones_barrio, referencia_lote
)
SELECT c.id, m.mes, 2026, ROUND(40 + c.id * 3 + m.mes * 2, 2),
       CASE MOD(c.id + m.mes, 4) WHEN 0 THEN 'Abierto' WHEN 1 THEN 'Enviado' WHEN 2 THEN 'Aprobado' ELSE 'Rechazado' END,
       CONCAT('Seguimiento operativo del periodo ', m.mes, '/2026'),
       CASE WHEN MOD(c.id + m.mes, 4) = 0 THEN NULL ELSE CONCAT('2026-', LPAD(m.mes, 2, '0'), '-28 15:00:00') END,
       CASE WHEN MOD(c.id + m.mes, 4) = 2 THEN CONCAT('2026-', LPAD(m.mes, 2, '0'), '-30 11:00:00') ELSE NULL END,
       m.mes, 2026, c.barrio_id,
       COALESCE((SELECT usuario_id FROM detalles_encargado_calle dc WHERE dc.calle_id = c.id LIMIT 1), 1),
       COALESCE((SELECT usuario_id FROM detalles_encargado_barrio db WHERE db.barrio_id = c.barrio_id LIMIT 1), 1),
       80.00, 10, 7, 3, IF(MOD(c.id + m.mes, 4) = 3, 1, 0),
       'Cierre preparado por encargado de calle', 'Revisión de encargado de barrio',
       CONCAT('LC-', 2026, LPAD(m.mes, 2, '0'), '-', LPAD(c.id, 3, '0'))
FROM calles c
CROSS JOIN (SELECT 1 AS mes UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) m;

-- Lotes consolidados por barrio y recibos de finiquito para periodos aprobados.
INSERT IGNORE INTO lotes_barrio (
    periodo_mes, periodo_anio, barrio_id, encargado_barrio_id, gestor_id,
    monto_total_esperado, monto_total_recolectado, total_calles, calles_completas,
    estado, alerta_deuda, observaciones_barrio, observaciones_gestor, recibo_generado,
    fecha_creacion, fecha_envio, fecha_aprobacion, mes, anio
)
SELECT m.mes, 2026, b.id,
       COALESCE((SELECT usuario_id FROM detalles_encargado_barrio db WHERE db.barrio_id = b.id LIMIT 1), 1),
       2, 260.00, 230.00, COUNT(c.id), GREATEST(COUNT(c.id) - 1, 0),
       CASE MOD(b.id + m.mes, 4) WHEN 0 THEN 'Borrador' WHEN 1 THEN 'Enviado' WHEN 2 THEN 'Aprobado' ELSE 'Rechazado' END,
       IF(MOD(b.id + m.mes, 4) = 3, 1, 0),
       'Consolidado mensual generado para seguimiento', 'Revisión administrativa simulada',
       IF(MOD(b.id + m.mes, 4) = 2, 1, 0),
       CONCAT('2026-', LPAD(m.mes, 2, '0'), '-01 08:00:00'),
       CASE WHEN MOD(b.id + m.mes, 4) = 0 THEN NULL ELSE CONCAT('2026-', LPAD(m.mes, 2, '0'), '-29 16:00:00') END,
       CASE WHEN MOD(b.id + m.mes, 4) = 2 THEN CONCAT('2026-', LPAD(m.mes, 2, '0'), '-30 12:00:00') ELSE NULL END,
       m.mes, 2026
FROM barrios b
JOIN calles c ON c.barrio_id = b.id
CROSS JOIN (SELECT 1 AS mes UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) m
GROUP BY b.id, m.mes;

INSERT IGNORE INTO recibos_finiquito (
    lote_barrio_id, barrio_id, periodo_mes, periodo_anio, monto_aprobado,
    generado_por, numero_recibo, fecha_emision
)
SELECT lb.id, lb.barrio_id, lb.periodo_mes, lb.periodo_anio,
       lb.monto_total_recolectado, 2,
       CONCAT('REC-', lb.periodo_anio, LPAD(lb.periodo_mes, 2, '0'), '-', LPAD(lb.barrio_id, 3, '0')),
       CONCAT(lb.periodo_anio, '-', LPAD(lb.periodo_mes, 2, '0'), '-30 13:00:00')
FROM lotes_barrio lb
LEFT JOIN recibos_finiquito r ON r.lote_barrio_id = lb.id
WHERE lb.estado = 'Aprobado' AND r.id IS NULL;

-- Solicitudes y actividad del portal.

INSERT INTO reportes (usuario_id, ubicacion_nombre, latitud, longitud, tipo_residuo, descripcion, cantidad, estado, fotos, fecha_reporte)
SELECT NULL, CONCAT('Punto ecológico ', n), -13.51 - (n / 10000), -71.97 - (n / 10000),
       CASE MOD(n, 5) WHEN 0 THEN 'Cartón' WHEN 1 THEN 'Plástico PET' WHEN 2 THEN 'Vidrio' WHEN 3 THEN 'Papel' ELSE 'Aluminio' END,
       CONCAT('Reporte ciudadano de materiales separados para recolección. Caso demo ', n),
       5 + n * 1.25, CASE MOD(n, 3) WHEN 0 THEN 'Pendiente' WHEN 1 THEN 'En proceso' ELSE 'Completado' END,
       CONCAT('uploads/reportes/demo_', LPAD(n, 3, '0'), '.jpg'),
       CONCAT('2026-', LPAD(MOD(n - 1, 6) + 1, 2, '0'), '-', LPAD(MOD(n - 1, 26) + 1, 2, '0'), ' 10:15:00')
FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20) nums;

INSERT INTO recepciones_residuos (
    tipo_material, peso_kg, fecha_recepcion, origen_tipo, barrio_id, calle_id,
    referencia_origen, observaciones_recepcion, registrado_por, rol_id, estado
)
SELECT CASE MOD(n, 5) WHEN 0 THEN 'Cartón' WHEN 1 THEN 'Plástico PET' WHEN 2 THEN 'Vidrio' WHEN 3 THEN 'Papel' ELSE 'Aluminio' END,
       15.50 + n * 0.75, CONCAT('2026-', LPAD(MOD(n - 1, 6) + 1, 2, '0'), '-', LPAD(MOD(n - 1, 26) + 1, 2, '0')),
       'Barrio', sb.id, sc.id,
       CONCAT('Ruta de recolección demo ', n), 'Material pesado y clasificado al recibir.',
       COALESCE((SELECT id FROM usuarios WHERE rol_id = 3 ORDER BY id LIMIT 1), 1), 3,
       CASE WHEN MOD(n, 10) = 0 THEN 'Anulada' ELSE 'Registrada' END
FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24) nums
JOIN seed_barrios sb ON sb.orden = 1 + MOD(n - 1, (SELECT COUNT(*) FROM seed_barrios))
JOIN seed_calles sc ON sc.orden = 1 + MOD(n - 1, (SELECT COUNT(*) FROM seed_calles));


-- Solicitudes de vivienda con historial mixto: pendientes, aprobadas y rechazadas.
INSERT INTO solicitudes_vivienda (
    tipo, calle_id, vivienda_id, monto_deuda, detalles_deuda, propietario, cedula,
    numero_casa, referencia, estado, creado_por, revisado_por, fecha_creacion, fecha_revision
)
SELECT CASE MOD(n, 3) WHEN 0 THEN 'Baja' WHEN 1 THEN 'Renovacion' ELSE 'Alta' END,
       sc.id,
       CASE WHEN MOD(n, 3) = 0 THEN sv.id ELSE NULL END,
       CASE WHEN MOD(n, 3) = 0 THEN 20 + n ELSE 0 END,
       CASE WHEN MOD(n, 3) = 0 THEN 'Saldo pendiente acumulado de meses anteriores' ELSE NULL END,
       CONCAT('Familia solicitante ', n), CONCAT('58', LPAD(52000000 + n, 8, '0')), CONCAT(500 + n),
       'Solicitud generada durante la operación demo',
       CASE MOD(n, 4) WHEN 0 THEN 'Pendiente' WHEN 1 THEN 'Aprobado' WHEN 2 THEN 'Rechazado' ELSE 'Pendiente' END,
       COALESCE((SELECT id FROM usuarios WHERE rol_id = 6 ORDER BY id LIMIT 1), 1),
       CASE MOD(n, 4) WHEN 0 THEN NULL ELSE 5 END,
       CONCAT('2026-', LPAD(MOD(n - 1, 6) + 1, 2, '0'), '-', LPAD(MOD(n - 1, 26) + 1, 2, '0'), ' 11:20:00'),
       CASE MOD(n, 4) WHEN 0 THEN NULL ELSE CONCAT('2026-', LPAD(MOD(n - 1, 6) + 1, 2, '0'), '-', LPAD(MOD(n - 1, 26) + 2, 2, '0'), ' 15:20:00') END
FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20) nums
JOIN seed_calles sc ON sc.orden = 1 + MOD(n - 1, (SELECT COUNT(*) FROM seed_calles))
JOIN seed_viviendas sv ON sv.orden = 1 + MOD(n - 1, (SELECT COUNT(*) FROM seed_viviendas));

INSERT INTO solicitudes_gestion (
    tipo, barrio_id, calle_id, solicitado_por, nombre_calle, tipo_via,
    descripcion_calle, nombre, apellido, email, dni, telefono, estado,
    motivo_rechazo, procesado_por, fecha_creacion, fecha_revision
)
SELECT CASE WHEN MOD(n, 2) = 0 THEN 'NuevaCalle' ELSE 'NuevoEncargadoCalle' END,
       sb.id,
       CASE WHEN MOD(n, 2) = 0 THEN NULL ELSE sc.id END,
       COALESCE((SELECT id FROM usuarios WHERE rol_id = 5 ORDER BY id LIMIT 1), 1),
       CASE WHEN MOD(n, 2) = 0 THEN CONCAT('Calle propuesta ', n) ELSE NULL END,
       'Calle', 'Solicitud de gestión generada durante la operación demo.',
       CASE WHEN MOD(n, 2) = 0 THEN NULL ELSE CONCAT('Encargado ', n) END,
       CASE WHEN MOD(n, 2) = 0 THEN NULL ELSE 'Demo' END,
       CASE WHEN MOD(n, 2) = 0 THEN NULL ELSE CONCAT('encargado', n, '@example.com') END,
       CASE WHEN MOD(n, 2) = 0 THEN NULL ELSE CONCAT('60', LPAD(30000000 + n, 8, '0')) END,
       CASE WHEN MOD(n, 2) = 0 THEN NULL ELSE CONCAT('98', LPAD(7300000 + n, 7, '0')) END,
       CASE MOD(n, 3) WHEN 0 THEN 'Pendiente' WHEN 1 THEN 'Aprobada' ELSE 'Rechazada' END,
       CASE WHEN MOD(n, 3) = 2 THEN 'La solicitud requiere información adicional.' ELSE NULL END,
       CASE WHEN MOD(n, 3) = 0 THEN NULL ELSE 2 END,
       CONCAT('2026-', LPAD(MOD(n - 1, 6) + 1, 2, '0'), '-', LPAD(MOD(n - 1, 26) + 1, 2, '0'), ' 12:00:00'),
       CASE WHEN MOD(n, 3) = 0 THEN NULL ELSE CONCAT('2026-', LPAD(MOD(n - 1, 6) + 1, 2, '0'), '-', LPAD(MOD(n - 1, 26) + 2, 2, '0'), ' 16:00:00') END
FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12) nums
JOIN seed_barrios sb ON sb.orden = 1 + MOD(n - 1, (SELECT COUNT(*) FROM seed_barrios))
JOIN seed_calles sc ON sc.orden = 1 + MOD(n - 1, (SELECT COUNT(*) FROM seed_calles));

-- Exenciones nuevas sin duplicar la vivienda ya exonerada.
INSERT INTO exenciones_cobro (
    vivienda_id, calle_id, barrio_id, tipo_exencion, descripcion, creado_por,
    estado, aprobado_por, motivo_rechazo, fecha_creacion, fecha_revision
)
SELECT v.id, v.calle_id, v.barrio_id,
       CASE MOD(v.id, 3) WHEN 0 THEN 'adulto_mayor' WHEN 1 THEN 'pobreza' ELSE 'otro' END,
       'Solicitud de exención generada durante la operación demo.',
       COALESCE((SELECT id FROM usuarios WHERE rol_id = 5 ORDER BY id LIMIT 1), 1),
       CASE MOD(v.id, 3) WHEN 0 THEN 'Pendiente' WHEN 1 THEN 'Aprobado' ELSE 'Rechazado' END,
       CASE WHEN MOD(v.id, 3) = 0 THEN NULL ELSE 1 END,
       CASE WHEN MOD(v.id, 3) = 2 THEN 'Documentación incompleta.' ELSE NULL END,
       '2026-05-15 09:00:00',
       CASE WHEN MOD(v.id, 3) = 0 THEN NULL ELSE '2026-05-20 14:00:00' END
FROM viviendas v
LEFT JOIN exenciones_cobro e ON e.vivienda_id = v.id
WHERE e.id IS NULL AND MOD(v.id, 7) = 0
LIMIT 8;

-- Normalizar fechas demo que podrían caer en días inexistentes (por ejemplo, 30 de febrero).
UPDATE recaudaciones
SET fecha_recaudacion = CONCAT('2026-', LPAD(MOD(id - 1, 6) + 1, 2, '0'), '-27 10:00:00')
WHERE fecha_recaudacion = '0000-00-00 00:00:00';

UPDATE lotes_calle
SET fecha_envio = CONCAT('2026-', LPAD(MOD(id - 1, 6) + 1, 2, '0'), '-28 12:00:00')
WHERE fecha_envio = '0000-00-00 00:00:00';

UPDATE lotes_calle
SET fecha_evaluacion = CONCAT('2026-', LPAD(MOD(id - 1, 6) + 1, 2, '0'), '-28 16:00:00')
WHERE fecha_evaluacion = '0000-00-00 00:00:00';

UPDATE lotes_barrio
SET fecha_envio = CONCAT('2026-', LPAD(MOD(id - 1, 6) + 1, 2, '0'), '-28 12:00:00')
WHERE fecha_envio = '0000-00-00 00:00:00';

UPDATE lotes_barrio
SET fecha_aprobacion = CONCAT('2026-', LPAD(MOD(id - 1, 6) + 1, 2, '0'), '-28 16:00:00')
WHERE fecha_aprobacion = '0000-00-00 00:00:00';

UPDATE recibos_finiquito
SET fecha_emision = CONCAT('2026-', LPAD(MOD(id - 1, 6) + 1, 2, '0'), '-28 09:00:00')
WHERE fecha_emision = '0000-00-00 00:00:00';


COMMIT;
