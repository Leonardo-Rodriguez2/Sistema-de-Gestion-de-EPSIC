<?php
// views/calle/dashboard.php
$user = check_dashboard_access([6]);

// Obtener datos de la calle asignada
$calleStmt = $pdo->prepare("SELECT c.id, c.nombre, c.barrio_id, b.nombre as barrio_nombre 
                           FROM detalles_encargado_calle d 
                           JOIN calles c ON d.calle_id = c.id
                           JOIN barrios b ON c.barrio_id = b.id
                           WHERE d.usuario_id = ?");
$calleStmt->execute([$user['id']]);
$calle_info = $calleStmt->fetch(PDO::FETCH_ASSOC);

if (!$calle_info) {
    die("No tienes una calle asignada. Contacta al administrador.");
}

// Obtener estadísticas rápidas
$vCountStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE calle_id = ?");
$vCountStmt->execute([$calle_info['id']]);
$total_viviendas = $vCountStmt->fetchColumn();

$mes = date('n'); $anio = date('Y');
$pCountStmt = $pdo->prepare("SELECT COUNT(*) FROM cobros c JOIN viviendas v ON c.vivienda_id = v.id WHERE v.calle_id = ? AND c.mes = ? AND c.anio = ? AND c.estado = 'Pendiente'");
$pCountStmt->execute([$calle_info['id'], $mes, $anio]);
$pendientes = $pCountStmt->fetchColumn();

$pagadasStmt = $pdo->prepare("SELECT COUNT(*) FROM cobros c JOIN viviendas v ON c.vivienda_id = v.id WHERE v.calle_id = ? AND c.mes = ? AND c.anio = ? AND c.estado = 'Pagado'");
$pagadasStmt->execute([$calle_info['id'], $mes, $anio]);
$pagadas = $pagadasStmt->fetchColumn();

$bCountStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_vivienda WHERE calle_id = ? AND tipo = 'Baja' AND estado = 'Pendiente'");
$bCountStmt->execute([$calle_info['id']]);
$ordenes_baja = $bCountStmt->fetchColumn();

// Lote status del periodo
$loteCalleStmt = $pdo->prepare("SELECT estado FROM lotes_calle WHERE calle_id=? AND mes=? AND anio=? ORDER BY id DESC LIMIT 1");
$loteCalleStmt->execute([$calle_info['id'], $mes, $anio]);
$lote_estado = $loteCalleStmt->fetchColumn() ?: 'Sin lote';

$title = "Dashboard - EcoCusco";
$header_title = "Panel de Calle: " . htmlspecialchars($calle_info['nombre']);
$header_subtitle = "Barrio " . htmlspecialchars($calle_info['barrio_nombre']) . " — Periodo actual: " . date('F Y');

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?php
    $chart_data = [
        ['label' => 'Pagos registrados', 'value' => $pagadas, 'color' => '#2F806B'],
        ['label' => 'Pagos pendientes', 'value' => $pendientes, 'color' => '#D98B35'],
        ['label' => 'Órdenes de baja', 'value' => $ordenes_baja, 'color' => '#B34D4D'],
    ];
    $chart_title = 'Cobranza del periodo actual';
    $chart_note = 'Pagadas y pendientes se comparan contra la misma calle y el periodo actual.';
    include __DIR__ . '/../components/dashboard_charts.php';
    ?>

    <h3 style="margin: 30px 0 15px; color: #374151;">Gestión de la Calle</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        
        <a href="router.php?page=viviendas" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 30px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#111827'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 40px; margin-bottom: 15px;">🏘️</div>
            <div style="font-weight: 700; color: #111827;">Ver Viviendas</div>
            <div style="font-size: 12px; color: #6B7280; text-align: center; margin-top: 5px;">Revisa el listado de casas asignadas</div>
        </a>

        <a href="router.php?page=reportar_pago" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 30px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#4C6E91'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 40px; margin-bottom: 15px;">💰</div>
            <div style="font-weight: 700; color: #111827;">Marcar Pagos</div>
            <div style="font-size: 12px; color: #6B7280; text-align: center; margin-top: 5px;">Registra quiénes pagaron este mes</div>
        </a>

        <a href="router.php?page=registrar_vivienda" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 30px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#4C6E91'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 40px; margin-bottom: 15px;">➕</div>
            <div style="font-weight: 700; color: #111827;">Solicitar Alta</div>
            <div style="font-size: 12px; color: #6B7280; text-align: center; margin-top: 5px;">Registrar nueva vivienda</div>
        </a>

        <a href="router.php?page=solicitar_baja" class="card" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 30px; transition: 0.3s; border: 2px solid transparent;" onmouseover="this.style.borderColor='#9A6A6A'" onmouseout="this.style.borderColor='transparent'">
            <div style="font-size: 40px; margin-bottom: 15px;">📄</div>
            <div style="font-weight: 700; color: #111827;">Solicitar Baja</div>
            <div style="font-size: 12px; color: #6B7280; text-align: center; margin-top: 5px;">Dar de baja una vivienda</div>
        </a>

    </div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
