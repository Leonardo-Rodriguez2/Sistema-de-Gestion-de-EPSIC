<?php
// views/admin/quitar_servicio.php
$user = check_dashboard_access([1]);

$f_search = trim((string)($_GET['search'] ?? ''));
$f_barrio = (int)($_GET['barrio_id'] ?? 0);
$f_calle = (int)($_GET['calle_id'] ?? 0);

// 1. Obtener todas las viviendas activas/suspendidas del sistema
$sql = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa, v.direccion, v.estado_servicio, 
               c.nombre as calle_nombre, b.nombre as barrio_nombre,
               (SELECT SUM(monto) FROM cobros WHERE vivienda_id = v.id AND estado NOT IN ('Pagado','Anulado')) as deuda_total
        FROM viviendas v 
        JOIN calles c ON v.calle_id = c.id
        JOIN barrios b ON v.barrio_id = b.id
        WHERE v.estado_servicio != 'Anulado'";
$params = [];
if ($f_barrio > 0) {
    $sql .= " AND v.barrio_id = :barrio_id";
    $params[':barrio_id'] = $f_barrio;
}
if ($f_calle > 0) {
    $sql .= " AND v.calle_id = :calle_id";
    $params[':calle_id'] = $f_calle;
}
if ($f_search !== '') {
    $sql .= " AND (v.propietario LIKE :search_propietario OR v.cedula LIKE :search_cedula
                   OR v.direccion LIKE :search_direccion OR v.numero_casa LIKE :search_casa
                   OR c.nombre LIKE :search_calle OR b.nombre LIKE :search_barrio)";
    foreach (['propietario', 'cedula', 'direccion', 'casa', 'calle', 'barrio'] as $campo) {
        $params[":search_$campo"] = "%$f_search%";
    }
}
$sql .= "
        ORDER BY b.nombre, c.nombre, v.numero_casa";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viviendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$calles = $pdo->query("SELECT id, nombre, barrio_id FROM calles ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$title = "Quitar Servicio (Admin) - EcoReciclaje";
$header_title = "Control Maestro de Servicios";
$header_subtitle = "Anula servicios de cualquier vivienda del sistema de forma directa y permanente.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <div class="card">
        <h3 style="margin-top:0;">🛑 Anulación Directa por Administración</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Esta acción no requiere solicitud previa. El servicio se anulará de inmediato y se registrará la deuda actual para auditoría.
        </p>
        <form method="GET" action="router.php" class="unified-filter no-print">
            <input type="hidden" name="page" value="quitar_servicio">
            <div style="flex:1; min-width:220px;">
                <label style="display:block; font-size:10px; font-weight:700; color:#657384; margin-bottom:5px;">BUSCAR PROPIETARIO / CÉDULA / CASA</label>
                <input type="search" name="search" value="<?= htmlspecialchars($f_search) ?>" placeholder="Nombre, identificación, dirección..." style="width:100%; min-height:38px; padding:8px 10px; border:1px solid #D9E1E7;">
            </div>
            <div style="width:180px;">
                <label style="display:block; font-size:10px; font-weight:700; color:#657384; margin-bottom:5px;">BARRIO</label>
                <select name="barrio_id" style="width:100%; min-height:38px; padding:8px; border:1px solid #D9E1E7;">
                    <option value="0">Todos</option>
                    <?php foreach ($barrios as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $f_barrio === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="width:180px;">
                <label style="display:block; font-size:10px; font-weight:700; color:#657384; margin-bottom:5px;">CALLE</label>
                <select name="calle_id" style="width:100%; min-height:38px; padding:8px; border:1px solid #D9E1E7;">
                    <option value="0">Todas</option>
                    <?php foreach ($calles as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $f_calle === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="router.php?page=quitar_servicio" class="btn-cancel" style="padding:9px 14px; text-decoration:none; background:#E9EFF4;">Limpiar</a>
        </form>
        
        <div class="table-wrap">
            <table class="table-mini" data-no-table-search="true" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Barrio / Calle</th>
                        <th style="padding: 12px;">Vivienda / Propietario</th>
                        <th style="padding: 12px; text-align: center;">Deuda Acumulada</th>
                        <th style="padding: 12px; text-align: center;">Acción Administrativa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($viviendas)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay viviendas activas en el sistema.</td></tr>
                    <?php endif; ?>
                    <?php foreach($viviendas as $v): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-weight: 800; color: #33485C;"><?= htmlspecialchars($v['barrio_nombre']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;"><?= htmlspecialchars($v['calle_nombre']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($v['propietario']) ?></div>
                                <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: #6B7280;">Casa #<?= htmlspecialchars($v['numero_casa']) ?></div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="font-weight: 800; color: #7A4F4F;"><?= monto($v['deuda_total'] ?: 0) ?></div>
                                <div style="font-size: 10px; color: #6B7280;">Monto al retiro</div>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <form method="POST" action="router.php" onsubmit="return confirm('¿EJECUTAR BAJA ADMINISTRATIVA? Esta acción es instantánea y permanente.')">
                                    <input type="hidden" name="form_type" value="ordenar_baja">
                                    <input type="hidden" name="page" value="quitar_servicio">
                                    <input type="hidden" name="vivienda_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn-primary" style="background: #111827; color: white; border: none; padding: 8px 15px; font-size: 11px; font-weight: 800; border-radius: 4px;">ANULAR SERVICIO</button>
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
