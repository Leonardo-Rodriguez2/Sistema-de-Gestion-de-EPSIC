<?php
// views/barrio/solicitudes.php
$user = check_dashboard_access([5]);

// Filtros
$f_estado = (string)($_GET['estado'] ?? 'Pendiente');
$estadosPermitidos = ['Pendiente', 'Aprobado', 'Rechazado', 'Todos'];
if (!in_array($f_estado, $estadosPermitidos, true)) $f_estado = 'Pendiente';
$f_tipo = (string)($_GET['tipo'] ?? '');
$tiposPermitidos = ['Alta', 'Baja', 'Renovacion'];
if (!in_array($f_tipo, $tiposPermitidos, true)) $f_tipo = '';
$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// 1. Obtener solicitudes de las calles de su barrio
$sql = "SELECT s.*, c.nombre as calle_nombre, u.nombre as solicitante_nombre,
               u.apellido as solicitante_apellido, b.nombre as barrio_nombre
        FROM solicitudes_vivienda s 
        JOIN calles c ON s.calle_id = c.id
        JOIN barrios b ON c.barrio_id = b.id
        JOIN usuarios u ON s.creado_por = u.id
        JOIN detalles_encargado_barrio deb ON c.barrio_id = deb.barrio_id
        WHERE deb.usuario_id = :usuario AND s.tipo IN ('Alta', 'Baja', 'Renovacion')";
