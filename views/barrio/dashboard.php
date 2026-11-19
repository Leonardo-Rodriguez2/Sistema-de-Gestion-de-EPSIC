<?php
// views/barrio/dashboard.php
global $pdo;

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use app\models\mainModel;

if (empty($pdo)) {
    $pdo = (new mainModel())->conectar();
}

$user = check_dashboard_access([5]);

// Obtener datos del barrio asignado
$barrioStmt = $pdo->prepare("SELECT b.* FROM detalles_encargado_barrio d JOIN barrios b ON d.barrio_id = b.id WHERE d.usuario_id = ?");
$barrioStmt->execute([$user['id']]);
$barrio_info = $barrioStmt->fetch(PDO::FETCH_ASSOC);

if (!$barrio_info) {
    die("No tienes un barrio asignado. Contacta al administrador.");
}

// Stats rápidas
$cCountStmt = $pdo->prepare("SELECT COUNT(*) FROM calles WHERE barrio_id = ?");
$cCountStmt->execute([$barrio_info['id']]);
$total_calles = $cCountStmt->fetchColumn();

$sCountStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_vivienda s JOIN calles c ON s.calle_id = c.id WHERE c.barrio_id = ? AND s.estado = 'Pendiente'");
$sCountStmt->execute([$barrio_info['id']]);
$total_solicitudes = $sCountStmt->fetchColumn();

$vCountStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE barrio_id = ? AND estado_servicio='Activo'");
$vCountStmt->execute([$barrio_info['id']]);
$total_activas = $vCountStmt->fetchColumn();

$vSusStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE barrio_id = ? AND estado_servicio='Suspendido'");
$vSusStmt->execute([$barrio_info['id']]);
$total_suspendidas = $vSusStmt->fetchColumn();

$lotesPendStmt = $pdo->prepare("SELECT COUNT(*) FROM lotes_calle lc JOIN calles c ON lc.calle_id=c.id WHERE c.barrio_id=? AND lc.estado='Enviado'");
$lotesPendStmt->execute([$barrio_info['id']]);
$lotes_calle_pend = $lotesPendStmt->fetchColumn();

$lbStatus = $pdo->prepare("SELECT estado FROM lotes_barrio WHERE barrio_id=? AND mes=? AND anio=? ORDER BY id DESC LIMIT 1");
$mesActual = date('n');
$anioActual = date('Y');
$lbStatus->execute([$barrio_info['id'], $mesActual, $anioActual]);
$lote_barrio_estado = $lbStatus->fetchColumn() ?: '—';

$title = "Dashboard Barrio - EcoReciclaje";
$header_title = "Panel de Barrio: " . htmlspecialchars($barrio_info['nombre']);
$header_subtitle = "Gestión centralizada de calles, pagos y solicitudes.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?php
    $chart_data = [
        ['label' => 'Casas activas', 'value' => $total_activas, 'color' => '#2F806B'],
        ['label' => 'Suspendidas', 'value' => $total_suspendidas, 'color' => '#B34D4D'],
        ['label' => 'Solicitudes pendientes', 'value' => $total_solicitudes, 'color' => '#D98B35'],
        ['label' => 'Lotes de calle pendientes', 'value' => $lotes_calle_pend, 'color' => '#176B87'],
    ];
    $chart_title = 'Resumen del barrio';
    $chart_note = 'Usa este resumen para saber si debes priorizar pagos, solicitudes o la revisión de lotes.';
    include __DIR__ . '/../components/dashboard_charts.php';
    ?>

    <!-- Resumen del periodo actual -->
    <?php
    $loteBarrioInfo = $pdo->prepare("SELECT lb.*, rf.numero_recibo FROM lotes_barrio lb LEFT JOIN recibos_finiquito rf ON rf.lote_barrio_id=lb.id WHERE lb.barrio_id=? AND lb.mes=? AND lb.anio=? ORDER BY lb.id DESC LIMIT 1");
    $loteBarrioInfo->execute([$barrio_info['id'], $mesActual, $anioActual]);
    $lbi = $loteBarrioInfo->fetch(PDO::FETCH_ASSOC);
    if ($lbi): ?>
    <div class="card" style="margin-bottom:20px; border-left:4px solid <?= $lbi['estado']==='Aprobado'?'#4C6E91':($lbi['estado']==='Enviado'?'#8A7A5A':'#6B7280') ?>;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div>
                <strong style="font-size:14px;">📊 Periodo Actual: <?= date('F Y', mktime(0,0,0,$mesActual,1,$anioActual)) ?></strong>
                <div style="font-size:12px; color:#6B7280; margin-top:4px;">
                    Estado lote barrio: <strong style="color:<?= $lbi['estado']==='Aprobado'?'#3E5C76':($lbi['estado']==='Enviado'?'#7A6A4F':'#6B7280') ?>"><?= $lbi['estado'] ?></strong>
                    <?php if($lbi['numero_recibo']): ?> • Recibo: <?= htmlspecialchars($lbi['numero_recibo']) ?><?php endif; ?>
                </div>
            </div>
            <a href="router.php?page=reportar_pago" class="btn-primary" style="background:#8A7A5A;">
                Ir a Verificación de Pagos →
            </a>
        </div>
    </div>
    <?php endif; ?>

    <h3 style="margin: 30px 0 15px; color: #374151;">Módulos de Gestión</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
        
        <a href="router.php?page=viviendas" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 25px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#111827'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 35px; margin-bottom: 15px;">🏘️</div>
            <div style="font-weight: 700; color: #111827;">Control de Viviendas</div>
            <div style="font-size: 11px; color: #6B7280; text-align: center; margin-top: 5px;">Revisa el listado general del barrio</div>
        </a>

        <a href="router.php?page=reportar_pago" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 25px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#4C6E91'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 35px; margin-bottom: 15px;">💰</div>
            <div style="font-weight: 700; color: #111827;">Verificación de Pagos</div>
            <div style="font-size: 11px; color: #6B7280; text-align: center; margin-top: 5px;">Marca casas que pagaron y revisa comprobantes</div>
        </a>

        <a href="router.php?page=solicitudes" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 25px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#8A7A5A'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 35px; margin-bottom: 15px;">📩</div>
            <div style="font-weight: 700; color: #111827;">Solicitudes</div>
            <div style="font-size: 11px; color: #6B7280; text-align: center; margin-top: 5px;">Aprueba altas y renovaciones pendientes</div>
        </a>

        <a href="router.php?page=quitar_servicio" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 25px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#9A6A6A'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 35px; margin-bottom: 15px;">🔻</div>
            <div style="font-weight: 700; color: #111827;">Quitar Servicio</div>
            <div style="font-size: 11px; color: #6B7280; text-align: center; margin-top: 5px;">Emitir órdenes de suspensión</div>
        </a>

        <a href="router.php?page=calles" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 25px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#4C6E91'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 35px; margin-bottom: 15px;">🛣️</div>
            <div style="font-weight: 700; color: #111827;">Mis Calles</div>
            <div style="font-size: 11px; color: #6B7280; text-align: center; margin-top: 5px;">Gestión de infraestructura</div>
        </a>

    </div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
