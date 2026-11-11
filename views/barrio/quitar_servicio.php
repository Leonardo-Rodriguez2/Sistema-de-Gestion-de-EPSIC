<?php
// views/barrio/quitar_servicio.php
$user = check_dashboard_access([5]);
$f_search = trim((string)($_GET['search'] ?? ''));
$f_estado = (string)($_GET['estado'] ?? '');
$estados = ['Activo', 'Suspendido'];
if (!in_array($f_estado, $estados, true)) $f_estado = '';

// 1. Obtener viviendas activas del barrio
$sql = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa, v.direccion, v.estado_servicio, c.nombre as calle_nombre,
               (SELECT SUM(monto) FROM cobros WHERE vivienda_id = v.id AND estado NOT IN ('Pagado','Anulado')) as deuda_total
        FROM viviendas v 
        JOIN calles c ON v.calle_id = c.id
        JOIN detalles_encargado_barrio deb ON v.barrio_id = deb.barrio_id
        WHERE deb.usuario_id = ? AND v.estado_servicio != 'Anulado'";
$params = [$user['id']];
if ($f_estado !== '') {
    $sql .= " AND v.estado_servicio = ?";
    $params[] = $f_estado;
}
if ($f_search !== '') {
    $sql .= " AND (v.propietario LIKE ? OR v.cedula LIKE ? OR v.direccion LIKE ? OR v.numero_casa LIKE ? OR c.nombre LIKE ?)";
    for ($i = 0; $i < 5; $i++) $params[] = "%$f_search%";
}
$sql .= "
        ORDER BY c.nombre, v.numero_casa";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viviendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Quitar Servicio - EcoCusco";
$header_title = "Suspensión Permanente";
$header_subtitle = "Anula el servicio de viviendas con deudas críticas o por solicitud directa.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <div class="card">
        <h3 style="margin-top:0;">📋 Viviendas Disponibles</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Al anular el servicio, la vivienda dejará de recibir cobros mensuales y se registrará su deuda actual.
        </p>
        <form method="GET" action="router.php" class="unified-filter no-print">
            <input type="hidden" name="page" value="quitar_servicio">
            <div style="flex:1; min-width:230px;">
                <label style="display:block; font-size:10px; font-weight:700; color:#657384; margin-bottom:5px;">BUSCAR PROPIETARIO / CÉDULA / CASA</label>
                <input type="search" name="search" value="<?= htmlspecialchars($f_search) ?>" placeholder="Nombre, identificación, dirección..." style="width:100%; min-height:38px; padding:8px 10px; border:1px solid #D9E1E7;">
            </div>
            <div style="width:170px;">
                <label style="display:block; font-size:10px; font-weight:700; color:#657384; margin-bottom:5px;">ESTADO</label>
                <select name="estado" style="width:100%; min-height:38px; padding:8px; border:1px solid #D9E1E7;">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $estado): ?><option value="<?= htmlspecialchars($estado) ?>" <?= $f_estado === $estado ? 'selected' : '' ?>><?= htmlspecialchars($estado) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="router.php?page=quitar_servicio" class="btn-cancel" style="padding:9px 14px; text-decoration:none; background:#E9EFF4;">Limpiar</a>
        </form>
        
        <div class="table-wrap">
            <table class="table-mini" data-no-table-search="true" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Calle / N°</th>
                        <th style="padding: 12px;">Propietario</th>
                        <th style="padding: 12px;">Estado Actual</th>
                        <th style="padding: 12px; text-align: center;">Deuda Acumulada</th>
                        <th style="padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($viviendas)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay viviendas activas en este momento.</td></tr>
                    <?php endif; ?>
                    <?php foreach($viviendas as $v): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($v['calle_nombre']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;">Casa #<?= htmlspecialchars($v['numero_casa']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 600;"><?= htmlspecialchars($v['propietario']) ?></div>
                                <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: #9CA3AF;"><?= htmlspecialchars($v['direccion']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                $serv_bg = ($v['estado_servicio'] == 'Activo') ? '#E9EFF4' : '#F2EEE7';
                                $serv_color = ($v['estado_servicio'] == 'Activo') ? '#33485C' : '#6E5F45';
                                ?>
                                <span class="badge" style="background: <?= $serv_bg ?>; color: <?= $serv_color ?>; font-size: 10px;"><?= $v['estado_servicio'] ?></span>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="font-weight: 800; color: #7A4F4F;">Bs. <?= number_format($v['deuda_total'] ?: 0, 2) ?></div>
                                <div style="font-size: 9px; color: #6B7280;">Monto a registrar</div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <form method="POST" onsubmit="return confirm('¿ESTÁS SEGURO? Esta acción anulará el servicio permanentemente y registrará la deuda de Bs. <?= number_format($v['deuda_total'] ?: 0, 2) ?>.')">
                                    <input type="hidden" name="form_type" value="ordenar_baja">
                                    <input type="hidden" name="vivienda_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn-primary" style="background: #9A6A6A; border:none; padding: 6px 12px; font-size: 11px;">Quitar Servicio</button>
                                </form>
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
