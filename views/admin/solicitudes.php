<?php
// views/admin/solicitudes.php
$user = check_dashboard_access([1]);
global $pdo;

// Filtros
$tipo = $_GET['tipo'] ?? '';
$tiposPermitidos = ['Alta', 'Baja', 'Renovacion'];
if (!in_array($tipo, $tiposPermitidos, true)) $tipo = '';

$estado = (string)($_GET['estado'] ?? 'Pendiente');
$estadosPermitidos = ['Pendiente', 'Aprobado', 'Rechazado', 'Todos'];
if (!in_array($estado, $estadosPermitidos, true)) $estado = 'Pendiente';

$f_barrio = (int)($_GET['barrio_id'] ?? 0);
$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_inicio = trim((string)($_GET['f_inicio'] ?? ''));
$f_fin = trim((string)($_GET['f_fin'] ?? ''));
$barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Consulta de solicitudes
$params = [];
$sql = "SELECT s.*, c.nombre as calle_nombre, b.nombre as barrio_nombre,
               u.nombre as solicitante_nombre, u.apellido as solicitante_apellido
        FROM solicitudes_vivienda s 
        JOIN calles c ON s.calle_id = c.id
        JOIN barrios b ON c.barrio_id = b.id
        JOIN usuarios u ON s.creado_por = u.id
        WHERE 1=1";
if ($estado !== 'Todos') {
    $sql .= " AND s.estado = :estado";
    $params[':estado'] = $estado;
}
if ($tipo !== '') {
    $sql .= " AND s.tipo = :tipo";
    $params[':tipo'] = $tipo;
}
if ($f_barrio > 0) {
    $sql .= " AND b.id = :barrio";
    $params[':barrio'] = $f_barrio;
}
if ($f_buscar !== '') {
    $sql .= " AND (s.propietario LIKE :q_propietario OR s.cedula LIKE :q_cedula OR s.otro_identificador LIKE :q_otro
                   OR c.nombre LIKE :q_calle OR b.nombre LIKE :q_barrio
                   OR u.nombre LIKE :q_solicitante OR u.apellido LIKE :q_apellido)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_calle'] = '%' . $f_buscar . '%';
    $params[':q_barrio'] = '%' . $f_buscar . '%';
    $params[':q_solicitante'] = '%' . $f_buscar . '%';
    $params[':q_apellido'] = '%' . $f_buscar . '%';
}
if ($f_inicio !== '') {
    $sql .= " AND DATE(s.fecha_creacion) >= :inicio";
    $params[':inicio'] = $f_inicio;
}
if ($f_fin !== '') {
    $sql .= " AND DATE(s.fecha_creacion) <= :fin";
    $params[':fin'] = $f_fin;
}
$sql .= " ORDER BY s.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$barrioNombreFiltro = '';
foreach ($barrios as $b) {
    if ((int)$b['id'] === $f_barrio) { $barrioNombreFiltro = $b['nombre']; break; }
}
$metaSolicitudes = [
    'Estado' => $estado !== 'Todos' ? $estado : 'Todos',
    'Tipo' => $tipo !== '' ? $tipo : null,
    'Barrio' => $barrioNombreFiltro !== '' ? $barrioNombreFiltro : null,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_inicio !== '' ? $f_inicio : null,
    'Hasta' => $f_fin !== '' ? $f_fin : null,
];
$filasSolicitudes = [];
foreach ($solicitudes as $s) {
    $filasSolicitudes[] = [
        date('d/m/Y H:i', strtotime($s['fecha_creacion'])),
        $s['tipo'] ?? '',
        $s['estado'] ?? '',
        $s['barrio_nombre'] ?? '',
        $s['calle_nombre'] ?? '',
        trim(($s['solicitante_nombre'] ?? '') . ' ' . ($s['solicitante_apellido'] ?? '')),
        $s['propietario'] ?? '',
        formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? ''),
        number_format((float)($s['monto_deuda'] ?? 0), 2, '.', ','),
        $s['detalles_deuda'] ?? '',
    ];
}
reporte_exportar_si_solicitado(
    'solicitudes_vivienda',
    'Solicitudes de vivienda',
    ['Fecha', 'Tipo', 'Estado', 'Barrio', 'Calle', 'Solicitante', 'Propietario', 'Identificación', 'Deuda', 'Detalles de deuda'],
    $filasSolicitudes,
    array_merge($metaSolicitudes, ['Total solicitudes' => count($filasSolicitudes)]),
    'Altas, bajas y renovaciones de servicio'
);

