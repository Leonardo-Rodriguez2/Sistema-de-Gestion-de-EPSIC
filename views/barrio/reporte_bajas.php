<?php
// views/barrio/reporte_bajas.php
$user = check_dashboard_access([5]);

$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// 1. Obtener viviendas anuladas y su deuda registrada en la solicitud aprobada
$sql = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa, v.direccion, c.nombre as calle_nombre,
               s.monto_deuda, s.detalles_deuda, s.fecha_revision as fecha_baja
        FROM viviendas v 
        JOIN calles c ON v.calle_id = c.id
        JOIN solicitudes_vivienda s ON v.id = s.vivienda_id
        JOIN detalles_encargado_barrio deb ON v.barrio_id = deb.barrio_id
        WHERE deb.usuario_id = :usuario AND v.estado_servicio = 'Anulado' AND s.tipo = 'Baja' AND s.estado = 'Aprobado'
        AND v.id NOT IN (SELECT vivienda_id FROM solicitudes_vivienda WHERE tipo = 'Renovacion' AND estado = 'Pendiente' AND vivienda_id IS NOT NULL)";
$params = [':usuario' => $user['id']];
if ($f_buscar !== '') {
    $sql .= " AND (v.propietario LIKE :q_propietario OR v.cedula LIKE :q_cedula OR v.otro_identificador LIKE :q_otro
                   OR v.numero_casa LIKE :q_casa OR c.nombre LIKE :q_calle)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
    $params[':q_calle'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sql .= " AND DATE(s.fecha_revision) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(s.fecha_revision) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= " GROUP BY v.id ORDER BY s.fecha_revision DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$metaBajas = [
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasBajas = [];
$totalDeudaBajas = 0.0;
foreach ($bajas as $b) {
    $totalDeudaBajas += (float)$b['monto_deuda'];
    $filasBajas[] = [
        !empty($b['fecha_baja']) ? date('d/m/Y H:i', strtotime($b['fecha_baja'])) : '',
        $b['calle_nombre'] ?? '',
        '#' . ($b['numero_casa'] ?? ''),
        $b['propietario'] ?? '',
        formato_identificacion($b['cedula'] ?? '', $b['cedula_tipo'] ?? 'V', $b['otro_identificador'] ?? ''),
        $b['direccion'] ?? '',
        number_format((float)$b['monto_deuda'], 2, '.', ','),
        $b['detalles_deuda'] ?? '',
    ];
}
reporte_exportar_si_solicitado(
    'reporte_bajas_barrio',
    'Reporte de bajas del barrio',
    ['Fecha de baja', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Dirección', 'Deuda final', 'Conceptos pendientes'],
    $filasBajas,
    array_merge($metaBajas, ['Total bajas' => count($filasBajas), 'Deuda total' => number_format($totalDeudaBajas, 2, '.', ',')]),
    'Viviendas retiradas del servicio y estado de cuenta final'
);

$title = "Reporte de Bajas - EcoCusco";
$header_title = "Historial de Bajas";
$header_subtitle = "Viviendas que salieron del sistema y su estado de cuenta final.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Reporte de bajas del barrio', 'Viviendas retiradas del servicio y estado de cuenta final', array_merge($metaBajas, ['Total bajas' => count($bajas), 'Deuda total' => 'S/ ' . number_format($totalDeudaBajas, 2, '.', ',')])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(200px,1.5fr) repeat(2,minmax(140px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="reporte_bajas">
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
            <a href="router.php?page=reporte_bajas" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3 style="margin: 0;">📉 Registro de Retiros</h3>
            <span class="badge no-print" style="background:#F3F4F6; color:#374151;"><?= count($bajas) ?> baja(s)</span>
        </div>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha Baja</th>
                        <th style="padding: 12px;">Vivienda / Calle</th>
                        <th style="padding: 12px; text-align: right;">Deuda Final</th>
                        <th style="padding: 12px;">Conceptos Pendientes</th>
                        <th style="padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($bajas)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay registros de bajas confirmadas que coincidan con los filtros.</td></tr>
                    <?php endif; ?>
                    <?php foreach($bajas as $b): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-weight: 700; color: #4B5563;"><?= date('d/m/Y', strtotime($b['fecha_baja'])) ?></div>
                                <div style="font-size: 10px; color: #9CA3AF;"><?= date('H:i', strtotime($b['fecha_baja'])) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 700; color: #111827;"><?= htmlspecialchars($b['propietario']) ?></div>
                                <?php $identificacion = formato_identificacion($b['cedula'] ?? '', $b['cedula_tipo'] ?? 'V', $b['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: #6B7280;"><?= htmlspecialchars($b['calle_nombre']) ?> | Casa #<?= htmlspecialchars($b['numero_casa']) ?></div>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <div style="font-weight: 800; color: #7A4F4F; font-size: 15px;">S/ <?= number_format($b['monto_deuda'], 2) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-size: 11px; color: #4B5563; line-height: 1.4; max-width: 200px;">
                                    <?= htmlspecialchars($b['detalles_deuda'] ?: 'Sin detalles registrados') ?>
                                </div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <form method="POST" action="router.php" onsubmit="return confirm('¿RENOVAR SERVICIO DIRECTAMENTE? La vivienda volverá a estar activa.')">
                                    <input type="hidden" name="action" value="reactivar_servicio">
                                    <input type="hidden" name="page" value="reporte_bajas">
                                    <input type="hidden" name="vivienda_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn-primary" style="background:#111827; color:white; border:none; padding: 6px 12px; font-size:10px; cursor:pointer;">🔄 Renovar</button>
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