$params = [':usuario' => $user['id']];
if ($f_estado !== 'Todos') {
    $sql .= " AND s.estado = :estado";
    $params[':estado'] = $f_estado;
}
if ($f_tipo !== '') {
    $sql .= " AND s.tipo = :tipo";
    $params[':tipo'] = $f_tipo;
}
if ($f_buscar !== '') {
    $sql .= " AND (s.propietario LIKE :q_propietario OR s.cedula LIKE :q_cedula OR s.otro_identificador LIKE :q_otro
                   OR s.numero_casa LIKE :q_casa OR c.nombre LIKE :q_calle OR u.nombre LIKE :q_solicitante OR u.apellido LIKE :q_apellido)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
    $params[':q_calle'] = '%' . $f_buscar . '%';
    $params[':q_solicitante'] = '%' . $f_buscar . '%';
    $params[':q_apellido'] = '%' . $f_buscar . '%';
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
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datos para exportación.
$barrioNombre = '';
if (!empty($solicitudes)) $barrioNombre = (string)($solicitudes[0]['barrio_nombre'] ?? '');
$metaSolicitudes = [
    'Barrio' => $barrioNombre !== '' ? $barrioNombre : null,
    'Estado' => $f_estado,
    'Tipo' => $f_tipo !== '' ? $f_tipo : null,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasSolicitudes = [];
foreach ($solicitudes as $s) {
    $propietario = $s['propietario'] ?? '';
    $casa = $s['numero_casa'] ?? '';
    $identificacion = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? '');
    if (($s['tipo'] ?? '') !== 'Alta' && !empty($s['vivienda_id'])) {
        $vStmt = $pdo->prepare("SELECT propietario, numero_casa, cedula, cedula_tipo, otro_identificador FROM viviendas WHERE id = ?");
        $vStmt->execute([$s['vivienda_id']]);
        $vData = $vStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $propietario = $vData['propietario'] ?? $propietario;
        $casa = $vData['numero_casa'] ?? $casa;
        $identificacion = formato_identificacion($vData['cedula'] ?? '', $vData['cedula_tipo'] ?? 'V', $vData['otro_identificador'] ?? '');
    }
    $filasSolicitudes[] = [
        date('d/m/Y H:i', strtotime($s['fecha_creacion'])),
        $s['tipo'],
        $s['estado'],
        $s['calle_nombre'] ?? '',
        '#' . $casa,
        $propietario,
        $identificacion,
        number_format((float)($s['monto_deuda'] ?? 0), 2, '.', ','),
        $s['detalles_deuda'] ?? '',
        trim(($s['solicitante_nombre'] ?? '') . ' ' . ($s['solicitante_apellido'] ?? '')),
    ];
}
reporte_exportar_si_solicitado(
    'solicitudes_barrio',
    'Solicitudes del barrio',
    ['Fecha', 'Tipo', 'Estado', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Deuda', 'Detalles', 'Solicitante'],
    $filasSolicitudes,
    array_merge($metaSolicitudes, ['Total solicitudes' => count($filasSolicitudes)]),
    'Bandeja de altas, bajas y renovaciones de servicio'
);

$title = "Bandeja de Solicitudes - EcoCusco";
$header_title = "Solicitudes Pendientes";
$header_subtitle = "Revisa y aprueba altas, bajas o renovaciones de servicio.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Solicitudes del barrio', 'Bandeja de altas, bajas y renovaciones de servicio', array_merge($metaSolicitudes, ['Total solicitudes' => count($solicitudes)])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(180px,1.4fr) repeat(4,minmax(115px,1fr)) auto; gap:10px; align-items:end;">
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
                    <option value="<?= $estadoOpcion ?>" <?= $f_estado === $estadoOpcion ? 'selected' : '' ?>><?= $estadoOpcion ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Tipo</label>
            <select name="tipo" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
                <option value="">Todos</option>
                <?php foreach ($tiposPermitidos as $tipoOpcion): ?>
                    <option value="<?= $tipoOpcion ?>" <?= $f_tipo === $tipoOpcion ? 'selected' : '' ?>><?= $tipoOpcion ?></option>
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
            <a href="router.php?page=solicitudes" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha / Tipo</th>
                        <th style="padding: 12px;">Vivienda / Propietario</th>
                        <th style="padding: 12px;">Deuda al Retiro</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($solicitudes)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No tienes solicitudes pendientes por procesar.</td></tr>
                    <?php endif; ?>
                    <?php foreach($solicitudes as $s): ?>
                        <tr id="solicitud-<?= (int)$s['id'] ?>" style="border-bottom: 1px solid #F3F4F6;<?= (int)($_GET['solicitud_id'] ?? 0) === (int)$s['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding: 12px;">
                                <div style="font-size: 11px; color: #6B7280;"><?= date('d/m/Y', strtotime($s['fecha_creacion'])) ?></div>
                                <span class="badge" style="background: <?= $s['tipo'] == 'Alta' ? '#E9EFF4' : ($s['tipo'] == 'Baja' ? '#F4EDED' : '#E9EFF4') ?>; color: <?= $s['tipo'] == 'Alta' ? '#33485C' : ($s['tipo'] == 'Baja' ? '#7A4F4F' : '#3E5C76') ?>; border: none; font-size: 10px; font-weight: 700;">
                                    <?= strtoupper($s['tipo']) ?>
                                </span>
                                <div style="font-size:10px; color:#6B7280; margin-top:3px;"><?= htmlspecialchars($s['estado']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <?php if($s['tipo'] == 'Alta'): ?>
                                    <div style="font-weight: 700;"><?= htmlspecialchars($s['propietario']) ?></div>
                                    <div style="font-size: 11px; color: #6B7280;">Calle: <?= htmlspecialchars($s['calle_nombre']) ?> | Casa: <?= htmlspecialchars($s['numero_casa']) ?></div>
                                    <?php $identificacion = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?>
                                        <div style="font-size: 11px; color: #6B7280;">ID: <?= htmlspecialchars($identificacion) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php 
                                        $vStmt = $pdo->prepare("SELECT propietario, numero_casa, cedula, cedula_tipo, otro_identificador FROM viviendas WHERE id = ?");
                                        $vStmt->execute([$s['vivienda_id']]);
                                        $v = $vStmt->fetch();
                                    ?>
                                    <div style="font-weight: 700;"><?= htmlspecialchars($v['propietario'] ?? 'N/A') ?></div>
                                    <div style="font-size: 11px; color: #6B7280;">ID Casa: #<?= $s['vivienda_id'] ?> | Calle: <?= htmlspecialchars($s['calle_nombre']) ?></div>
                                    <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?>
                                        <div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if($s['tipo'] == 'Baja'): ?>
                                    <div style="font-weight: 800; color: #7A4F4F;">S/ <?= number_format($s['monto_deuda'], 2) ?></div>
                                    <div style="font-size: 10px; color: #6B7280; max-width: 200px;"><?= htmlspecialchars($s['detalles_deuda']) ?></div>
                                <?php else: ?>
                                    <span style="color: #9CA3AF; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <?php if ($s['estado'] === 'Pendiente'): ?>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <form method="POST" onsubmit="return confirm('¿Aprobar esta solicitud?')">
                                        <input type="hidden" name="form_type" value="procesar_solicitud">
                                        <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="estado" value="Aprobado">
                                        <button type="submit" class="badge" style="background: #111827; color: white; border:none; cursor:pointer;">Aprobar</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('¿Rechazar esta solicitud?')">
                                        <input type="hidden" name="form_type" value="procesar_solicitud">
                                        <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="estado" value="Rechazado">
                                        <button type="submit" class="badge" style="background: #F3F4F6; color: #374151; border:none; cursor:pointer;">Rechazar</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span style="color:#9CA3AF; font-size:11px;">Procesada</span>
                                <?php endif; ?>
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
