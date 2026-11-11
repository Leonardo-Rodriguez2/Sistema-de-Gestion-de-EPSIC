<?php
// views/admin/dashboard.php
$user = check_dashboard_access([1]);

// Stats ampliadas
$statsStmt = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM viviendas) as total_viviendas,
        (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Activo') as activas,
        (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Suspendido') as suspendidas,
        (SELECT SUM(monto_total) FROM recaudaciones WHERE estado='Verificado') as total_ingresos,
        (SELECT COUNT(*) FROM recaudaciones WHERE estado='Pendiente') as reportes_pendientes,
        (SELECT COUNT(*) FROM barrios) as total_barrios,
        (SELECT COUNT(*) FROM calles) as total_calles,
        (SELECT COUNT(*) FROM lotes_barrio WHERE estado='Enviado') as lotes_pendientes,
        (SELECT COUNT(*) FROM solicitudes_vivienda WHERE estado='Pendiente') as solicitudes_pendientes
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$title = "Panel de Administración - EPSIC";
$header_title = "Dashboard Central";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?php
    $chart_data = [
        ['label' => 'Viviendas activas', 'value' => $stats['activas'], 'color' => '#2F806B'],
        ['label' => 'Suspendidas', 'value' => $stats['suspendidas'], 'color' => '#B34D4D'],
        ['label' => 'Lotes por aprobar', 'value' => $stats['lotes_pendientes'], 'color' => '#D98B35'],
        ['label' => 'Solicitudes pendientes', 'value' => $stats['solicitudes_pendientes'], 'color' => '#176B87'],
    ];
    $chart_title = 'Estado operativo del sistema';
    $chart_note = 'Las barras muestran el volumen de cada indicador; la proporción azul indica su peso dentro de este resumen.';
    include __DIR__ . '/../components/dashboard_charts.php';
    ?>

    <div class="grid">
        <div class="card" style="grid-column: span 2;">
            <h3>🚀 Accesos Rápidos</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="router.php?page=usuarios" class="btn-primary" style="text-decoration: none; background: #4B5563;">Gestionar Personal</a>
                <a href="router.php?page=barrios" class="btn-primary" style="text-decoration: none;">Ver Barrios</a>
                <a href="router.php?page=gestor_viviendas" class="btn-primary" style="text-decoration: none; background: #4C6E91;">Lista Viviendas</a>
                <a href="router.php?page=gestor_dashboard" class="btn-primary" style="text-decoration: none; background: #8A7A5A;">Aprobar Lotes</a>
                <a href="router.php?page=gestor_recibos" class="btn-primary" style="text-decoration: none; background: #3E5C76;">Ver Recibos</a>
            </div>
        </div>
    </div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
