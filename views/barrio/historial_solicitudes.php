<?php
// views/historial_solicitudes.php
$allowed = [5, 6];
$user = check_dashboard_access($allowed);

// 1. Obtener la calle_id (si es calle manager) o barrio_id (si es barrio manager)
$sid = $_SESSION['active_sid'] ?? 'main';
$user_id = $user['id'];

$sql = "";
$params = [':usuario' => $user_id];

$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_tipo = (string)($_GET['tipo'] ?? '');
$tiposPermitidos = ['Alta', 'Baja', 'Renovacion'];
if (!in_array($f_tipo, $tiposPermitidos, true)) $f_tipo = '';
$f_estado = (string)($_GET['estado'] ?? 'Todos');
$estadosPermitidos = ['Aprobado', 'Rechazado', 'Todos'];
if (!in_array($f_estado, $estadosPermitidos, true)) $f_estado = 'Todos';
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

if($user['rol_id'] == 6) {
    // Calle Manager: Ver sus solicitudes procesadas
    $sql = "SELECT s.*, v.propietario as v_prop, v.numero_casa as v_casa
            FROM solicitudes_vivienda s
            LEFT JOIN viviendas v ON s.vivienda_id = v.id
            WHERE s.creado_por = :usuario AND s.estado != 'Pendiente'";
} else {
    // Barrio Manager: Ver todas las procesadas de su barrio
    $sql = "SELECT s.*, v.propietario as v_prop, v.numero_casa as v_casa, c.nombre as calle_nombre
            FROM solicitudes_vivienda s
            LEFT JOIN viviendas v ON s.vivienda_id = v.id
            JOIN calles c ON c.id = s.calle_id
            JOIN detalles_encargado_barrio deb ON deb.barrio_id = c.barrio_id
            WHERE deb.usuario_id = :usuario AND s.estado != 'Pendiente'";
}

