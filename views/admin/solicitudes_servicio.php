<?php
// views/admin/solicitudes_servicio.php
// Bandeja de solicitudes de cotización enviadas desde el portal público.
$user = check_dashboard_access([1, 2]);

$estados = ['Pendiente', 'Atendido', 'Descartado'];
$f_estado = $_GET['estado'] ?? '';

$sql = "SELECT id, representante, telefono, barrio, tipo_contrato, correo,
               plan, cantidad, monto_estimado_usd, detalles, estado, fecha
        FROM solicitudes_servicio";
$params = [];
if (in_array($f_estado, $estados, true)) {
    $sql .= " WHERE estado = ?";
    $params[] = $f_estado;
}
$sql .= " ORDER BY fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Solicitudes de Servicio - EPSIC";
$header_title = "Solicitudes de Servicio";
$header_subtitle = "Cotizaciones enviadas desde el portal público.";

ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<div style="display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap;">
    <a href="router.php?page=solicitudes_servicio" class="btn-primary" style="text-decoration:none; background:<?= $f_estado===''?'#4C6E91':'#6B7280' ?>;">Todas</a>
    <?php foreach ($estados as $e): ?>
        <a href="router.php?page=solicitudes_servicio&estado=<?= urlencode($e) ?>" class="btn-primary" style="text-decoration:none; background:<?= $f_estado===$e?'#4C6E91':'#6B7280' ?>;"><?= htmlspecialchars($e) ?></a>
    <?php endforeach; ?>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <div class="table-wrap">
        <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F9FAFB; text-align:left;">
                    <th style="padding:12px 16px;">Fecha</th>
                    <th style="padding:12px 16px;">Representante</th>
                    <th style="padding:12px 16px;">Teléfono</th>
                    <th style="padding:12px 16px;">Correo</th>
                    <th style="padding:12px 16px;">Barrio</th>
                    <th style="padding:12px 16px;">Plan / Cantidad</th>
                    <th style="padding:12px 16px;">Estimado</th>
                    <th style="padding:12px 16px;">Estado</th>
                    <th style="padding:12px 16px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($solicitudes)): ?>
                    <tr><td colspan="9" style="padding:24px; text-align:center; color:#9CA3AF;">No hay solicitudes registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($solicitudes as $s): ?>
                        <tr id="solicitud-<?= (int)$s['id'] ?>" style="border-top:1px solid #F3F4F6; vertical-align:top;<?= (int)($_GET['solicitud_id'] ?? 0) === (int)$s['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding:12px 16px; white-space:nowrap;"><?= !empty($s['fecha']) ? date('d/m/Y H:i', strtotime($s['fecha'])) : '—' ?></td>
                            <td style="padding:12px 16px;">
                                <strong><?= htmlspecialchars($s['representante']) ?></strong>
                                <?php if (!empty($s['detalles'])): ?>
                                    <div style="font-size:12px; color:#6B7280; margin-top:4px; max-width:220px;"><?= nl2br(htmlspecialchars($s['detalles'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 16px;"><?= htmlspecialchars($s['telefono']) ?></td>
                            <td style="padding:12px 16px;"><?= htmlspecialchars($s['correo'] ?? '—') ?></td>
                            <td style="padding:12px 16px;"><?= htmlspecialchars($s['barrio']) ?></td>
                            <td style="padding:12px 16px;">
                                <?= htmlspecialchars($s['plan'] ?? $s['tipo_contrato'] ?? '—') ?>
                                <?php if (!empty($s['cantidad'])): ?>
                                    <div style="font-size:12px; color:#6B7280; margin-top:3px;"><?= (int)$s['cantidad'] ?> unidad(es)</div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 16px; white-space:nowrap;">
                                <?= $s['monto_estimado_usd'] !== null ? monto($s['monto_estimado_usd']) : 'A calcular' ?>
                            </td>
                            <td style="padding:12px 16px;">
                                <span class="badge <?= $s['estado']==='Atendido'?'admin':($s['estado']==='Pendiente'?'gestor':'jefe') ?>"><?= htmlspecialchars($s['estado']) ?></span>
                            </td>
                            <td style="padding:12px 16px;">
                                <form action="router.php" method="POST" style="display:flex; gap:6px; align-items:center; margin:0;">
                                    <input type="hidden" name="action" value="actualizar_solicitud_servicio">
                                    <input type="hidden" name="page" value="solicitudes_servicio">
                                    <input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>">
                                    <select name="estado" style="padding:4px 6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
                                        <?php foreach ($estados as $e): ?>
                                            <option value="<?= $e ?>" <?= $s['estado']===$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
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
