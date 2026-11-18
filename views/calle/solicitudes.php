<?php
// views/calle/solicitudes.php
$user = check_dashboard_access([6]);

// 1. Obtener la calle asignada
$calleStmt = $pdo->prepare("SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ?");
$calleStmt->execute([$user['id']]);
$calle_id = $calleStmt->fetchColumn();

// Filtros
$f_estado = (string)($_GET['estado'] ?? 'Todos');
$estadosPermitidos = ['Pendiente', 'Aprobado', 'Rechazado', 'Todos'];
if (!in_array($f_estado, $estadosPermitidos, true)) $f_estado = 'Todos';
$f_tipo = (string)($_GET['tipo'] ?? '');
$tiposPermitidos = ['Alta', 'Baja', 'Renovacion'];
if (!in_array($f_tipo, $tiposPermitidos, true)) $f_tipo = '';
$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// 2. Obtener solicitudes de esta calle
$sql = "SELECT s.*, v.propietario as v_prop, v.numero_casa as v_casa
        FROM solicitudes_vivienda s
        LEFT JOIN viviendas v ON s.vivienda_id = v.id
        WHERE s.creado_por = :usuario";
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
                   OR s.numero_casa LIKE :q_casa OR v.propietario LIKE :q_vprop OR v.numero_casa LIKE :q_vcasa)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
    $params[':q_vprop'] = '%' . $f_buscar . '%';
    $params[':q_vcasa'] = '%' . $f_buscar . '%';
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

// Exportación del listado filtrado.
$metaSolicitudes = [
    'Estado' => $f_estado,
    'Tipo' => $f_tipo !== '' ? $f_tipo : null,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasSolicitudes = [];
foreach ($solicitudes as $s) {
    $propietario = $s['v_prop'] ?? $s['propietario'];
    $casa = $s['v_casa'] ?? $s['numero_casa'];
    $filasSolicitudes[] = [
        date('d/m/Y H:i', strtotime($s['fecha_creacion'])),
        $s['tipo'],
        $s['estado'],
        '#' . ($casa ?? ''),
        $propietario ?? '',
        formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? ''),
        number_format((float)($s['monto_deuda'] ?? 0), 2, '.', ','),
        $s['detalles_deuda'] ?? '',
        !empty($s['fecha_revision']) ? date('d/m/Y H:i', strtotime($s['fecha_revision'])) : '',
    ];
}
reporte_exportar_si_solicitado(
    'mis_solicitudes',
    'Mis solicitudes de servicio',
    ['Fecha', 'Tipo', 'Estado', 'Casa', 'Propietario', 'Identificación', 'Deuda', 'Detalles', 'Fecha de revisión'],
    $filasSolicitudes,
    array_merge($metaSolicitudes, ['Total solicitudes' => count($filasSolicitudes)]),
    'Seguimiento de altas, bajas y renovaciones enviadas'
);

$title = "Mis Solicitudes - EcoCusco";
$header_title = "Seguimiento de Trámites";
$header_subtitle = "Revisa el estado de tus solicitudes de alta, baja o renovación.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Mis solicitudes de servicio', 'Seguimiento de altas, bajas y renovaciones enviadas', array_merge($metaSolicitudes, ['Total solicitudes' => count($solicitudes)])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(180px,1.4fr) repeat(4,minmax(115px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="solicitudes">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, casa..."
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
        <h3 style="margin-top:0;">📋 Estado de Solicitudes</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Aquí puedes ver si el Encargado de Barrio ya procesó tus peticiones.
        </p>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Tipo</th>
                        <th style="padding: 12px;">Vivienda</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Detalles / Deuda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($solicitudes)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #9CA3AF; padding: 40px;">Aún no has realizado ninguna solicitud.</td></tr>
                    <?php endif; ?>
                    <?php foreach($solicitudes as $s): ?>
                        <?php
                            $tipoSolicitud = (string)($s['tipo'] ?? '');
                            $tipoEtiqueta = ['Alta' => 'ALTA', 'Baja' => 'BAJA', 'Renovacion' => 'RENOVACIÓN'][$tipoSolicitud] ?? strtoupper($tipoSolicitud);
                            $tipoBg = $tipoSolicitud === 'Alta' ? '#E9EFF4' : ($tipoSolicitud === 'Baja' ? '#F4EDED' : '#EEF2F6');
                            $tipoColor = $tipoSolicitud === 'Alta' ? '#33485C' : ($tipoSolicitud === 'Baja' ? '#7A4F4F' : '#3E5C76');
                            $montoDeuda = (float)($s['monto_deuda'] ?? 0);
                            $detallesDeuda = trim((string)($s['detalles_deuda'] ?? ''));
                        ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px; white-space: nowrap;">
                                <div style="font-size: 11px; color: #6B7280;"><?= date('d/m/Y', strtotime($s['fecha_creacion'])) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <span class="badge" style="background: <?= $tipoBg ?>; color: <?= $tipoColor ?>; border: none; font-size: 10px; font-weight: 700;">
                                    <?= htmlspecialchars($tipoEtiqueta) ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <?php $nombre = $s['v_prop'] ?? $s['propietario']; ?>
                                <?php $casa = $s['v_casa'] ?? $s['numero_casa']; ?>
                                <div style="font-weight: 700;"><?= htmlspecialchars($nombre ?? 'N/A') ?></div>
                                <div style="font-size: 11px; color: #6B7280;">Casa #<?= htmlspecialchars($casa ?? '?') ?></div>
                                <?php $identificacion = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 10px; color: #9CA3AF;">ID: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if($s['estado'] == 'Pendiente'): ?>
                                    <span class="badge" style="background: #F2EEE7; color: #6E5F45; border:none; font-size: 10px;">⏳ EN ESPERA</span>
                                <?php elseif($s['estado'] == 'Aprobado'): ?>
                                    <span class="badge" style="background: #E9EFF4; color: #33485C; border:none; font-size: 10px;">✅ PROCESADO</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #F3F4F6; color: #374151; border:none; font-size: 10px;">❌ RECHAZADO</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if(in_array($tipoSolicitud, ['Baja', 'Renovacion'], true)): ?>
                                    <div style="font-weight: 800; color: <?= $tipoSolicitud === 'Baja' ? '#7A4F4F' : '#3E5C76' ?>; font-size: 12px;">S/ <?= number_format($montoDeuda, 2) ?></div>
                                    <?php if ($detallesDeuda !== ''): ?>
                                        <div style="font-size: 10px; color: #6B7280;"><?= htmlspecialchars($detallesDeuda) ?></div>
                                    <?php endif; ?>
                                <?php elseif ($detallesDeuda !== ''): ?>
                                    <div style="font-size: 10px; color: #6B7280;"><?= htmlspecialchars($detallesDeuda) ?></div>
                                <?php else: ?>
                                    <span style="color: #9CA3AF; font-size: 11px;">Registro de vivienda</span>
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
