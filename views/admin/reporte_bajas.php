<?php
// views/admin/reporte_bajas.php
$user = check_dashboard_access([1]);

$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_barrio = (int)($_GET['barrio_id'] ?? 0);
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));
$barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// 1. Obtener todas las viviendas anuladas del sistema
$sql = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa, c.nombre as calle_nombre, b.nombre as barrio_nombre,
               s.monto_deuda, s.detalles_deuda, s.fecha_revision as fecha_baja, u.nombre as ejecutor_nombre
        FROM viviendas v 
        JOIN calles c ON v.calle_id = c.id
        JOIN barrios b ON v.barrio_id = b.id
        JOIN solicitudes_vivienda s ON v.id = s.vivienda_id
        LEFT JOIN usuarios u ON s.revisado_por = u.id
        WHERE v.estado_servicio = 'Anulado' AND s.tipo = 'Baja' AND s.estado = 'Aprobado'";
$params = [];
if ($f_buscar !== '') {
    $sql .= " AND (v.propietario LIKE :q_propietario OR v.cedula LIKE :q_cedula OR v.otro_identificador LIKE :q_otro
                   OR c.nombre LIKE :q_calle OR b.nombre LIKE :q_barrio OR v.numero_casa LIKE :q_casa)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_calle'] = '%' . $f_buscar . '%';
    $params[':q_barrio'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
}
if ($f_barrio > 0) {
    $sql .= " AND b.id = :barrio";
    $params[':barrio'] = $f_barrio;
}
if ($f_desde !== '') {
    $sql .= " AND DATE(s.fecha_revision) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(s.fecha_revision) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= " ORDER BY s.fecha_revision DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$barrioNombreFiltro = '';
foreach ($barrios as $bb) {
    if ((int)$bb['id'] === $f_barrio) { $barrioNombreFiltro = $bb['nombre']; break; }
}
$metaBajas = [
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Barrio' => $barrioNombreFiltro !== '' ? $barrioNombreFiltro : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasBajas = [];
$totalDeudaBajas = 0.0;
foreach ($bajas as $b) {
    $totalDeudaBajas += (float)($b['monto_deuda'] ?? 0);
    $filasBajas[] = [
        !empty($b['fecha_baja']) ? date('d/m/Y', strtotime($b['fecha_baja'])) : '',
        $b['barrio_nombre'] ?? '',
        $b['calle_nombre'] ?? '',
        '#' . ($b['numero_casa'] ?? ''),
        $b['propietario'] ?? '',
        formato_identificacion($b['cedula'] ?? '', $b['cedula_tipo'] ?? 'V', $b['otro_identificador'] ?? ''),
        monto((float)($b['monto_deuda'] ?? 0)),
        $b['detalles_deuda'] ?? '',
        $b['ejecutor_nombre'] ?? '',
    ];
}
reporte_exportar_si_solicitado(
    'reporte_bajas',
    'Reporte global de bajas',
    ['Fecha de baja', 'Barrio', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Deuda final', 'Detalles de deuda', 'Autorizado por'],
    $filasBajas,
    array_merge($metaBajas, ['Total bajas' => count($filasBajas), 'Deuda total' => monto($totalDeudaBajas)]),
    'Historial de servicios anulados y deudas pendientes'
);

$title = "Reporte Global de Bajas - EcoReciclaje";
$header_title = "Auditoría de Retiros";
$header_subtitle = "Historial completo de servicios anulados y deudas pendientes en todo el sistema.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Reporte global de bajas', 'Historial de servicios anulados y deudas pendientes', array_merge($metaBajas, ['Total bajas' => count($bajas), 'Deuda total' => monto($totalDeudaBajas)])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(200px,1.5fr) repeat(3,minmax(130px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="reporte_bajas">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, casa..."
                   style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Barrio</label>
            <select name="barrio_id" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
                <option value="0">Todos</option>
                <?php foreach ($barrios as $bb): ?>
                    <option value="<?= (int)$bb['id'] ?>" <?= $f_barrio === (int)$bb['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bb['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
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
            <h3 style="margin: 0;">📊 Reporte Consolidado</h3>
            <span class="badge no-print" style="background:#F3F4F6; color:#374151;"><?= count($bajas) ?> baja(s)</span>
        </div>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha / Barrio</th>
                        <th style="padding: 12px;">Vivienda / Calle</th>
                        <th style="padding: 12px; text-align: right;">Deuda Final</th>
                        <th style="padding: 12px;">Autorizado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($bajas)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No se registran bajas que coincidan con los filtros.</td></tr>
                    <?php endif; ?>
                    <?php foreach($bajas as $b): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-weight: 700; color: #33485C;"><?= htmlspecialchars($b['barrio_nombre']) ?></div>
                                <div style="font-size: 11px; color: #9CA3AF;"><?= date('d/m/Y', strtotime($b['fecha_baja'])) ?></div>
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
                                <div style="font-weight: 800; color: #7A4F4F;"><?= monto($b['monto_deuda']) ?></div>
                                <div style="font-size: 10px; color: #6B7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;"><?= htmlspecialchars($b['detalles_deuda']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <span class="badge" style="background: #F3F4F6; color: #374151;"><?= htmlspecialchars($b['ejecutor_nombre'] ?: 'N/A') ?></span>
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