$title = "Solicitudes del Sistema";
$header_title = "Solicitudes Pendientes";
$header_subtitle = "Gestiona todas las solicitudes de altas, bajas y renovaciones.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Solicitudes de vivienda', 'Altas, bajas y renovaciones de servicio', array_merge($metaSolicitudes, ['Total solicitudes' => count($solicitudes)])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(180px,1.4fr) repeat(6,minmax(110px,1fr)); gap:10px; align-items:end;">
        <input type="hidden" name="page" value="solicitudes">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, calle..."
                   style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Estado</label>
            <select name="estado" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
                <?php foreach ($estadosPermitidos as $estadoOpcion): ?>
                    <option value="<?= $estadoOpcion ?>" <?= $estado === $estadoOpcion ? 'selected' : '' ?>><?= $estadoOpcion ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Tipo</label>
            <select name="tipo" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
                <option value="">Todos</option>
                <?php foreach ($tiposPermitidos as $tipoOpcion): ?>
                    <option value="<?= $tipoOpcion ?>" <?= $tipo === $tipoOpcion ? 'selected' : '' ?>><?= $tipoOpcion ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Barrio</label>
            <select name="barrio_id" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
                <option value="0">Todos</option>
                <?php foreach ($barrios as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $f_barrio === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Desde</label>
            <input type="date" name="f_inicio" value="<?= htmlspecialchars($f_inicio) ?>" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Hasta</label>
            <input type="date" name="f_fin" value="<?= htmlspecialchars($f_fin) ?>" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div style="display:flex; gap:6px; align-items:center;">
            <button type="submit" class="btn-primary" style="padding:9px 12px;">Filtrar</button>
            <a href="router.php?page=solicitudes" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Tipo</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Calle / barrio</th>
                        <th style="padding: 12px;">Solicitante</th>
                        <th style="padding: 12px;">Vivienda</th>
                        <th style="padding: 12px;">Deuda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr><td colspan="7" style="text-align:center;color:#9CA3AF;padding:40px;">No hay solicitudes que coincidan con los filtros.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($solicitudes as $s): ?>
                        <tr id="solicitud-<?= (int)$s['id'] ?>" style="border-bottom:1px solid #F3F4F6;<?= (int)($_GET['solicitud_id'] ?? 0) === (int)$s['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding:12px; white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($s['fecha_creacion'])) ?></td>
                            <td style="padding:12px;">
                                <span style="background:<?= $s['tipo']=='Alta'?'#E9EFF4':($s['tipo']=='Baja'?'#F4EDED':'#E9EFF4') ?>;color:<?= $s['tipo']=='Alta'?'#33485C':($s['tipo']=='Baja'?'#7A4F4F':'#3E5C76') ?>;padding:3px 8px;font-size:10px;font-weight:700;"><?= strtoupper($s['tipo']) ?></span>
                            </td>
                            <td style="padding:12px;">
                                <span style="background:<?= $s['estado']=='Pendiente'?'#F7F4EE':($s['estado']=='Rechazado'?'#F4EDED':'#E9EFF4') ?>;color:<?= $s['estado']=='Pendiente'?'#6E5F45':($s['estado']=='Rechazado'?'#7A4F4F':'#33485C') ?>;padding:3px 8px;font-size:10px;font-weight:700;"><?= htmlspecialchars($s['estado']) ?></span>
                            </td>
                            <td style="padding:12px;">
                                <?= htmlspecialchars($s['calle_nombre']) ?>
                                <div style="font-size:10px; color:#6B7280;"><?= htmlspecialchars($s['barrio_nombre'] ?? '') ?></div>
                            </td>
                            <td style="padding:12px;"><?= htmlspecialchars(trim(($s['solicitante_nombre'] ?? '') . ' ' . ($s['solicitante_apellido'] ?? ''))) ?></td>
                            <td style="padding:12px;">
                                <?= htmlspecialchars($s['propietario'] ?? $s['vivienda_id']) ?>
                                <?php $identificacion = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size:11px;color:#6B7280;">ID: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px;font-weight:700;color:#7A4F4F;">S/ <?= number_format($s['monto_deuda'] ?? 0, 2) ?></td>
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
