<?php
// Esta ruta histórica quedó como alias para evitar dos pantallas distintas
// de verificación con consultas y resultados diferentes.
$sid = urlencode((string)($_SESSION['active_sid'] ?? 'main'));
header('Location: router.php?page=reportar_pago&sid=' . $sid);
exit;

global $pdo;

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use app\models\mainModel;

if (empty($pdo)) {
    $pdo = (new mainModel())->conectar();
}

$user = check_dashboard_access([5]);
$page = 'seguimiento_pagos';
$sid = $_SESSION['active_sid'] ?? 'main';

$barrioStmt = $pdo->prepare("SELECT b.id, b.nombre FROM detalles_encargado_barrio d JOIN barrios b ON d.barrio_id = b.id WHERE d.usuario_id = ?");
$barrioStmt->execute([$user['id']]);
$barrio = $barrioStmt->fetch(PDO::FETCH_ASSOC);
$barrio_id = (int)($barrio['id'] ?? 0);
$barrio_nombre = $barrio['nombre'] ?? 'Sin barrio asignado';

$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$estadosPermitidos = ['', 'Pagado', 'Pendiente', 'Vencido', 'Sin registro'];

// ─── Filtros ────────────────────────────────────────────────────────────────
$f_q       = trim((string)($_GET['q'] ?? ''));
$f_estado  = (string)($_GET['estado'] ?? '');
$f_mes     = (int)($_GET['mes'] ?? 0);
$f_anio    = (int)($_GET['anio'] ?? 0);
$f_calle   = (int)($_GET['calle_id'] ?? 0);

if (!in_array($f_estado, $estadosPermitidos, true)) $f_estado = '';
if ($f_mes < 0 || $f_mes > 12) $f_mes = 0;
if ($f_anio < 2000 || $f_anio > 2100) $f_anio = 0;

$callesStmt = $pdo->prepare("SELECT id, nombre FROM calles WHERE barrio_id = ? ORDER BY nombre ASC");
$callesStmt->execute([$barrio_id]);
$calles = $callesStmt->fetchAll(PDO::FETCH_ASSOC);

$aniosStmt = $pdo->prepare("
    SELECT DISTINCT c.anio
    FROM cobros c
    JOIN viviendas v ON v.id = c.vivienda_id
    WHERE v.barrio_id = ? AND c.anio IS NOT NULL
    ORDER BY c.anio DESC
");
$aniosStmt->execute([$barrio_id]);
$anios = array_map('intval', array_column($aniosStmt->fetchAll(PDO::FETCH_ASSOC), 'anio'));
if (!in_array((int)date('Y'), $anios, true)) {
    array_unshift($anios, (int)date('Y'));
}
$anios = array_values(array_unique($anios));
rsort($anios);

// ─── Consulta ───────────────────────────────────────────────────────────────
$where = ['v.barrio_id = :barrio'];
$params = [':barrio' => $barrio_id];

$joinCobro = "LEFT JOIN cobros c ON c.vivienda_id = v.id AND c.estado <> 'Anulado'";
if ($f_mes > 0) {
    $joinCobro .= " AND c.mes = :mes";
    $params[':mes'] = $f_mes;
}
if ($f_anio > 0) {
    $joinCobro .= " AND c.anio = :anio";
    $params[':anio'] = $f_anio;
}

$sql = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador,
               v.numero_casa, v.direccion, v.estado_servicio,
               cal.nombre AS calle_nombre,
               c.id AS cobro_id, c.monto, c.estado, c.tipo_cobro, c.mes, c.anio,
               c.comprobante_calle, c.comprobante_barrio, c.observaciones,
               c.fecha_confirmacion_calle, c.fecha_confirmacion_barrio
        FROM viviendas v
        LEFT JOIN calles cal ON cal.id = v.calle_id
        {$joinCobro}
        WHERE " . implode(' AND ', $where);

if ($f_calle > 0) {
    $sql .= " AND v.calle_id = :calle";
    $params[':calle'] = $f_calle;
}

if ($f_q !== '') {
    $sql .= " AND (
        v.propietario LIKE :q_propietario
        OR v.cedula LIKE :q_cedula
        OR v.otro_identificador LIKE :q_otro
        OR v.numero_casa LIKE :q_casa
        OR v.direccion LIKE :q_direccion
        OR cal.nombre LIKE :q_calle
    )";
    $params[':q_propietario'] = '%' . $f_q . '%';
    $params[':q_cedula'] = '%' . $f_q . '%';
    $params[':q_otro'] = '%' . $f_q . '%';
    $params[':q_casa'] = '%' . $f_q . '%';
    $params[':q_direccion'] = '%' . $f_q . '%';
    $params[':q_calle'] = '%' . $f_q . '%';
}

