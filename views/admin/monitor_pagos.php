<?php
// views/admin/monitor_pagos.php
$user = check_dashboard_access([1]);

// 1. Periodo consultado (por defecto, el mes actual)
$mes = (int)($_GET['mes'] ?? date('n'));
$anio = (int)($_GET['anio'] ?? date('Y'));
if ($mes < 1 || $mes > 12) $mes = (int)date('n');
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');
$barrio_id = (int)($_GET['barrio_id'] ?? 0);
$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$aniosStmt = $pdo->query("SELECT DISTINCT anio FROM cobros WHERE anio IS NOT NULL ORDER BY anio DESC");
$aniosDisponibles = array_map('intval', array_column($aniosStmt->fetchAll(PDO::FETCH_ASSOC), 'anio'));
if (!in_array((int)date('Y'), $aniosDisponibles, true)) array_unshift($aniosDisponibles, (int)date('Y'));
$aniosDisponibles = array_values(array_unique($aniosDisponibles));
rsort($aniosDisponibles);

$sql = "SELECT b.id, b.nombre,
          (SELECT COUNT(*) FROM viviendas WHERE barrio_id = b.id AND estado_servicio <> 'Anulado') as total_casas,
          (SELECT COUNT(*) FROM cobros c JOIN viviendas v ON c.vivienda_id = v.id 
           WHERE v.barrio_id = b.id AND c.mes = :mes_pagadas AND c.anio = :anio_pagadas AND c.estado = 'Pagado' AND c.tipo_cobro = 'Servicio') as casas_pagadas,
          (SELECT SUM(monto) FROM cobros c JOIN viviendas v ON c.vivienda_id = v.id 
           WHERE v.barrio_id = b.id AND c.mes = :mes_total AND c.anio = :anio_total AND c.estado = 'Pagado') as total_recaudado
        FROM barrios b";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':mes_pagadas' => $mes,
    ':anio_pagadas' => $anio,
    ':mes_total' => $mes,
    ':anio_total' => $anio,
]);
$resumen_barrios = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($resumen_barrios as &$bResumen) {
    $bResumen['cumplimiento'] = ((int)$bResumen['total_casas'] > 0)
        ? round(((int)$bResumen['casas_pagadas'] / (int)$bResumen['total_casas']) * 100)
        : 0;
    $bResumen['pendientes'] = max(0, (int)$bResumen['total_casas'] - (int)$bResumen['casas_pagadas']);
}
unset($bResumen);

// Detalle de morosos para el barrio seleccionado.
$bName = '';
$morosos = [];
if ($barrio_id > 0) {
    $bNameStmt = $pdo->prepare("SELECT nombre FROM barrios WHERE id = ?");
    $bNameStmt->execute([$barrio_id]);
    $bName = (string)($bNameStmt->fetchColumn() ?: '');

    $sqlMorosos = "SELECT v.*, c.nombre as calle_nombre 
                   FROM viviendas v 
                   LEFT JOIN calles c ON v.calle_id = c.id
                   WHERE v.barrio_id = ? 
                   AND v.id NOT IN (SELECT vivienda_id FROM cobros WHERE mes = ? AND anio = ? AND estado = 'Pagado' AND tipo_cobro = 'Servicio')
                   ORDER BY c.nombre, v.numero_casa";
    $stmtM = $pdo->prepare($sqlMorosos);
    $stmtM->execute([$barrio_id, $mes, $anio]);
    $morosos = $stmtM->fetchAll(PDO::FETCH_ASSOC);
}

// Exportación: detalle de morosos si hay barrio seleccionado; si no, resumen.
$metaMonitor = [
    'Periodo' => $meses[$mes] . ' ' . $anio,
    'Barrio' => $bName !== '' ? $bName : null,
];
if ($barrio_id > 0) {
    $filasMonitor = [];
    foreach ($morosos as $m) {
        $filasMonitor[] = [
            $m['calle_nombre'] ?? '',
            $m['propietario'] ?? '',
            formato_identificacion($m['cedula'] ?? '', $m['cedula_tipo'] ?? 'V', $m['otro_identificador'] ?? ''),
            '#' . ($m['numero_casa'] ?? ''),
            $m['direccion'] ?? '',
            $m['estado_servicio'] ?? '',
        ];
    }
    reporte_exportar_si_solicitado(
        'monitor_pagos_morosos',
        'Viviendas pendientes de pago',
        ['Calle', 'Propietario', 'Identificación', 'Casa', 'Dirección', 'Estado del servicio'],
        $filasMonitor,
        array_merge($metaMonitor, ['Viviendas pendientes' => count($filasMonitor)]),
        'Detalle de viviendas sin pago en el periodo'
    );
} else {
    $filasMonitor = [];
    foreach ($resumen_barrios as $b) {
        $filasMonitor[] = [
            $b['nombre'],
            (int)$b['total_casas'],
            (int)$b['casas_pagadas'],
            (int)$b['pendientes'],
            $b['cumplimiento'] . '%',
            number_format((float)($b['total_recaudado'] ?? 0), 2, '.', ','),
        ];
    }
    reporte_exportar_si_solicitado(
        'monitor_pagos_resumen',
        'Monitor de pagos por barrio',
        ['Barrio', 'Casas', 'Casas pagadas', 'Pendientes', 'Cumplimiento', 'Recaudado'],
        $filasMonitor,
        array_merge($metaMonitor, ['Total barrios' => count($filasMonitor)]),
        'Resumen de cumplimiento y recaudación por barrio'
    );
}

