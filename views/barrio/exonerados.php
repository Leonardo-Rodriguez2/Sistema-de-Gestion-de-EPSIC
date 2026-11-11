<?php
$user = check_dashboard_access([5]);

$barrioStmt = $pdo->prepare("SELECT d.barrio_id, b.nombre FROM detalles_encargado_barrio d JOIN barrios b ON b.id = d.barrio_id WHERE d.usuario_id = ? LIMIT 1");
$barrioStmt->execute([$user['id']]);
$barrioInfo = $barrioStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$barrio_id = (int)($barrioInfo['barrio_id'] ?? 0);
$barrio_nombre = (string)($barrioInfo['nombre'] ?? '');

$barriosAsignadosStmt = $pdo->prepare("
    SELECT b.id, b.nombre
    FROM detalles_encargado_barrio d
    JOIN barrios b ON b.id = d.barrio_id
    WHERE d.usuario_id = ?
    ORDER BY b.nombre
");
$barriosAsignadosStmt->execute([$user['id']]);
$barriosAsignados = $barriosAsignadosStmt->fetchAll(PDO::FETCH_ASSOC);

$tab = $_GET['tab'] ?? 'pendientes';
if (!in_array($tab, ['pendientes', 'exentas', 'agregar'], true)) $tab = 'pendientes';

$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// Obtener solicitudes pendientes
$sqlPendientes = "
    SELECT e.*, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, c.nombre as calle_nombre, u.nombre as creador_nombre, u.apellido as creador_apellido
    FROM exenciones_cobro e
    JOIN viviendas v ON e.vivienda_id = v.id
    JOIN calles c ON e.calle_id = c.id
    LEFT JOIN usuarios u ON e.creado_por = u.id
    WHERE e.barrio_id = :barrio AND e.estado = 'Pendiente'";
$paramsPendientes = [':barrio' => $barrio_id];
if ($f_buscar !== '') {
    $sqlPendientes .= " AND (v.propietario LIKE :q_propietario OR v.cedula LIKE :q_cedula OR v.otro_identificador LIKE :q_otro
                            OR v.numero_casa LIKE :q_casa OR c.nombre LIKE :q_calle OR u.nombre LIKE :q_creador OR u.apellido LIKE :q_apellido)";
    $paramsPendientes[':q_propietario'] = '%' . $f_buscar . '%';
    $paramsPendientes[':q_cedula'] = '%' . $f_buscar . '%';
    $paramsPendientes[':q_otro'] = '%' . $f_buscar . '%';
    $paramsPendientes[':q_casa'] = '%' . $f_buscar . '%';
    $paramsPendientes[':q_calle'] = '%' . $f_buscar . '%';
    $paramsPendientes[':q_creador'] = '%' . $f_buscar . '%';
    $paramsPendientes[':q_apellido'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sqlPendientes .= " AND DATE(e.fecha_creacion) >= :desde";
    $paramsPendientes[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sqlPendientes .= " AND DATE(e.fecha_creacion) <= :hasta";
    $paramsPendientes[':hasta'] = $f_hasta;
}
$sqlPendientes .= " ORDER BY e.fecha_creacion DESC";
$pendientesStmt = $pdo->prepare($sqlPendientes);
$pendientesStmt->execute($paramsPendientes);
$pendientes = $pendientesStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener viviendas exentas activas
$sqlExentas = "
    SELECT e.*, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, c.nombre as calle_nombre, 
           u.nombre as aprobador_nombre, u.apellido as aprobador_apellido,
           uc.nombre as creador_nombre, uc.apellido as creador_apellido
    FROM exenciones_cobro e
    JOIN viviendas v ON e.vivienda_id = v.id
    JOIN calles c ON e.calle_id = c.id
    LEFT JOIN usuarios u ON e.aprobado_por = u.id
    LEFT JOIN usuarios uc ON e.creado_por = uc.id
    WHERE e.barrio_id = :barrio AND e.estado = 'Aprobado'";
$paramsExentas = [':barrio' => $barrio_id];
if ($f_buscar !== '') {
    $sqlExentas .= " AND (v.propietario LIKE :q_propietario OR v.cedula LIKE :q_cedula OR v.otro_identificador LIKE :q_otro
                         OR v.numero_casa LIKE :q_casa OR c.nombre LIKE :q_calle OR uc.nombre LIKE :q_creador OR uc.apellido LIKE :q_apellido)";
    $paramsExentas[':q_propietario'] = '%' . $f_buscar . '%';
    $paramsExentas[':q_cedula'] = '%' . $f_buscar . '%';
    $paramsExentas[':q_otro'] = '%' . $f_buscar . '%';
    $paramsExentas[':q_casa'] = '%' . $f_buscar . '%';
    $paramsExentas[':q_calle'] = '%' . $f_buscar . '%';
    $paramsExentas[':q_creador'] = '%' . $f_buscar . '%';
    $paramsExentas[':q_apellido'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sqlExentas .= " AND DATE(e.fecha_revision) >= :desde";
    $paramsExentas[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sqlExentas .= " AND DATE(e.fecha_revision) <= :hasta";
    $paramsExentas[':hasta'] = $f_hasta;
}
$sqlExentas .= " ORDER BY e.fecha_revision DESC";
$exentasStmt = $pdo->prepare($sqlExentas);
$exentasStmt->execute($paramsExentas);
$exentas = $exentasStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener las viviendas activas de los barrios asignados para agregar directo
$viviendasStmt = $pdo->prepare("
    SELECT v.id, v.barrio_id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador,
           c.nombre as calle_nombre, v.exento_cobro
    FROM viviendas v
    JOIN calles c ON v.calle_id = c.id
    JOIN detalles_encargado_barrio d ON d.barrio_id = v.barrio_id
    WHERE d.usuario_id = ? AND v.estado_servicio = 'Activo'
    ORDER BY v.barrio_id, c.nombre, v.numero_casa
");
$viviendasStmt->execute([$user['id']]);
$viviendas = $viviendasStmt->fetchAll(PDO::FETCH_ASSOC);

$tiposExencionLargo = ['pobreza' => 'Pobreza / Dificultad económica', 'adulto_mayor' => 'Adulto mayor / Jubilado', 'empleado' => 'Empleado de la empresa', 'espacio_publico' => 'Espacio público', 'otro' => 'Otro'];
$metaExonerados = [
    'Barrio' => $barrio_nombre !== '' ? $barrio_nombre : null,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasExonerados = [];
if ($tab === 'pendientes') {
    foreach ($pendientes as $ex) {
        $filasExonerados[] = [
            !empty($ex['fecha_creacion']) ? date('d/m/Y H:i', strtotime($ex['fecha_creacion'])) : '',
            '#' . ($ex['numero_casa'] ?? ''),
            $ex['propietario'] ?? '',
            formato_identificacion($ex['cedula'] ?? '', $ex['cedula_tipo'] ?? 'V', $ex['otro_identificador'] ?? ''),
            $ex['calle_nombre'] ?? '',
            $tiposExencionLargo[$ex['tipo_exencion']] ?? ($ex['tipo_exencion'] ?? ''),
            $ex['descripcion'] ?? '',
            trim(($ex['creador_nombre'] ?? '') . ' ' . ($ex['creador_apellido'] ?? '')),
            'Pendiente',
        ];
    }
    reporte_exportar_si_solicitado(
        'exonerados_pendientes',
        'Solicitudes de exención pendientes',
        ['Fecha', 'Casa', 'Propietario', 'Identificación', 'Calle', 'Motivo', 'Descripción', 'Solicitante', 'Estado'],
        $filasExonerados,
        array_merge($metaExonerados, ['Total solicitudes' => count($filasExonerados)]),
        'Solicitudes enviadas por los encargados de calle'
    );
} elseif ($tab === 'exentas') {
    foreach ($exentas as $ex) {
        $filasExonerados[] = [
            !empty($ex['fecha_revision']) ? date('d/m/Y H:i', strtotime($ex['fecha_revision'])) : '',
            '#' . ($ex['numero_casa'] ?? ''),
            $ex['propietario'] ?? '',
            formato_identificacion($ex['cedula'] ?? '', $ex['cedula_tipo'] ?? 'V', $ex['otro_identificador'] ?? ''),
            $ex['calle_nombre'] ?? '',
            $tiposExencionLargo[$ex['tipo_exencion']] ?? ($ex['tipo_exencion'] ?? ''),
            $ex['descripcion'] ?? '',
            trim(($ex['aprobador_nombre'] ?? '') . ' ' . ($ex['aprobador_apellido'] ?? '')),
            trim(($ex['creador_nombre'] ?? '') . ' ' . ($ex['creador_apellido'] ?? '')),
        ];
    }
    reporte_exportar_si_solicitado(
        'viviendas_exoneradas',
        'Viviendas exoneradas del pago',
        ['Desde', 'Casa', 'Propietario', 'Identificación', 'Calle', 'Motivo', 'Descripción', 'Aprobó', 'Solicitó'],
        $filasExonerados,
        array_merge($metaExonerados, ['Total viviendas' => count($filasExonerados)]),
        'Viviendas que actualmente no generan cobros mensuales'
    );
}

$title = "Exenciones de Cobro - EcoCusco";
$header_title = "🛡️ Exenciones de Cobro";
$header_subtitle = "Gestiona qué viviendas quedan exoneradas del pago mensual.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?php if ($tab !== 'agregar'): ?>
        <?= reporte_encabezado_impresion(
            $tab === 'pendientes' ? 'Solicitudes de exención pendientes' : 'Viviendas exoneradas del pago',
            $tab === 'pendientes' ? 'Solicitudes enviadas por los encargados de calle' : 'Viviendas que actualmente no generan cobros mensuales',
            array_merge($metaExonerados, [$tab === 'pendientes' ? 'Total solicitudes' : 'Total viviendas' => count($filasExonerados)])
        ) ?>
        <?= reporte_botonera() ?>

        <form method="GET" action="router.php" class="card no-print" style="margin-bottom:16px; padding:14px 16px; display:grid; grid-template-columns:minmax(200px,1.5fr) repeat(2,minmax(140px,1fr)) auto; gap:10px; align-items:end;">
            <input type="hidden" name="page" value="exonerados">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
            <div>
                <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
                <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, casa, calle..."
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
                <a href="router.php?page=exonerados&tab=<?= htmlspecialchars($tab) ?>" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
            </div>
        </form>
    <?php endif; ?>

    <style>
    .ex-tab { padding: 10px 18px; border-radius: 8px 8px 0 0; font-weight: 600; font-size: 13px; cursor: pointer; border: 1px solid #E5E7EB; border-bottom: none; background: #F9FAFB; color: #6B7280; text-decoration: none; display: inline-block; }
    .ex-tab.active { background: white; color: #111827; border-bottom: 2px solid #5A6B7D; }
    .ex-tab-bar { margin-bottom: 0; display: flex; gap: 4px; }
    .ex-content { border: 1px solid #E5E7EB; border-radius: 0 8px 8px 8px; padding: 14px; background: white; margin-top: -1px; }
    @media(max-width:600px){.ex-tab{font-size:11px;padding:8px 10px}.ex-content{padding:12px}}
    .ex-card { border:1px solid #E5E7EB; border-radius:10px; padding:14px; background:white; transition:0.2s; }
    .ex-card:hover { box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .ex-badge { padding:2px 8px; border-radius:4px; font-size:10px; font-weight:700; }
    .ex-info { font-size:11px; color:#6B7280; }
    .ex-label { font-size:10px; color:#9CA3AF; font-weight:600; text-transform:uppercase; }
    .ex-direct-card { width:100%; margin:16px 0 0; overflow:hidden; border:1px solid #E5E7EB; border-radius:10px; background:#fff; box-shadow:0 2px 8px rgba(17,24,39,.045); }
    .ex-direct-header { padding:20px 24px; border-bottom:1px solid #E5E7EB; background:#F9FAFB; }
    .ex-direct-header h2 { margin:0 0 5px; color:#1F2937; font-size:18px; font-weight:700; }
    .ex-direct-header p { margin:0; color:#6B7280; font-size:13px; line-height:1.5; }
    .ex-direct-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px 24px; padding:24px; }
    .ex-direct-step { display:flex; min-width:0; gap:10px; align-items:flex-start; margin:0; }
    .ex-direct-step:nth-of-type(n+3) { grid-column:1 / -1; }
    .ex-direct-step-number { display:grid; width:26px; height:26px; flex:0 0 26px; place-items:center; border:1px solid #E5E7EB; border-radius:6px; color:#4B5563; background:#F9FAFB; font-size:12px; font-weight:700; }
    .ex-direct-field { flex:1; min-width:0; }
    .ex-direct-field label { display:block; margin-bottom:7px; color:#374151; font-size:12px; font-weight:700; }
    .ex-direct-field select, .ex-direct-field input, .ex-direct-field textarea { width:100%; min-height:42px; padding:10px 12px; border:1px solid #D1D5DB; border-radius:6px; background:#fff; color:#1F2937; font-size:13px; }
    .ex-direct-field select:focus, .ex-direct-field input:focus, .ex-direct-field textarea:focus { outline:2px solid rgba(107,114,128,.15); border-color:#6B7280; }
    .ex-direct-hint { display:block; margin-top:6px; color:#657384; font-size:12px; line-height:1.45; }
    .ex-direct-actions { display:flex; grid-column:1 / -1; justify-content:flex-end; padding-top:4px; }
    .ex-direct-submit { min-height:44px; padding:10px 18px; border:0; border-radius:6px; color:#fff; background:#1F2937; font-size:13px; font-weight:700; cursor:pointer; }
    .ex-direct-submit:hover { background:#111827; }
    .ex-direct-submit:disabled { cursor:not-allowed; opacity:.55; }
    .ex-direct-notice { width:100%; margin:12px 0 0; padding:12px 15px; border:1px solid #E5E7EB; border-radius:8px; color:#4B5563; background:#F9FAFB; font-size:12px; line-height:1.5; }
    @media(max-width:700px){ .ex-direct-form{grid-template-columns:1fr; gap:17px; padding:18px}.ex-direct-step,.ex-direct-step:nth-child(n+3),.ex-direct-actions{grid-column:1}.ex-direct-header{padding:17px 18px}.ex-direct-actions,.ex-direct-submit{width:100%} }
    @media print { .ex-card { break-inside: avoid; page-break-inside: avoid; } }
    </style>

    <div class="ex-tab-bar no-print">
        <a href="router.php?page=exonerados&tab=pendientes" class="ex-tab <?= $tab == 'pendientes' ? 'active' : '' ?>">
            📩 Solicitudes Pendientes (<?= count($pendientes) ?>)
        </a>
        <a href="router.php?page=exonerados&tab=exentas" class="ex-tab <?= $tab == 'exentas' ? 'active' : '' ?>">
            🛡️ Viviendas Exoneradas (<?= count($exentas) ?>)
        </a>
        <a href="router.php?page=exonerados&tab=agregar" class="ex-tab <?= $tab == 'agregar' ? 'active' : '' ?>">
            ➕ Exonerar Directo
        </a>
    </div>

    <?php if ($tab == 'pendientes'): ?>
    <div class="ex-content">
        <p style="color:#6B7280; font-size:13px; margin-top:0;">
            Estas son las solicitudes que los encargados de calle han enviado para exonerar viviendas.
            Revisa el motivo y decide si apruebas o rechazas.
        </p>

        <?php if(empty($pendientes)): ?>
            <div style="text-align:center; padding:50px 20px; color:#9CA3AF;">
                <div style="font-size:50px; margin-bottom:10px;">📭</div>
                No hay solicitudes de exención pendientes.
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach($pendientes as $ex): ?>
                <div class="ex-card" id="exencion-<?= (int)$ex['id'] ?>" style="border-left:4px solid #8A7A5A;<?= (int)($_GET['exencion_id'] ?? 0) === (int)$ex['id'] ? 'background:#FFF7ED;' : '' ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                        <div>
                            <div style="font-weight:700; font-size:15px;">
                                🏠 Casa #<?= htmlspecialchars($ex['numero_casa'] ?: '?') ?>
                                <span style="font-weight:400; font-size:12px; color:#6B7280;">— <?= htmlspecialchars($ex['calle_nombre']) ?></span>
                            </div>
                            <div style="font-size:13px; color:#374151; margin-top:2px;">
                                <?= htmlspecialchars($ex['propietario']) ?>
                            </div>
                            <?php $identificacion = formato_identificacion($ex['cedula'] ?? '', $ex['cedula_tipo'] ?? 'V', $ex['otro_identificador'] ?? ''); ?>
                            <?php if ($identificacion !== ''): ?>
                                <div style="font-size:12px; color:#6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="ex-badge" style="background:#F2EEE7; color:#6E5F45;">⏳ PENDIENTE</span>
                    </div>

                    <div style="margin-top:10px; display:flex; gap:15px; flex-wrap:wrap; font-size:12px; color:#6B7280;">
                        <span><strong>Motivo:</strong> 
                            <?php $tipos = ['pobreza'=>'Pobreza / Dificultad económica', 'adulto_mayor'=>'Adulto mayor / Jubilado', 'empleado'=>'Empleado de la empresa', 'espacio_publico'=>'Espacio público', 'otro'=>'Otro']; ?>
                            <?= $tipos[$ex['tipo_exencion']] ?? $ex['tipo_exencion'] ?>
                        </span>
                        <span><strong>Solicitó:</strong> <?= htmlspecialchars(($ex['creador_nombre'] ?? '') . ' ' . ($ex['creador_apellido'] ?? '')) ?></span>
                        <span><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($ex['fecha_creacion'])) ?></span>
                    </div>

                    <?php if($ex['descripcion']): ?>
                        <div style="margin-top:8px; padding:10px; background:#F7F4EE; border-radius:6px; font-size:12px; color:#6E5F45;">
                            💬 <?= nl2br(htmlspecialchars($ex['descripcion'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="no-print" style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <form method="POST" action="router.php?page=exonerados&tab=pendientes" style="display:inline;">
                            <input type="hidden" name="form_type" value="aprobar_exencion">
                            <input type="hidden" name="exencion_id" value="<?= $ex['id'] ?>">
                            <button type="submit" style="background:#4C6E91; color:white; border:none; border-radius:6px; padding:8px 18px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:4px;"
                                onclick="return confirm('¿Aprobar esta exención?\n\nLa vivienda quedará 🛡️ EXENTA DE PAGO y ya no aparecerá en los cobros mensuales.')">
                                ✅ Aprobar Exención
                            </button>
                        </form>

                        <form method="POST" action="router.php?page=exonerados&tab=pendientes" style="display:flex; gap:4px; align-items:center;">
                            <input type="hidden" name="form_type" value="rechazar_exencion">
                            <input type="hidden" name="exencion_id" value="<?= $ex['id'] ?>">
                            <input type="text" name="motivo_rechazo" placeholder="Escribe el motivo del rechazo..." required
                                style="padding:8px 10px; border:1px solid #E5E7EB; border-radius:6px; font-size:12px; width:220px;">
                            <button type="submit" style="background:#9A6A6A; color:white; border:none; border-radius:6px; padding:8px 14px; font-size:12px; font-weight:600; cursor:pointer;">
                                ❌ Rechazar
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab == 'exentas'): ?>
    <div class="ex-content">
        <p style="color:#6B7280; font-size:13px; margin-top:0;">
            Lista de viviendas que están exoneradas del pago mensual. Puedes revocar la exención
            si la situación cambió y la vivienda debe volver a pagar.
        </p>

        <?php if(empty($exentas)): ?>
            <div style="text-align:center; padding:50px 20px; color:#9CA3AF;">
                <div style="font-size:50px; margin-bottom:10px;">🛡️</div>
                No hay viviendas exoneradas actualmente.
                <div style="font-size:12px; margin-top:6px;">Puedes agregar una desde la pestaña <strong>"Exonerar Directo"</strong>.</div>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:12px;">
                <?php foreach($exentas as $ex): ?>
                <div class="ex-card" style="border-left:4px solid #5A6B7D; background:#F7F8FA;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <div style="font-weight:700; font-size:14px;">
                                🏠 Casa #<?= htmlspecialchars($ex['numero_casa'] ?: '?') ?>
                            </div>
                            <div style="font-size:12px; color:#6B7280;">
                                <?= htmlspecialchars($ex['propietario']) ?>
                            </div>
                            <?php $identificacion = formato_identificacion($ex['cedula'] ?? '', $ex['cedula_tipo'] ?? 'V', $ex['otro_identificador'] ?? ''); ?>
                            <?php if ($identificacion !== ''): ?>
                                <div style="font-size:11px; color:#6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                            <?php endif; ?>
                            <div style="font-size:11px; color:#6B7280;">
                                📍 <?= htmlspecialchars($ex['calle_nombre']) ?>
                            </div>
                        </div>
                        <span class="ex-badge" style="background:#E7EDF3; color:#4A5568; border:none;">🛡️ EXENTA</span>
                    </div>

                    <div style="margin-top:10px; display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:11px; color:#6B7280;">
                        <span><span class="ex-label">Motivo</span><br>
                            <?php $tipos = ['pobreza'=>'Pobreza', 'adulto_mayor'=>'Adulto mayor', 'empleado'=>'Empleado', 'espacio_publico'=>'Espacio público', 'otro'=>'Otro']; ?>
                            <?= $tipos[$ex['tipo_exencion']] ?? $ex['tipo_exencion'] ?>
                        </span>
                        <span><span class="ex-label">Aprobó</span><br>
                            <?= htmlspecialchars(($ex['aprobador_nombre'] ?? '') . ' ' . ($ex['aprobador_apellido'] ?? '')) ?>
                        </span>
                        <span><span class="ex-label">Solicitó</span><br>
                            <?= htmlspecialchars(($ex['creador_nombre'] ?? '') . ' ' . ($ex['creador_apellido'] ?? '')) ?>
                        </span>
                        <span><span class="ex-label">Desde</span><br>
                            <?= date('d/m/Y', strtotime($ex['fecha_revision'])) ?>
                        </span>
                    </div>

                    <?php if($ex['descripcion']): ?>
                        <div style="margin-top:8px; font-size:11px; color:#4A5568; background:#EEF2F6; padding:6px 8px; border-radius:4px;">
                            💬 <?= nl2br(htmlspecialchars($ex['descripcion'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="no-print" style="margin-top:10px; padding-top:8px; border-top:1px solid #F3F4F6;">
                        <form method="POST" action="router.php?page=exonerados&tab=exentas" style="display:inline;">
                            <input type="hidden" name="form_type" value="quitar_exencion">
                            <input type="hidden" name="exencion_id" value="<?= $ex['id'] ?>">
                            <button type="submit" style="background:#F7F2F2; color:#8A5A5A; border:1px solid #E8D8D8; border-radius:6px; padding:6px 14px; font-size:12px; font-weight:600; cursor:pointer; width:100%;"
                                onclick="return confirm('¿REVOCAR esta exención?\n\nCasa #<?= htmlspecialchars($ex['numero_casa'] ?: '?') ?> — <?= htmlspecialchars($ex['propietario']) ?>\n\nAl revocar, la vivienda VOLVERÁ A GENERAR COBROS mensuales.\n¿Estás seguro?')">
                                🔄 Revocar Exención — Volverá a pagar
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab == 'agregar'): ?>
    <div class="ex-content">
        <div class="ex-direct-card">
            <div class="ex-direct-header">
                <h2>🛡️ Exonerar una vivienda</h2>
                <p>Elige el barrio, busca al cliente y registra el motivo de la exoneración.</p>
            </div>
            <?php if (empty($barriosAsignados)): ?>
                <div style="padding:24px; color:#657384;">
                    No tienes barrios asignados para realizar una exoneración directa.
                </div>
            <?php else: ?>
            <form method="POST" action="router.php?page=exonerados&tab=agregar" class="ex-direct-form" id="exonerarDirectoForm">
                <input type="hidden" name="form_type" value="agregar_exencion_directa">

                <div class="ex-direct-step">
                    <span class="ex-direct-step-number">1</span>
                    <div class="ex-direct-field">
                        <label for="exBarrioSelect">Barrio</label>
                        <select id="exBarrioSelect" name="barrio_id" required>
                            <option value="">Selecciona un barrio</option>
                            <?php foreach ($barriosAsignados as $barrio): ?>
                                <option value="<?= (int)$barrio['id'] ?>"><?= htmlspecialchars($barrio['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="ex-direct-hint">Solo aparecen los barrios asignados a tu cuenta.</span>
                    </div>
                </div>

                <div class="ex-direct-step">
                    <span class="ex-direct-step-number">2</span>
                    <div class="ex-direct-field">
                        <label for="exClienteBuscar">Buscar cliente</label>
                        <input type="search" id="exClienteBuscar" placeholder="Escribe el nombre o la cédula..." autocomplete="off" disabled>
                        <span class="ex-direct-hint">Filtra las viviendas del barrio seleccionado por nombre o identificación.</span>
                    </div>
                </div>

                <div class="ex-direct-step">
                    <span class="ex-direct-step-number">3</span>
                    <div class="ex-direct-field">
                        <label for="exViviendaSelect">Casa / cliente</label>
                        <select id="exViviendaSelect" name="vivienda_id" required disabled>
                            <option value="">Primero selecciona un barrio</option>
                            <?php foreach ($viviendas as $v): ?>
                                <?php
                                    $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? '');
                                    $textoVivienda = 'Casa #' . ($v['numero_casa'] ?: '?') . ' — ' . $v['propietario']
                                        . ($identificacion !== '' ? ' — ' . $identificacion : '')
                                        . ' — ' . $v['calle_nombre'];
                                    $textoBusqueda = trim(($v['propietario'] ?? '') . ' ' . ($v['cedula'] ?? '') . ' '
                                        . ($v['otro_identificador'] ?? '') . ' ' . ($v['numero_casa'] ?? ''));
                                ?>
                                <option value="<?= (int)$v['id'] ?>"
                                        data-barrio="<?= (int)$v['barrio_id'] ?>"
                                        data-search="<?= htmlspecialchars($textoBusqueda, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $v['exento_cobro'] ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($textoVivienda) ?><?= $v['exento_cobro'] ? ' — YA EXONERADA' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="ex-direct-hint" id="exClienteEstado" aria-live="polite">Selecciona un barrio para ver sus viviendas activas.</span>
                    </div>
                </div>

                <div class="ex-direct-step">
                    <span class="ex-direct-step-number">4</span>
                    <div class="ex-direct-field">
                        <label for="exTipoSelect">Motivo de la exoneración</label>
                        <select id="exTipoSelect" name="tipo_exencion" required>
                            <option value="pobreza">Pobreza / Dificultad económica</option>
                            <option value="adulto_mayor">Adulto mayor / Jubilado</option>
                            <option value="empleado">Empleado de la empresa</option>
                            <option value="espacio_publico">Espacio público</option>
                            <option value="otro">Otro motivo</option>
                        </select>
                    </div>
                </div>

                <div class="ex-direct-step">
                    <span class="ex-direct-step-number">5</span>
                    <div class="ex-direct-field">
                        <label for="exDescripcion">Descripción <span style="font-weight:400; color:#657384;">(opcional)</span></label>
                        <textarea id="exDescripcion" name="descripcion" rows="3" placeholder="Agrega información útil sobre el motivo de la exoneración..."></textarea>
                    </div>
                </div>

                <div class="ex-direct-actions">
                    <button type="submit" class="ex-direct-submit" id="exonerarDirectoSubmit" disabled>🛡️ Exonerar vivienda</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <div class="ex-direct-notice">
            <strong>Importante:</strong> La vivienda quedará exenta de los próximos cobros mensuales. Podrás revisar o revocar la exoneración desde la pestaña <strong>Viviendas Exoneradas</strong>.
        </div>
    </div>
    <script>
    (() => {
        const barrioSelect = document.getElementById('exBarrioSelect');
        const buscarInput = document.getElementById('exClienteBuscar');
        const viviendaSelect = document.getElementById('exViviendaSelect');
        const estadoCliente = document.getElementById('exClienteEstado');
        const submitButton = document.getElementById('exonerarDirectoSubmit');
        if (!barrioSelect || !buscarInput || !viviendaSelect || !estadoCliente || !submitButton) return;

        const viviendaOptions = Array.from(viviendaSelect.options).slice(1);
        const filtrarViviendas = () => {
            const barrioId = barrioSelect.value;
            const consulta = buscarInput.value.trim().toLocaleLowerCase();
            let visibles = 0;
            let disponibles = 0;

            viviendaOptions.forEach((option) => {
                const coincideBarrio = barrioId !== '' && option.dataset.barrio === barrioId;
                const texto = (option.dataset.search || '').toLocaleLowerCase();
                const coincideBusqueda = consulta === '' || texto.includes(consulta);
                option.hidden = !(coincideBarrio && coincideBusqueda);
                if (coincideBarrio && coincideBusqueda) {
                    visibles++;
                    if (!option.disabled) disponibles++;
                }
            });

            buscarInput.disabled = barrioId === '';
            viviendaSelect.disabled = barrioId === '';
            submitButton.disabled = barrioId === '' || viviendaSelect.value === '';
            if (barrioId === '') {
                viviendaSelect.options[0].textContent = 'Primero selecciona un barrio';
                estadoCliente.textContent = 'Selecciona un barrio para ver sus viviendas activas.';
            } else if (visibles === 0) {
                viviendaSelect.options[0].textContent = 'No se encontraron clientes';
                estadoCliente.textContent = 'No hay viviendas que coincidan con esa búsqueda en este barrio.';
            } else {
                viviendaSelect.options[0].textContent = 'Selecciona una casa / cliente';
                estadoCliente.textContent = disponibles === 0
                    ? 'Las viviendas encontradas ya están exoneradas.'
                    : `${disponibles} vivienda${disponibles === 1 ? '' : 's'} disponible${disponibles === 1 ? '' : 's'}.`;
            }
        };

        barrioSelect.addEventListener('change', () => {
            viviendaSelect.value = '';
            buscarInput.value = '';
            filtrarViviendas();
        });
        buscarInput.addEventListener('input', () => {
            viviendaSelect.value = '';
            filtrarViviendas();
        });
        viviendaSelect.addEventListener('change', filtrarViviendas);
        document.getElementById('exonerarDirectoForm').addEventListener('submit', (event) => {
            if (!viviendaSelect.value) {
                event.preventDefault();
                viviendaSelect.focus();
            } else if (!window.confirm('¿Exonerar esta vivienda? Quedará exenta de los próximos cobros mensuales.')) {
                event.preventDefault();
            }
        });
        filtrarViviendas();
    })();
    </script>
    <?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