if ($f_estado === 'Pagado') {
    $sql .= " AND c.estado = 'Pagado'";
} elseif ($f_estado === 'Pendiente') {
    $sql .= " AND c.estado IN ('Pendiente', 'Vencido')";
} elseif ($f_estado === 'Vencido') {
    $sql .= " AND c.estado = 'Vencido'";
} elseif ($f_estado === 'Sin registro') {
    $sql .= " AND c.id IS NULL";
}

$sql .= " ORDER BY v.numero_casa + 0 ASC, v.numero_casa ASC, c.anio DESC, c.mes DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$casas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Resumen del conjunto filtrado ─────────────────────────────────────────
$stats = [
    'registros' => count($casas),
    'casas' => 0,
    'pagadas' => 0,
    'pendientes' => 0,
    'recaudado' => 0.0,
    'pendiente_monto' => 0.0,
];
$casasUnicas = [];
$casasPagadas = [];
$casasPendientes = [];
foreach ($casas as $fila) {
    $vid = (int)$fila['id'];
    $casasUnicas[$vid] = true;
    $estadoCobro = $fila['estado'] ?? '';
    if ($estadoCobro === 'Pagado') {
        $casasPagadas[$vid] = true;
        $stats['recaudado'] += (float)($fila['monto'] ?? 0);
    } elseif (!empty($fila['cobro_id'])) {
        $casasPendientes[$vid] = true;
        $stats['pendiente_monto'] += (float)($fila['monto'] ?? 0);
    }
}
$stats['casas'] = count($casasUnicas);
$stats['pagadas'] = count($casasPagadas);
$stats['pendientes'] = count($casasPendientes);

$filtrosMeta = [
    'Barrio' => $barrio_nombre,
    'Buscar' => $f_q !== '' ? $f_q : null,
    'Estado' => $f_estado !== '' ? $f_estado : null,
    'Periodo' => ($f_mes > 0 || $f_anio > 0)
        ? trim(($f_mes > 0 ? $meses[$f_mes] : '') . ' ' . ($f_anio > 0 ? $f_anio : ''))
        : 'Todos los periodos',
    'Calle' => $f_calle > 0 ? (array_column($calles, 'nombre', 'id')[$f_calle] ?? '') : null,
];

// ─── Exportación a Excel ────────────────────────────────────────────────────
$filasExcel = [];
foreach ($casas as $fila) {
    $identificacion = formato_identificacion($fila['cedula'] ?? '', $fila['cedula_tipo'] ?? 'V', $fila['otro_identificador'] ?? '');
    $estadoCobro = $fila['estado'] ?? 'Sin registro';
    $comprobante = (!empty($fila['comprobante_barrio']) || !empty($fila['comprobante_calle'])) ? 'Sí' : 'No';
    $filasExcel[] = [
        '#' . ($fila['numero_casa'] ?? ''),
        $fila['propietario'] ?? '',
        $identificacion,
        $fila['calle_nombre'] ?? '',
        $fila['direccion'] ?? '',
        ($fila['mes'] ?? '') ? ($fila['mes'] . '/' . $fila['anio']) : 'Sin periodo',
        $estadoCobro,
        $fila['monto'] !== null ? number_format((float)$fila['monto'], 2, '.', ',') : '',
        $comprobante,
        $fila['observaciones'] ?? '',
    ];
}

