<?php
// views/barrio/ordenes_baja.php
$user = check_dashboard_access([5]);

$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// 1. Obtener solicitudes de BAJA únicamente
$sql = "SELECT s.*, c.nombre as calle_nombre, u.nombre as solicitante_nombre, u.apellido as solicitante_apellido
        FROM solicitudes_vivienda s 
        JOIN calles c ON s.calle_id = c.id
        JOIN usuarios u ON s.creado_por = u.id
        JOIN detalles_encargado_barrio deb ON c.barrio_id = deb.barrio_id
        WHERE deb.usuario_id = :usuario AND s.tipo = 'Baja' AND s.estado = 'Pendiente'";
$params = [':usuario' => $user['id']];
if ($f_buscar !== '') {
    $sql .= " AND (s.propietario LIKE :q_propietario OR s.cedula LIKE :q_cedula OR s.otro_identificador LIKE :q_otro
                   OR s.numero_casa LIKE :q_casa OR c.nombre LIKE :q_calle)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
    $params[':q_calle'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sql .= " AND DATE(s.fecha_creacion) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(s.fecha_creacion) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= " ORDER BY s.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$metaOrdenes = [
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasOrdenes = [];
$totalDeudaOrdenes = 0.0;
foreach ($ordenes as $o) {
    $vStmt = $pdo->prepare("SELECT propietario, numero_casa, cedula, cedula_tipo, otro_identificador FROM viviendas WHERE id = ?");
    $vStmt->execute([$o['vivienda_id']]);
    $v = $vStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalDeudaOrdenes += (float)($o['monto_deuda'] ?? 0);
    $filasOrdenes[] = [
        date('d/m/Y H:i', strtotime($o['fecha_creacion'])),
        $o['calle_nombre'] ?? '',
        '#' . ($v['numero_casa'] ?? ''),
        $v['propietario'] ?? '',
        formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''),
        number_format((float)($o['monto_deuda'] ?? 0), 2, '.', ','),
        $o['detalles_deuda'] ?? '',
        trim(($o['solicitante_nombre'] ?? '') . ' ' . ($o['solicitante_apellido'] ?? '')),
    ];
}
reporte_exportar_si_solicitado(
    'ordenes_baja',
    'Órdenes de baja pendientes',
    ['Fecha', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Deuda pendiente', 'Detalles', 'Solicitante'],
    $filasOrdenes,
    array_merge($metaOrdenes, ['Total órdenes' => count($filasOrdenes), 'Deuda total' => number_format($totalDeudaOrdenes, 2, '.', ',')]),
    'Retiros de servicio pendientes de confirmación'
);

$title = "Órdenes de Baja - EcoCusco";
$header_title = "Ejecución de Bajas";
$header_subtitle = "Confirma el retiro del servicio para las viviendas solicitadas por los encargados de calle.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Órdenes de baja pendientes', 'Retiros de servicio pendientes de confirmación', array_merge($metaOrdenes, ['Total órdenes' => count($ordenes), 'Deuda total' => 'S/ ' . number_format($totalDeudaOrdenes, 2, '.', ',')])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(200px,1.5fr) repeat(2,minmax(140px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="ordenes_baja">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, casa..."
                   style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div style="display:flex; gap:6px; align-items:center;">
            <button type="submit" class="btn-primary" style="padding:9px 12px;">Filtrar</button>
            <a href="router.php?page=ordenes_baja" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card" style="border-left: 4px solid #9A6A6A;">
        <h3 style="margin-top:0;">🛑 Solicitudes de Baja Pendientes</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Al confirmar, el servicio se anulará en el sistema y se registrará la deuda final.
        </p>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha / Calle</th>
                        <th style="padding: 12px;">Propietario / Vivienda</th>
                        <th style="padding: 12px; text-align: right;">Deuda Pendiente</th>
                        <th style="padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ordenes)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay órdenes de baja pendientes.</td></tr>
                    <?php endif; ?>
                    <?php foreach($ordenes as $o): ?>
                        <tr id="solicitud-<?= (int)$o['id'] ?>" style="border-bottom: 1px solid #F3F4F6;<?= (int)($_GET['solicitud_id'] ?? 0) === (int)$o['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding: 12px;">
                                <div style="font-weight: 700; color: #111827;"><?= htmlspecialchars($o['calle_nombre']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;"><?= date('d/m/Y H:i', strtotime($o['fecha_creacion'])) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <?php 
                                    $vStmt = $pdo->prepare("SELECT propietario, numero_casa, cedula, cedula_tipo, otro_identificador FROM viviendas WHERE id = ?");
                                    $vStmt->execute([$o['vivienda_id']]);
                                    $v = $vStmt->fetch();
                                ?>
                                <div style="font-weight: 700;"><?= htmlspecialchars($v['propietario'] ?? 'N/A') ?></div>
                                <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?><div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                                <div style="font-size: 11px; color: #6B7280;">Casa #<?= htmlspecialchars($v['numero_casa'] ?? '#') ?> (ID: <?= $o['vivienda_id'] ?>)</div>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <div style="font-weight: 800; color: #7A4F4F;">S/ <?= number_format($o['monto_deuda'], 2) ?></div>
                                <div style="font-size: 9px; color: #6B7280;"><?= htmlspecialchars($o['detalles_deuda']) ?></div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <form method="POST" onsubmit="return confirm('¿CONFIRMAR BAJA DE SERVICIO? El usuario ya no contará con el servicio de recolección.')">
                                    <input type="hidden" name="form_type" value="procesar_solicitud">
                                    <input type="hidden" name="solicitud_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="estado" value="Aprobado">
                                    <button type="submit" class="btn-primary" style="background: #111827; color: white; border: none; padding: 8px 15px; font-size: 11px; font-weight: 700;">Realizar Baja</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
