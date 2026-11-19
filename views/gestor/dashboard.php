<?php
// views/gestor/dashboard.php — Panel de Aprobación Final de Lotes de Barrio
global $pdo;
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
use app\models\mainModel;
if (empty($pdo)) $pdo = (new mainModel())->conectar();

$user = check_dashboard_access([1, 2]);
$page = 'dashboard';

// Stats generales
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM lotes_barrio WHERE estado='Enviado') as lotes_pendientes,
    (SELECT COUNT(*) FROM lotes_barrio WHERE estado='Aprobado') as lotes_aprobados,
    (SELECT SUM(monto_total_recolectado) FROM lotes_barrio WHERE estado='Aprobado') as total_aprobado,
    (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Activo') as total_activas,
    (SELECT COUNT(*) FROM viviendas WHERE exento_cobro=1) as total_exentas,
    (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Suspendido') as total_suspendidas,
    (SELECT COALESCE(SUM(monto),0) FROM cobros WHERE estado NOT IN ('Pagado','Anulado')) as deuda_total,
    (SELECT COUNT(*) FROM cobros WHERE estado NOT IN ('Pagado','Anulado')) as cobros_pendientes,
    (SELECT COUNT(*) FROM recibos_finiquito) as total_recibos
")->fetch(PDO::FETCH_ASSOC);

// Lotes de Barrio pendientes de aprobación
$lotesEnviados = $pdo->query(
    "SELECT lb.*, b.nombre as barrio_nombre, u.nombre as encargado_nombre, u.apellido as encargado_apellido
     FROM lotes_barrio lb
     JOIN barrios b ON lb.barrio_id = b.id
     JOIN usuarios u ON lb.encargado_barrio_id = u.id
     WHERE lb.estado='Enviado'
     ORDER BY lb.fecha_envio ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Historial reciente de lotes aprobados
$historial = $pdo->query(
    "SELECT lb.*, b.nombre as barrio_nombre, u.nombre as gestor_nombre,
            rf.numero_recibo, rf.fecha_emision as fecha_recibo
     FROM lotes_barrio lb
     JOIN barrios b ON lb.barrio_id = b.id
     LEFT JOIN usuarios u ON lb.gestor_id = u.id
     LEFT JOIN recibos_finiquito rf ON rf.lote_barrio_id = lb.id
     WHERE lb.estado='Aprobado'
     ORDER BY lb.fecha_aprobacion DESC
     LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$meses_nombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$title          = "Dashboard Gestor - EcoReciclaje";
$header_title   = "Panel de Gestión de Pagos";
$header_subtitle = "Revisa y aprueba los lotes de barrio para cerrar el ciclo de facturación.";

ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<?php
$chart_data = [
    ['label' => 'Lotes aprobados', 'value' => $stats['lotes_aprobados'], 'color' => '#2F806B'],
    ['label' => 'Lotes pendientes', 'value' => $stats['lotes_pendientes'], 'color' => '#D98B35'],
    ['label' => 'Cobros pendientes', 'value' => $stats['cobros_pendientes'], 'color' => '#B34D4D'],
    ['label' => 'Casas exoneradas', 'value' => $stats['total_exentas'], 'color' => '#176B87'],
];
$chart_title = 'Resumen de recaudación';
$chart_note = 'Prioriza los lotes pendientes y los cobros pendientes para mantener actualizado el cierre mensual.';
include __DIR__ . '/../components/dashboard_charts.php';
?>

<!-- ACCESO RÁPIDO A FACTURACIÓN -->
<div class="card" style="margin-top:20px; border-left:4px solid #4C6E91; background:linear-gradient(135deg, #EEF2F6 0%, #FFFFFF 100%);">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        <div>
            <h3 style="margin:0; display:flex; align-items:center; gap:10px;">📄 Facturación por Barrio</h3>
        </div>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:12px;">
        <a href="router.php?page=gestor_certificado_calle&vista=pendientes" class="btn-primary" style="text-decoration:none; justify-content:space-between; padding:16px 18px; background:#8A7A5A;">
            <span>⏳ Lotes Pendientes de Facturación</span><strong>→</strong>
        </a>
        <a href="router.php?page=gestor_certificado_calle&vista=emitidas" class="btn-primary" style="text-decoration:none; justify-content:space-between; padding:16px 18px; background:#4C6E91;">
            <span>📄 Facturas Emitidas</span><strong>→</strong>
        </a>
    </div>
</div>

<!-- LOTES PENDIENTES - LINK A PAGINA DEDICADA -->
<?php if($stats['lotes_pendientes'] > 0): ?>
<div class="card" style="margin-top:20px; border-left:4px solid #8A7A5A; background:linear-gradient(135deg, #F7F4EE 0%, #FFFFFF 100%);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 style="margin:0; display:flex; align-items:center; gap:10px;">
                ⏳ Lotes Pendientes de Revisión
                <span style="background:#F2EEE7; color:#6E5F45; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700;">
                    <?= $stats['lotes_pendientes'] ?>
                </span>
            </h3>
            <p style="color:#6B7280; font-size:12px; margin:6px 0 0;">Hay lotes de barrio esperando tu aprobación</p>
        </div>
        <a href="router.php?page=revisar_lotes" style="background:#8A7A5A; color:white; padding:10px 24px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:700; display:flex; align-items:center; gap:6px; white-space:nowrap;">
            📋 Revisar Lotes →
        </a>
    </div>
</div>
<?php endif; ?>

<!-- HISTORIAL DE LOTES APROBADOS -->
<?php if(!empty($historial)): ?>
<div class="card" style="margin-top:20px;">
    <h3 style="margin-top:0;">📄 Historial de Lotes Aprobados</h3>
    <div class="table-wrap">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="text-align:left; border-bottom:2px solid #F3F4F6;">
                    <th style="padding:10px;">Barrio</th>
                    <th style="padding:10px;">Periodo</th>
                    <th style="padding:10px;">Recolectado</th>
                    <th style="padding:10px;">Aprobado por</th>
                    <th style="padding:10px;">Recibo</th>
                    <th style="padding:10px;">Certificado</th>
                    <th style="padding:10px;">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($historial as $h): ?>
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:10px; font-weight:700;"><?= htmlspecialchars($h['barrio_nombre']) ?></td>
                    <td style="padding:10px;"><?= $meses_nombres[$h['periodo_mes']] ?> <?= $h['periodo_anio'] ?></td>
                    <td style="padding:10px; font-weight:800; color:#3E5C76;"><?= monto($h['monto_total_recolectado']) ?></td>
                    <td style="padding:10px; color:#6B7280;"><?= htmlspecialchars($h['gestor_nombre'] ?? '—') ?></td>
                    <td style="padding:10px;">
                        <?php if($h['numero_recibo']): ?>
                        <a href="router.php?page=ver_recibo_finiquito&lote_id=<?= $h['id'] ?>" target="_blank"
                           class="badge" style="background:#E9EFF4; color:#33485C; text-decoration:none; padding:5px 10px;">
                            📄 <?= htmlspecialchars($h['numero_recibo']) ?>
                        </a>
                        <?php else: ?>
                        <span style="color:#9CA3AF;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px;">
                        <a href="router.php?page=gestor_certificado_calle" class="badge" style="background:#EEF2F6; color:#33485C; text-decoration:none; padding:5px 10px;">
                            📤 Gestionar
                        </a>
                    </td>
                    <td style="padding:10px; color:#6B7280; font-size:12px;">
                        <?= $h['fecha_aprobacion'] ? date('d/m/Y', strtotime($h['fecha_aprobacion'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function openComprobanteModal(el) {
    var url = el.getAttribute('data-url');
    var cont = document.getElementById('comprobante-content');
    if (url.match(/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i)) {
        cont.innerHTML = '<img src="' + encodeURI(url) + '" style="max-width:100%; max-height:70vh; border-radius:8px;">';
    } else {
        cont.innerHTML = '<a href="' + encodeURI(url) + '" target="_blank" style="color:#3E5C76; font-size:14px;">📄 Abrir PDF</a>';
    }
    document.getElementById('modal-comprobante').style.display = 'flex';
}
function closeComprobanteModal() {
    document.getElementById('modal-comprobante').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