reporte_exportar_si_solicitado(
    'seguimiento_por_casa_' . $barrio_nombre,
    'Seguimiento por Casa',
    ['Casa', 'Propietario', 'Identificación', 'Calle', 'Dirección', 'Periodo', 'Estado', 'Monto (Bs.)', 'Comprobante', 'Observaciones'],
    $filasExcel,
    array_merge($filtrosMeta, [
        'Casas' => $stats['casas'],
        'Casas con pago' => $stats['pagadas'],
        'Casas con pendientes' => $stats['pendientes'],
        'Recaudado' => 'Bs. ' . number_format($stats['recaudado'], 2, '.', ','),
        'Saldo pendiente' => 'Bs. ' . number_format($stats['pendiente_monto'], 2, '.', ','),
    ]),
    'Listado de pagos y comprobantes por vivienda'
);

$title = "Seguimiento de Pagos - EcoCusco";
$header_title = "Seguimiento por Casa";
$header_subtitle = "Revisa qué casas pagaron, cuáles faltan y qué evidencia se recibió.";

$metaImpresion = $filtrosMeta;
$metaImpresion['Casas'] = $stats['casas'];
$metaImpresion['Recaudado'] = 'Bs. ' . number_format($stats['recaudado'], 2, '.', ',');

ob_start();
?>

