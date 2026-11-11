<?php
// views/barrio/solicitudes_renovacion.php
$user = check_dashboard_access([5]);

// 1. Obtener solicitudes de RENOVACION pendientes
$sql = "SELECT s.*, c.nombre as calle_nombre, u.nombre as solicitante_nombre 
        FROM solicitudes_vivienda s 
        JOIN calles c ON s.calle_id = c.id
        JOIN usuarios u ON s.creado_por = u.id
        JOIN viviendas v ON s.vivienda_id = v.id
        JOIN detalles_encargado_barrio deb ON c.barrio_id = deb.barrio_id
        WHERE deb.usuario_id = ? AND s.tipo = 'Renovacion' AND s.estado = 'Pendiente' AND v.estado_servicio != 'Activo'
        GROUP BY s.vivienda_id
        ORDER BY s.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$renovaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Órdenes de Renovación - EcoCusco";
$header_title = "Reactivación de Servicios";
$header_subtitle = "Aprueba las renovaciones de viviendas que han regularizado su situación.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <div class="card" style="border-left: 4px solid #4C6E91;">
        <h3 style="margin-top:0;">🔄 Solicitudes de Renovación</h3>
        <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
            Al aprobar, la vivienda volverá a figurar como ACTIVA en el sistema de recolección.
        </p>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha / Calle</th>
                        <th style="padding: 12px;">Propietario / Vivienda</th>
                        <th style="padding: 12px; text-align: right;">Deuda Pendiente</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($renovaciones)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay solicitudes de renovación pendientes.</td></tr>
                    <?php endif; ?>
                    <?php foreach($renovaciones as $r): ?>
                        <tr id="solicitud-<?= (int)$r['id'] ?>" style="border-bottom: 1px solid #F3F4F6;<?= (int)($_GET['solicitud_id'] ?? 0) === (int)$r['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding: 12px;">
                                <div style="font-weight: 700; color: #111827;"><?= htmlspecialchars($r['calle_nombre']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;"><?= date('d/m/Y', strtotime($r['fecha_creacion'])) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <?php 
                                    $vStmt = $pdo->prepare("SELECT propietario, numero_casa, cedula, cedula_tipo, otro_identificador FROM viviendas WHERE id = ?");
                                    $vStmt->execute([$r['vivienda_id']]);
                                    $v = $vStmt->fetch();
                                ?>
                                <div style="font-weight: 700;"><?= htmlspecialchars($v['propietario'] ?? 'N/A') ?></div>
                                <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?><div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                                <div style="font-size: 11px; color: #6B7280;">ID Casa: #<?= $r['vivienda_id'] ?> | Casa #<?= htmlspecialchars($v['numero_casa'] ?? '#') ?></div>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <div style="font-weight: 800; color: #33485C;">S/ <?= number_format($r['monto_deuda'], 2) ?></div>
                                <div style="font-size: 9px; color: #6B7280;"><?= htmlspecialchars($r['detalles_deuda']) ?></div>
                                <?php if (!empty($r['referencia_pago'])): ?>
                                    <div style="margin-top:7px; text-align:left; font-size:11px; color:#455765;">
                                        <strong>Referencia:</strong> <?= htmlspecialchars($r['referencia_pago']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($r['comprobante_pago'])): ?>
                                    <a href="<?= htmlspecialchars($r['comprobante_pago']) ?>" target="_blank" rel="noopener noreferrer"
                                       style="display:inline-flex; align-items:center; gap:5px; margin-top:7px; padding:6px 9px; border:1px solid #d9e1e7; border-radius:6px; color:#176b87; background:#f8fafb; font-size:11px; font-weight:600; text-decoration:none;">
                                        🧾 Ver comprobante
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <form method="POST" onsubmit="return confirm('¿Reactivar el servicio para esta vivienda?')">
                                        <input type="hidden" name="form_type" value="procesar_solicitud">
                                        <input type="hidden" name="solicitud_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="estado" value="Aprobado">
                                        <button type="submit" class="badge" style="background: #111827; color: white; border:none; cursor:pointer;">Aprobar</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('¿Rechazar renovación?')">
                                        <input type="hidden" name="form_type" value="procesar_solicitud">
                                        <input type="hidden" name="solicitud_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="estado" value="Rechazado">
                                        <button type="submit" class="badge" style="background: #F3F4F6; color: #374151; border:none; cursor:pointer;">Rechazar</button>
                                    </form>
                                </div>
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
