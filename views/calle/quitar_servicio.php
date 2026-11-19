<?php
// views/calle/quitar_servicio.php
$user = check_dashboard_access([6]);
$f_search = trim((string)($_GET['search'] ?? ''));

// 1. Obtener la calle asignada
$calleStmt = $pdo->prepare("SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ?");
$calleStmt->execute([$user['id']]);
$calle_id = $calleStmt->fetchColumn();

// 2. Obtener viviendas activas de esa calle
$sql = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa, v.direccion, v.estado_servicio,
               (SELECT SUM(monto) FROM cobros WHERE vivienda_id = v.id AND estado NOT IN ('Pagado','Anulado')) as deuda_total,
               (SELECT COUNT(*) FROM cobros WHERE vivienda_id = v.id AND estado NOT IN ('Pagado','Anulado')) as meses_pendientes
        FROM viviendas v 
        WHERE v.calle_id = ? AND v.estado_servicio != 'Anulado'";
$params = [$calle_id];
if ($f_search !== '') {
    $sql .= " AND (v.propietario LIKE ? OR v.cedula LIKE ? OR v.direccion LIKE ? OR v.numero_casa LIKE ?)";
    for ($i = 0; $i < 4; $i++) $params[] = "%$f_search%";
}
$sql .= "
        ORDER BY v.numero_casa ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viviendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Solicitar Baja - EcoReciclaje";
$header_title = "Retiro de Servicio";
$header_subtitle = "Envia una solicitud al barrio para anular el servicio de una vivienda.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <div class="card">
        <h3 style="margin-top:0;">🛡️ Gestión de Retiros</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Selecciona la vivienda que deseas dar de baja. Se enviará el monto de deuda acumulada para aprobación.
        </p>
        <form method="GET" action="router.php" class="unified-filter no-print">
            <input type="hidden" name="page" value="quitar_servicio">
            <div style="flex:1; min-width:240px;">
                <label style="display:block; font-size:10px; font-weight:700; color:#657384; margin-bottom:5px;">BUSCAR PROPIETARIO / CÉDULA / CASA</label>
                <input type="search" name="search" value="<?= htmlspecialchars($f_search) ?>" placeholder="Nombre, identificación, dirección..." style="width:100%; min-height:38px; padding:8px 10px; border:1px solid #D9E1E7;">
            </div>
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="router.php?page=quitar_servicio" class="btn-cancel" style="padding:9px 14px; text-decoration:none; background:#E9EFF4;">Limpiar</a>
        </form>
        
        <div class="table-wrap">
            <table class="table-mini" data-no-table-search="true" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Casa</th>
                        <th style="padding: 12px;">Propietario</th>
                        <th style="padding: 12px; text-align: center;">Deuda / Pendientes</th>
                        <th style="padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($viviendas)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay viviendas activas en tu calle.</td></tr>
                    <?php endif; ?>
                    <?php foreach($viviendas as $v): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-weight: 800; color: #111827;">#<?= htmlspecialchars($v['numero_casa']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;">ID: <?= $v['id'] ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 600;"><?= htmlspecialchars($v['propietario']) ?></div>
                                <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 10px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 10px; color: #9CA3AF;"><?= htmlspecialchars($v['direccion']) ?></div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="font-weight: 800; color: #7A4F4F;"><?= monto($v['deuda_total'] ?: 0) ?></div>
                                <div style="font-size: 10px; color: #6B7280;"><?= $v['meses_pendientes'] ?> conceptos</div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <form method="POST" onsubmit="return confirm('¿Solicitar la baja de esta vivienda?')">
                                    <input type="hidden" name="form_type" value="solicitar_baja">
                                    <input type="hidden" name="vivienda_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn-primary" style="background: white; color: #9A6A6A; border: 1px solid #9A6A6A; padding: 6px 12px; font-size: 11px; font-weight: 700;">Solicitar Baja</button>
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