<style>
.seg-toolbar { margin-bottom: 14px; }
.seg-filters {
    display: grid;
    grid-template-columns: minmax(190px, 1.35fr) repeat(5, minmax(120px, 1fr));
    gap: 12px;
    align-items: end;
    padding: 16px 18px;
    margin-bottom: 18px;
}
.seg-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.seg-field label {
    color: #4B5563; font-size: 9px; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase;
}
.seg-field input, .seg-field select {
    width: 100%; box-sizing: border-box; padding: 9px 10px;
    border: 1px solid #D1D5DB; background: #fff; color: #111827;
    font: inherit; font-size: 11px; min-height: 38px;
}
.seg-field input:focus, .seg-field select:focus {
    border-color: #176b87; outline: none;
    box-shadow: 0 0 0 3px rgba(23,107,135,.12);
}
.seg-actions { display: flex; gap: 6px; align-items: center; }
.seg-actions button {
    background: #176b87; color: #fff; border: none; padding: 9px 14px;
    font-size: 11px; font-weight: 700; cursor: pointer; min-height: 38px;
}
.seg-actions button:hover { background: #0e4f68; }
.seg-actions a {
    color: #6B7280; font-size: 11px; text-decoration: none;
    padding: 9px 4px; white-space: nowrap;
}
.seg-actions a:hover { color: #111827; text-decoration: underline; }
.seg-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}
.seg-stat { padding: 16px 18px; }
.seg-stat-label {
    color: #6B7280; font-size: 10px; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase; margin-bottom: 6px;
}
.seg-stat-value { color: #111827; font-size: 24px; line-height: 1.1; font-weight: 800; }
.seg-stat-note { color: #9CA3AF; font-size: 10px; margin-top: 5px; }
.seg-table-card { padding: 0; overflow: hidden; }
.seg-table-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap; padding: 16px 18px;
    border-bottom: 1px solid #E5E7EB;
}
.seg-table-head h3 { margin: 0; }
.seg-table-head span { color: #9CA3AF; font-size: 10px; }
.seg-table th { background: #F9FAFB; }
.seg-table td { vertical-align: top; }
.seg-casa { font-weight: 800; color: #111827; white-space: nowrap; }
.seg-prop { font-weight: 600; color: #111827; }
.seg-sub { color: #6B7280; font-size: 10px; margin-top: 2px; }
.seg-badge {
    display: inline-block; padding: 4px 8px; font-size: 10px;
    font-weight: 700; white-space: nowrap; border: 1px solid transparent;
}
.seg-badge-pagado { background: #E9EFF4; color: #33485C; border-color: #D6E1EA; }
.seg-badge-pendiente { background: #F7F4EE; color: #6E5F45; border-color: #E8E0CF; }
.seg-badge-vencido { background: #F4EDED; color: #7A4F4F; border-color: #E8D8D8; }
.seg-badge-sin { background: #F3F4F6; color: #6B7280; border-color: #E5E7EB; }
.seg-empty { text-align: center; padding: 42px 20px; color: #9CA3AF; }
.seg-empty strong { display: block; color: #374151; margin-bottom: 5px; font-size: 14px; }
@media (max-width: 1200px) {
    .seg-filters { grid-template-columns: repeat(3, minmax(130px, 1fr)); }
    .seg-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 700px) {
    .seg-filters { grid-template-columns: 1fr 1fr; }
    .seg-stats { grid-template-columns: 1fr; }
}
@media (max-width: 460px) {
    .seg-filters { grid-template-columns: 1fr; }
}
</style>

<?= reporte_encabezado_impresion('Seguimiento por Casa', 'Listado de pagos y comprobantes por vivienda', $metaImpresion) ?>

<div class="seg-toolbar">
    <?= reporte_botonera() ?>
</div>

<form method="GET" action="router.php" class="card seg-filters no-print">
    <input type="hidden" name="page" value="seguimiento_pagos">
    <input type="hidden" name="sid" value="<?= htmlspecialchars($sid) ?>">

    <div class="seg-field">
        <label for="f_q">Buscar casa o propietario</label>
        <input id="f_q" type="search" name="q" value="<?= htmlspecialchars($f_q) ?>"
               placeholder="Casa, propietario, cédula, calle...">
    </div>

    <div class="seg-field">
        <label for="f_estado">Estado</label>
        <select id="f_estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['Pagado', 'Pendiente', 'Vencido', 'Sin registro'] as $estadoOpcion): ?>
                <option value="<?= $estadoOpcion ?>" <?= $f_estado === $estadoOpcion ? 'selected' : '' ?>>
                    <?= $estadoOpcion ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="seg-field">
        <label for="f_mes">Mes</label>
        <select id="f_mes" name="mes">
            <option value="0">Todos</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $f_mes === $m ? 'selected' : '' ?>><?= $meses[$m] ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="seg-field">
        <label for="f_anio">Año</label>
        <select id="f_anio" name="anio">
            <option value="0">Todos</option>
            <?php foreach ($anios as $anioOpcion): ?>
                <option value="<?= (int)$anioOpcion ?>" <?= $f_anio === (int)$anioOpcion ? 'selected' : '' ?>>
                    <?= (int)$anioOpcion ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="seg-field">
        <label for="f_calle">Calle</label>
        <select id="f_calle" name="calle_id">
            <option value="0">Todas</option>
            <?php foreach ($calles as $calle): ?>
                <option value="<?= (int)$calle['id'] ?>" <?= $f_calle === (int)$calle['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($calle['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="seg-field">
        <label>&nbsp;</label>
        <div class="seg-actions">
            <button type="submit">Filtrar</button>
            <a href="router.php?page=seguimiento_pagos<?= $sid !== '' ? '&sid=' . urlencode($sid) : '' ?>">Limpiar</a>
        </div>
    </div>
</form>

<div class="seg-stats">
    <div class="card seg-stat">
        <div class="seg-stat-label">Casas</div>
        <div class="seg-stat-value"><?= number_format($stats['casas'], 0, '.', ',') ?></div>
        <div class="seg-stat-note"><?= number_format($stats['registros'], 0, '.', ',') ?> registro(s) en el filtro</div>
    </div>
    <div class="card seg-stat">
        <div class="seg-stat-label">Con pago</div>
        <div class="seg-stat-value"><?= number_format($stats['pagadas'], 0, '.', ',') ?></div>
        <div class="seg-stat-note">Casas con al menos un pago</div>
    </div>
    <div class="card seg-stat">
        <div class="seg-stat-label">Con pendientes</div>
        <div class="seg-stat-value"><?= number_format($stats['pendientes'], 0, '.', ',') ?></div>
        <div class="seg-stat-note">Casas con cobros por regularizar</div>
    </div>
    <div class="card seg-stat">
        <div class="seg-stat-label">Recaudado</div>
        <div class="seg-stat-value" style="font-size:20px;">Bs. <?= number_format($stats['recaudado'], 2, '.', ',') ?></div>
        <div class="seg-stat-note">Pendiente: Bs. <?= number_format($stats['pendiente_monto'], 2, '.', ',') ?></div>
    </div>
</div>

<div class="card seg-table-card">
    <div class="seg-table-head">
        <h3>🏠 Estado de pagos por casa</h3>
        <span><?= number_format(count($casas), 0, '.', ',') ?> fila(s) · <?= htmlspecialchars($barrio_nombre) ?></span>
    </div>

    <?php if (empty($casas)): ?>
        <div class="seg-empty">
            <strong>Sin resultados</strong>
            No hay casas o cobros que coincidan con los filtros seleccionados.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-mini seg-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Casa</th>
                        <th>Propietario</th>
                        <th>Calle / dirección</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Monto</th>
                        <th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($casas as $c): ?>
                        <?php
                        $identificacion = formato_identificacion($c['cedula'] ?? '', $c['cedula_tipo'] ?? 'V', $c['otro_identificador'] ?? '');
                        $estado = $c['estado'] ?? 'Sin registro';
                        $badgeClass = $estado === 'Pagado'
                            ? 'seg-badge-pagado'
                            : ($estado === 'Vencido' ? 'seg-badge-vencido' : ($estado === 'Sin registro' ? 'seg-badge-sin' : 'seg-badge-pendiente'));
                        ?>
                        <tr>
                            <td class="seg-casa">#<?= htmlspecialchars($c['numero_casa'] ?? '') ?></td>
                            <td>
                                <div class="seg-prop"><?= htmlspecialchars($c['propietario'] ?? '') ?></div>
                                <?php if ($identificacion !== ''): ?>
                                    <div class="seg-sub"><?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($c['calle_nombre'] ?: 'Sin calle') ?></div>
                                <?php if (!empty($c['direccion'])): ?>
                                    <div class="seg-sub"><?= htmlspecialchars($c['direccion']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?= $c['mes'] ? htmlspecialchars($meses[(int)$c['mes']] ?? $c['mes']) . '/' . (int)$c['anio'] : 'Sin periodo' ?>
                            </td>
                            <td>
                                <span class="seg-badge <?= $badgeClass ?>"><?= htmlspecialchars($estado) ?></span>
                            </td>
                            <td style="white-space:nowrap; font-weight:700;">
                                <?= $c['monto'] !== null ? 'Bs. ' . number_format((float)$c['monto'], 2, '.', ',') : '—' ?>
                            </td>
                            <td>
                                <?php if (!empty($c['comprobante_barrio']) || !empty($c['comprobante_calle'])): ?>
                                    <?php $img = $c['comprobante_barrio'] ?: $c['comprobante_calle']; ?>
                                    <a href="#" data-url="<?= htmlspecialchars($img) ?>"
                                       onclick="return openComprobanteModal(this)"
                                       class="seg-badge seg-badge-pagado" style="text-decoration:none;">Ver evidencia</a>
                                <?php else: ?>
                                    <span style="color:#9CA3AF; font-size:10px;">Sin comprobante</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