$title = "Monitor de Pagos - EPSIC";
$header_title = "Fiscalización de Ingresos";
$header_subtitle = "Seguimiento en tiempo real del cumplimiento por barrio.";

ob_start();
?>
    <?= reporte_encabezado_impresion('Monitor de pagos', 'Fiscalización de ingresos y cumplimiento por barrio', $metaMonitor) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
        <input type="hidden" name="page" value="monitor_pagos">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Mes</label>
            <select name="mes" style="padding:9px 10px; border:1px solid #D1D5DB; font-size:11px; min-width:130px;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Año</label>
            <select name="anio" style="padding:9px 10px; border:1px solid #D1D5DB; font-size:11px; min-width:100px;">
                <?php foreach ($aniosDisponibles as $anioOpcion): ?>
                    <option value="<?= (int)$anioOpcion ?>" <?= $anio === (int)$anioOpcion ? 'selected' : '' ?>><?= (int)$anioOpcion ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Barrio (detalle)</label>
            <select name="barrio_id" style="padding:9px 10px; border:1px solid #D1D5DB; font-size:11px; min-width:170px;">
                <option value="0">Solo resumen general</option>
                <?php foreach ($resumen_barrios as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $barrio_id === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary" style="padding:9px 14px;">Consultar</button>
    </form>

    <div class="card" style="margin-bottom: 25px;">
        <h3 style="margin-top:0;">🌐 Estado General · <?= htmlspecialchars($meses[$mes]) ?> <?= (int)$anio ?></h3>
            <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 15px;">Barrio</th>
                        <th style="padding: 15px; text-align: center;">Cumplimiento</th>
                        <th style="padding: 15px; text-align: center;">Casas Pagadas</th>
                        <th style="padding: 15px; text-align: center;">Total Recaudado</th>
                        <th style="padding: 15px; text-align: center;">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($resumen_barrios as $b): 
                        $porc = $b['cumplimiento'];
                        $color = ($porc >= 80) ? '#4C6E91' : (($porc >= 50) ? '#8A7A5A' : '#9A6A6A');
                    ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 15px; font-weight: 700;"><?= htmlspecialchars($b['nombre']) ?></td>
                            <td style="padding: 15px; text-align: center;">
                                <div style="display: flex; align-items: center; gap: 10px; justify-content: center;">
                                    <div style="width: 100px; height: 8px; background: #F3F4F6; border-radius: 10px; overflow: hidden;">
                                        <div style="width: <?= $porc ?>%; height: 100%; background: <?= $color ?>;"></div>
                                    </div>
                                    <span style="font-weight: 800; color: <?= $color ?>;"><?= $porc ?>%</span>
                                </div>
                            </td>
                            <td style="padding: 15px; text-align: center; font-weight: 600;">
                                <?= (int)$b['casas_pagadas'] ?> / <?= (int)$b['total_casas'] ?>
                            </td>
                            <td style="padding: 15px; text-align: center; font-weight: 800; color: #3E5C76;">
                                S/ <?= number_format($b['total_recaudado'] ?? 0, 2) ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <a href="router.php?page=monitor_pagos&barrio_id=<?= (int)$b['id'] ?>&mes=<?= (int)$mes ?>&anio=<?= (int)$anio ?>#detalle" class="badge no-print" style="background: #E9EFF4; color: #3E5C76; text-decoration: none;">Ver Morosos</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($barrio_id > 0): ?>
        <div id="detalle" class="card" style="border-top: 4px solid #9A6A6A;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #9A6A6A;">🚨 Viviendas Pendientes: <?= htmlspecialchars($bName) ?></h3>
                <span class="badge" style="background: #F4EDED; color: #7A4F4F; font-weight: 800;"><?= count($morosos) ?> casas sin pago</span>
            </div>
<div class="table-wrap">
                <table class="table-mini" style="width: 100%;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                            <th>Calle</th>
                            <th>Propietario / Casa</th>
                            <th>Dirección</th>
                            <th style="text-align: center;">Estado Servicio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($morosos as $m): ?>
                            <tr style="border-bottom: 1px solid #F3F4F6;">
                                <td style="padding: 10px; font-weight: 600;"><?= htmlspecialchars($m['calle_nombre'] ?? 'Sin calle') ?></td>
                                <td style="padding: 10px;">
                                    <strong><?= htmlspecialchars($m['propietario']) ?></strong><br>
                                    <?php $identificacion = formato_identificacion($m['cedula'] ?? '', $m['cedula_tipo'] ?? 'V', $m['otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?>
                                        <small style="color:#6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></small><br>
                                    <?php endif; ?>
                                    <small>Casa #<?= htmlspecialchars($m['numero_casa']) ?></small>
                                </td>
                                <td style="padding: 10px; color: #6B7280; font-size: 11px;"><?= htmlspecialchars($m['direccion']) ?></td>
                                <td style="padding: 10px; text-align: center;">
                                    <?php $isSusp = ($m['estado_servicio'] == 'Suspendido'); ?>
                                    <span class="badge" style="background: <?= $isSusp ? '#F4EDED' : '#F2EEE7' ?>; color: <?= $isSusp ? '#7A4F4F' : '#6E5F45' ?>;">
                                        <?= htmlspecialchars($m['estado_servicio']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($morosos)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 20px; color: #4C6E91; font-weight: 700;">¡Todo el barrio está al día! 👏</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