if ($f_tipo !== '') {
    $sql .= " AND s.tipo = :tipo";
    $params[':tipo'] = $f_tipo;
}
if ($f_estado !== 'Todos') {
    $sql .= " AND s.estado = :estado";
    $params[':estado'] = $f_estado;
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
    $sql .= " AND DATE(COALESCE(s.fecha_revision, s.fecha_creacion)) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(COALESCE(s.fecha_revision, s.fecha_creacion)) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= ($user['rol_id'] == 6)
    ? " ORDER BY s.fecha_revision DESC LIMIT 200"
    : " ORDER BY s.fecha_revision DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agrupado = [];
foreach ($historial as $registro) {
    $identidad = '';
    $cedulaRegistro = trim((string)($registro['cedula'] ?? ''));
    $otroRegistro = trim((string)($registro['otro_identificador'] ?? ''));
    if ($cedulaRegistro !== '') {
        $identidad = strtoupper(trim((string)($registro['cedula_tipo'] ?? 'V'))) . '-' . $cedulaRegistro;
    } elseif ($otroRegistro !== '') {
        $identidad = 'otro-' . strtolower($otroRegistro);
    } else {
        $identidad = strtolower(trim(
            ($registro['calle_id'] ?? '') . '|' .
            ($registro['numero_casa'] ?? $registro['v_casa'] ?? '') . '|' .
            ($registro['propietario'] ?? $registro['v_prop'] ?? '')
        ));
    }
    $clave = $registro['tipo'] . '|' . $identidad;
    if (!isset($agrupado[$clave])) {
        $registro['solicitud_ids'] = [(int)$registro['id']];
        $agrupado[$clave] = $registro;
    } else {
        $agrupado[$clave]['solicitud_ids'][] = (int)$registro['id'];
        $agrupado[$clave]['monto_deuda'] = (float)$agrupado[$clave]['monto_deuda']
            + (float)$registro['monto_deuda'];
    }
}
$historial = array_values($agrupado);

// Exportación del historial agrupado.
$metaHistorial = [
    'Estado' => $f_estado,
    'Tipo' => $f_tipo !== '' ? $f_tipo : null,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasHistorial = [];
foreach ($historial as $h) {
    $filasHistorial[] = [
        !empty($h['fecha_revision']) ? date('d/m/Y H:i', strtotime($h['fecha_revision'])) : date('d/m/Y H:i', strtotime($h['fecha_creacion'])),
        $h['tipo'],
        $h['estado'],
        $h['calle_nombre'] ?? '',
        '#' . ($h['v_casa'] ?? $h['numero_casa'] ?? ''),
        $h['v_prop'] ?? $h['propietario'] ?? '',
        formato_identificacion($h['cedula'] ?? '', $h['cedula_tipo'] ?? 'V', $h['otro_identificador'] ?? ''),
        number_format((float)($h['monto_deuda'] ?? 0), 2, '.', ','),
        '#' . implode(', #', $h['solicitud_ids']),
    ];
}
reporte_exportar_si_solicitado(
    'historial_solicitudes',
    'Historial de solicitudes',
    ['Fecha', 'Tipo', 'Estado', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Deuda', 'Solicitudes'],
    $filasHistorial,
    array_merge($metaHistorial, ['Total trámites' => count($filasHistorial)]),
    'Trámites de alta, baja y renovación ya procesados'
);

$title = "Historial de Trámites - EcoCusco";
$header_title = "Historial de Solicitudes";
$header_subtitle = "Revisa los trámites de alta, baja y renovación ya procesados.";

ob_start();
?>
    <?= reporte_encabezado_impresion('Historial de solicitudes', 'Trámites de alta, baja y renovación ya procesados', array_merge($metaHistorial, ['Total trámites' => count($historial)])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(180px,1.4fr) repeat(4,minmax(115px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="historial_solicitudes">
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
            <a href="router.php?page=historial_solicitudes" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <h3 style="margin-top:0;">📜 Trámites Finalizados</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Este es un registro histórico de las solicitudes aprobadas o rechazadas.
        </p>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha / Tipo</th>
                        <th style="padding: 12px;">Vivienda</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Resolución</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($historial)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay trámites finalizados en el registro.</td></tr>
                    <?php endif; ?>
                    <?php foreach($historial as $h): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-size: 11px; color: #6B7280;"><?= date('d/m/Y', strtotime($h['fecha_revision'] ?: $h['fecha_creacion'])) ?></div>
                                <span class="badge" style="background: <?= $h['tipo'] == 'Alta' ? '#E9EFF4' : ($h['tipo'] == 'Baja' ? '#F4EDED' : '#E9EFF4') ?>; color: <?= $h['tipo'] == 'Alta' ? '#33485C' : ($h['tipo'] == 'Baja' ? '#7A4F4F' : '#3E5C76') ?>; border: none; font-size: 9px; font-weight: 700;">
                                    <?= strtoupper($h['tipo']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($h['v_prop'] ?? $h['propietario']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;">
                                    Casa #<?= htmlspecialchars($h['v_casa'] ?? $h['numero_casa']) ?> 
                                    <?= isset($h['calle_nombre']) ? ' | ' . htmlspecialchars($h['calle_nombre']) : '' ?>
                                </div>
                                <?php $identificacion = formato_identificacion($h['cedula'] ?? '', $h['cedula_tipo'] ?? 'V', $h['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 10px; color: #9CA3AF;">ID: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if($h['estado'] == 'Aprobado'): ?>
                                    <span class="badge" style="background: #E9EFF4; color: #33485C; border:none; font-size: 10px;">✅ APROBADO</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #F7F2F2; color: #7A4F4F; border:none; font-size: 10px;">❌ RECHAZADO</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-size: 11px; color: #4B5563;">
                                    <?= ($h['tipo'] == 'Baja' || $h['tipo'] == 'Renovacion') ? '<b>Deuda:</b> S/ ' . number_format($h['monto_deuda'], 2) : 'Registro de Vivienda' ?>
                                </div>
                                <div style="font-size: 10px; color: #6B7280;">
                                    Solicitud<?= count($h['solicitud_ids']) > 1 ? 'es' : '' ?> #<?= htmlspecialchars(implode(', #', $h['solicitud_ids'])) ?>
                                </div>
                                <div style="font-size: 9px; color: #9CA3AF;">Procesado el: <?= $h['fecha_revision'] ?></div>
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
