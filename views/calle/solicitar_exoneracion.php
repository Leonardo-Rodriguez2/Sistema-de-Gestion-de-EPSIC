<?php
$user = check_dashboard_access([6]);

$calleStmt = $pdo->prepare("SELECT dc.calle_id, c.barrio_id, c.nombre as calle_nombre FROM detalles_encargado_calle dc JOIN calles c ON dc.calle_id = c.id WHERE dc.usuario_id = ?");
$calleStmt->execute([$user['id']]);
$calleData = $calleStmt->fetch(PDO::FETCH_ASSOC);
$calle_id = $calleData['calle_id'];
$barrio_id = $calleData['barrio_id'];
$calle_nombre = $calleData['calle_nombre'];

$viviendasStmt = $pdo->prepare("SELECT id, numero_casa, propietario, cedula, cedula_tipo, otro_identificador, exento_cobro FROM viviendas WHERE calle_id = ? AND estado_servicio = 'Activo' ORDER BY numero_casa");
$viviendasStmt->execute([$calle_id]);
$viviendas = $viviendasStmt->fetchAll(PDO::FETCH_ASSOC);

$exencionesStmt = $pdo->prepare("
    SELECT e.*, v.numero_casa, v.propietario,
           v.cedula as v_cedula, v.cedula_tipo as v_cedula_tipo, v.otro_identificador as v_otro_identificador
    FROM exenciones_cobro e 
    JOIN viviendas v ON e.vivienda_id = v.id 
    WHERE e.calle_id = ? 
    ORDER BY e.fecha_creacion DESC
");
$exencionesStmt->execute([$calle_id]);
$exenciones = $exencionesStmt->fetchAll(PDO::FETCH_ASSOC);

// Vista dentro del cuadro de exenciones: solicitudes enviadas o casas ya exoneradas.
$vistaExencion = trim((string)($_GET['vista'] ?? 'solicitudes'));
if (!in_array($vistaExencion, ['solicitudes', 'exoneradas'], true)) {
    $vistaExencion = 'solicitudes';
}

$exoneradas = array_values(array_filter($viviendas, static function ($v) {
    return !empty($v['exento_cobro']);
}));

// Datos de la exención aprobada más reciente por vivienda (si existe).
$aprobadasPorVivienda = [];
foreach ($exenciones as $ex) {
    $vid = (int)($ex['vivienda_id'] ?? 0);
    if ($vid && $ex['estado'] === 'Aprobado' && !isset($aprobadasPorVivienda[$vid])) {
        $aprobadasPorVivienda[$vid] = $ex;
    }
}

$tiposExencion = [
    'pobreza'        => 'Pobreza / Dificultad económica',
    'adulto_mayor'   => 'Adulto mayor / Jubilado',
    'empleado'       => 'Empleado de la empresa',
    'espacio_publico' => 'Espacio público',
    'otro'           => 'Otro',
];

$title = "Solicitar Exoneración - EcoReciclaje";
$header_title = "Exoneraciones de Cobro";
$header_subtitle = "Solicita que una vivienda quede exonerada del pago mensual.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <div class="grid">
        <div class="card">
            <h3 style="margin-top:0;">Nueva Solicitud de Exoneración</h3>
            <p style="color:#6B7280; font-size:13px; margin-bottom:16px;">
                Selecciona una vivienda de tu calle (<strong><?= htmlspecialchars($calle_nombre) ?></strong>) y explica el motivo.
            </p>
            <form method="POST" action="router.php?page=solicitar_exoneracion">
                <input type="hidden" name="form_type" value="solicitar_exoneracion">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight:700; font-size:12px; color:#374151; display:block; margin-bottom:4px;">VIVIENDA</label>
                    <select name="vivienda_id" required style="width:100%; padding:10px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
                        <option value="">-- Seleccionar vivienda --</option>
                        <?php foreach($viviendas as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $v['exento_cobro'] ? 'disabled' : '' ?>>
                                Casa #<?= htmlspecialchars($v['numero_casa'] ?: '?') ?> - <?= htmlspecialchars($v['propietario']) ?>
                                <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                <?= $identificacion !== '' ? ' - ' . htmlspecialchars($identificacion) : '' ?>
                                <?= $v['exento_cobro'] ? '(YA EXENTA)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-weight:700; font-size:12px; color:#374151; display:block; margin-bottom:4px;">TIPO DE EXONERACIÓN</label>
                    <select name="tipo_exencion" required style="width:100%; padding:10px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
                        <option value="pobreza">Pobreza / Dificultad económica</option>
                        <option value="adulto_mayor">Adulto mayor / Jubilado</option>
                        <option value="empleado">Empleado de la empresa</option>
                        <option value="espacio_publico">Espacio público</option>
                        <option value="otro">Otro (especificar)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight:700; font-size:12px; color:#374151; display:block; margin-bottom:4px;">DESCRIPCIÓN / MOTIVO</label>
                    <textarea name="descripcion" rows="3" placeholder="Explica con detalle por qué esta vivienda debería quedar exenta..." style="width:100%; padding:10px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px; resize:vertical;"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px;">
                    Enviar Solicitud al Barrio
                </button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Mis Solicitudes de Exención</h3>
            <p style="color:#6B7280; font-size:13px; margin-bottom:16px;">
                Historial de solicitudes enviadas al encargado de barrio.
            </p>

            <style>
            .exn-tabs { display:flex; gap:4px; padding:4px; margin-bottom:16px; background:#F4F6F8; border:1px solid #E5E7EB; border-radius:8px; }
            .exn-tab { flex:1; display:flex; align-items:center; justify-content:center; gap:6px; padding:9px 10px; border-radius:6px; color:#6B7280; font-size:12px; font-weight:700; text-decoration:none; text-align:center; transition:background .15s, color .15s, box-shadow .15s; }
            .exn-tab:hover { color:#1F2937; background:#fff; transform:none; }
            .exn-tab.active { color:#1F2937; background:#fff; box-shadow:0 1px 3px rgba(17,24,39,.12); }
            .exn-count { padding:1px 7px; border-radius:10px; background:#E5E7EB; color:#4B5563; font-size:10px; font-weight:700; line-height:1.5; }
            .exn-tab.active .exn-count { background:#203d4b; color:#fff; }
            .exn-list { display:flex; flex-direction:column; gap:10px; }
            .exn-item { padding:12px; border:1px solid #E5E7EB; border-left:3px solid #D6E1EA; border-radius:8px; background:#fff; }
            .exn-item.exn-exenta { border-left-color:#5A6B7D; background:#F9FAFB; }
            .exn-item-head { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:6px; }
            .exn-item-title { font-weight:700; font-size:13px; color:#1F2937; }
            .exn-meta { font-size:11px; color:#6B7280; line-height:1.5; }
            .exn-desc { margin-top:5px; font-size:11px; color:#9CA3AF; font-style:italic; }
            .exn-date { margin-top:4px; font-size:10px; color:#9CA3AF; }
            .exn-empty { padding:34px 20px; text-align:center; color:#9CA3AF; }
            .exn-empty .exn-empty-icon { font-size:38px; line-height:1; margin-bottom:8px; }
            @media(max-width:520px) {
                .exn-tabs { flex-direction:column; }
                .exn-tab { justify-content:flex-start; }
            }
            </style>

            <div class="exn-tabs">
                <a href="router.php?page=solicitar_exoneracion&vista=solicitudes"
                   class="exn-tab <?= $vistaExencion === 'solicitudes' ? 'active' : '' ?>">
                    📩 Mis Solicitudes
                    <span class="exn-count"><?= count($exenciones) ?></span>
                </a>
                <a href="router.php?page=solicitar_exoneracion&vista=exoneradas"
                   class="exn-tab <?= $vistaExencion === 'exoneradas' ? 'active' : '' ?>">
                    🛡️ Casas Exoneradas
                    <span class="exn-count"><?= count($exoneradas) ?></span>
                </a>
            </div>

            <?php if ($vistaExencion === 'solicitudes'): ?>
                <?php if (empty($exenciones)): ?>
                    <div class="exn-empty">
                        <div class="exn-empty-icon">📩</div>
                        Aún no has realizado solicitudes de exoneración.
                    </div>
                <?php else: ?>
                    <div class="exn-list">
                        <?php foreach ($exenciones as $ex): ?>
                            <div class="exn-item">
                                <div class="exn-item-head">
                                    <span class="exn-item-title">
                                        Casa #<?= htmlspecialchars($ex['numero_casa'] ?: '?') ?>
                                    </span>
                                    <?php if ($ex['estado'] == 'Pendiente'): ?>
                                        <span class="badge" style="background:#F2EEE7; color:#6E5F45; border:none;">PENDIENTE</span>
                                    <?php elseif ($ex['estado'] == 'Aprobado'): ?>
                                        <span class="badge" style="background:#E9EFF4; color:#33485C; border:none;">APROBADO</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#F4EDED; color:#7A4F4F; border:none;">RECHAZADO</span>
                                    <?php endif; ?>
                                </div>
                                <div class="exn-meta">
                                    <?= htmlspecialchars($ex['propietario']) ?> —
                                    <?php $identificacion = formato_identificacion($ex['v_cedula'] ?? '', $ex['v_cedula_tipo'] ?? 'V', $ex['v_otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?><?= htmlspecialchars($identificacion) ?> — <?php endif; ?>
                                    Tipo: <?= htmlspecialchars($tiposExencion[$ex['tipo_exencion']] ?? $ex['tipo_exencion']) ?>
                                </div>
                                <?php if ($ex['descripcion']): ?>
                                    <div class="exn-desc">
                                        <?= htmlspecialchars($ex['descripcion']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="exn-date">
                                    <?= date('d/m/Y', strtotime($ex['fecha_creacion'])) ?>
                                    <?php if ($ex['motivo_rechazo']): ?> — Motivo: <?= htmlspecialchars($ex['motivo_rechazo']) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($exoneradas)): ?>
                    <div class="exn-empty">
                        <div class="exn-empty-icon">🛡️</div>
                        No hay casas exoneradas en tu calle.
                    </div>
                <?php else: ?>
                    <div class="exn-list">
                        <?php foreach ($exoneradas as $v): ?>
                            <?php $det = $aprobadasPorVivienda[(int)$v['id']] ?? null; ?>
                            <div class="exn-item exn-exenta">
                                <div class="exn-item-head">
                                    <span class="exn-item-title">
                                        🏠 Casa #<?= htmlspecialchars($v['numero_casa'] ?: '?') ?>
                                    </span>
                                    <span class="badge" style="background:#E7EDF3; color:#4A5568; border:none;">EXONERADA</span>
                                </div>
                                <div class="exn-meta">
                                    <?= htmlspecialchars($v['propietario']) ?>
                                    <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?> — <?= htmlspecialchars($identificacion) ?><?php endif; ?>
                                </div>
                                <?php if ($det): ?>
                                    <div class="exn-meta">
                                        Motivo: <?= htmlspecialchars($tiposExencion[$det['tipo_exencion']] ?? $det['tipo_exencion']) ?>
                                    </div>
                                    <?php if ($det['descripcion']): ?>
                                        <div class="exn-desc"><?= htmlspecialchars($det['descripcion']) ?></div>
                                    <?php endif; ?>
                                    <div class="exn-date">
                                        Exonerada desde <?= date('d/m/Y', strtotime($det['fecha_revision'] ?: $det['fecha_creacion'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="exn-date">Exenta del pago mensual.</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
