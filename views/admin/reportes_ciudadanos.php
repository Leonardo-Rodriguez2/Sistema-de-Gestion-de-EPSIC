<?php
// views/admin/reportes_ciudadanos.php
// Bandeja de reportes enviados por la ciudadanía desde el portal público.
$user = check_dashboard_access([1, 2, 3]);

$estados = ['Pendiente', 'En proceso', 'Completado'];
$f_estado = (string)($_GET['estado'] ?? '');
if (!in_array($f_estado, $estados, true)) $f_estado = '';
$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

$sql = "SELECT r.id, r.ubicacion_nombre, r.tipo_residuo, r.descripcion, r.estado, r.fecha_reporte,
               r.usuario_id, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM reportes r
        LEFT JOIN usuarios u ON r.usuario_id = u.id
        WHERE 1=1";
$params = [];
if ($f_estado !== '') {
    $sql .= " AND r.estado = :estado";
    $params[':estado'] = $f_estado;
}
if ($f_buscar !== '') {
    $sql .= " AND (r.ubicacion_nombre LIKE :q_ubicacion OR r.tipo_residuo LIKE :q_tipo
                   OR r.descripcion LIKE :q_descripcion OR u.nombre LIKE :q_usuario OR u.apellido LIKE :q_apellido)";
    $params[':q_ubicacion'] = '%' . $f_buscar . '%';
    $params[':q_tipo'] = '%' . $f_buscar . '%';
    $params[':q_descripcion'] = '%' . $f_buscar . '%';
    $params[':q_usuario'] = '%' . $f_buscar . '%';
    $params[':q_apellido'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sql .= " AND DATE(r.fecha_reporte) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(r.fecha_reporte) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= " ORDER BY r.fecha_reporte DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$metaReportes = [
    'Estado' => $f_estado !== '' ? $f_estado : 'Todos',
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasReportes = [];
foreach ($reportes as $r) {
    $filasReportes[] = [
        !empty($r['fecha_reporte']) ? date('d/m/Y H:i', strtotime($r['fecha_reporte'])) : '',
        $r['ubicacion_nombre'] ?? '',
        $r['tipo_residuo'] ?? '',
        $r['descripcion'] ?? '',
        $r['estado'] ?? '',
        trim(($r['usuario_nombre'] ?? '') . ' ' . ($r['usuario_apellido'] ?? '')),
    ];
}
reporte_exportar_si_solicitado(
    'reportes_ciudadanos',
    'Reportes ciudadanos',
    ['Fecha', 'Ubicación', 'Tipo de incidente', 'Descripción', 'Estado', 'Ciudadano'],
    $filasReportes,
    array_merge($metaReportes, ['Total reportes' => count($filasReportes)]),
    'Incidentes reportados desde el portal público'
);

$title = "Reportes Ciudadanos - EPSIC";
$header_title = "Reportes Ciudadanos";
$header_subtitle = "Incidentes reportados desde el portal público.";

ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<?= reporte_encabezado_impresion('Reportes ciudadanos', 'Incidentes reportados desde el portal público', array_merge($metaReportes, ['Total reportes' => count($reportes)])) ?>
<?= reporte_botonera() ?>

<div class="no-print" style="display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap;">
    <a href="<?= htmlspecialchars(reporte_url(['estado' => ''])) ?>" class="btn-primary" style="text-decoration:none; background:<?= $f_estado===''?'#4C6E91':'#6B7280' ?>;">Todos</a>
    <?php foreach ($estados as $e): ?>
        <a href="<?= htmlspecialchars(reporte_url(['estado' => $e])) ?>" class="btn-primary" style="text-decoration:none; background:<?= $f_estado===$e?'#4C6E91':'#6B7280' ?>;"><?= htmlspecialchars($e) ?></a>
    <?php endforeach; ?>
</div>

<form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(200px,1.5fr) repeat(2,minmax(140px,1fr)) auto; gap:10px; align-items:end;">
    <input type="hidden" name="page" value="reportes_ciudadanos">
    <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
    <input type="hidden" name="estado" value="<?= htmlspecialchars($f_estado) ?>">
    <div>
        <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
        <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Ubicación, tipo, descripción..."
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
        <a href="router.php?page=reportes_ciudadanos" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
    </div>
</form>

<div class="card" style="padding:0; overflow:hidden;">
    <div class="table-wrap">
        <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F9FAFB; text-align:left;">
                    <th style="padding:12px 16px;">Fecha</th>
                    <th style="padding:12px 16px;">Ubicación</th>
                    <th style="padding:12px 16px;">Tipo de incidente</th>
                    <th style="padding:12px 16px;">Descripción</th>
                    <th style="padding:12px 16px;">Ciudadano</th>
                    <th style="padding:12px 16px;">Estado</th>
                    <th style="padding:12px 16px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportes)): ?>
                    <tr><td colspan="7" style="padding:24px; text-align:center; color:#9CA3AF;">No hay reportes ciudadanos que coincidan con los filtros.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportes as $r): ?>
                        <tr id="reporte-<?= (int)$r['id'] ?>" style="border-top:1px solid #F3F4F6; vertical-align:top;<?= (int)($_GET['reporte_id'] ?? 0) === (int)$r['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding:12px 16px; white-space:nowrap;"><?= !empty($r['fecha_reporte']) ? date('d/m/Y H:i', strtotime($r['fecha_reporte'])) : '—' ?></td>
                            <td style="padding:12px 16px;"><?= htmlspecialchars($r['ubicacion_nombre']) ?></td>
                            <td style="padding:12px 16px; max-width:220px;"><?= htmlspecialchars($r['tipo_residuo']) ?></td>
                            <td style="padding:12px 16px; max-width:320px; font-size:12px; color:#4B5563;"><?= nl2br(htmlspecialchars($r['descripcion'] ?? '')) ?></td>
                            <td style="padding:12px 16px; font-size:11px; color:#4B5563;"><?= htmlspecialchars(trim(($r['usuario_nombre'] ?? '') . ' ' . ($r['usuario_apellido'] ?? ''))) ?: '—' ?></td>
                            <td style="padding:12px 16px;">
                                <span class="badge <?= $r['estado']==='Completado'?'admin':($r['estado']==='En proceso'?'gestor':'jefe') ?>"><?= htmlspecialchars($r['estado']) ?></span>
                            </td>
                            <td style="padding:12px 16px;">
                                <form action="router.php" method="POST" class="no-print" style="display:flex; gap:6px; align-items:center; margin:0;">
                                    <input type="hidden" name="action" value="actualizar_reporte">
                                    <input type="hidden" name="page" value="reportes_ciudadanos">
                                    <input type="hidden" name="reporte_id" value="<?= (int)$r['id'] ?>">
                                    <select name="estado" style="padding:4px 6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
                                        <?php foreach ($estados as $e): ?>
                                            <option value="<?= $e ?>" <?= $r['estado']===$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-primary" style="padding:5px 10px; font-size:12px;">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
