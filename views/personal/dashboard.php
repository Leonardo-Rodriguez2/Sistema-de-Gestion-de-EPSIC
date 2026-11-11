<?php
// views/personal/dashboard.php
$user = check_dashboard_access([1, 3]);

// Obtener cargo del personal
$detallesStmt = $pdo->prepare("SELECT cargo, turno FROM detalles_personal_obrero WHERE usuario_id = ?");
$detallesStmt->execute([$user['id']]);
$detalles = $detallesStmt->fetch(PDO::FETCH_ASSOC);
$cargo = $detalles['cargo'] ?? 'Personal';

// Indicadores generales para que el personal tenga una lectura rápida del sistema.
$operativoStmt = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Activo') AS viviendas_activas,
        (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Suspendido') AS viviendas_suspendidas,
        (SELECT COUNT(*) FROM solicitudes_vivienda WHERE estado='Pendiente') AS solicitudes_pendientes,
        (SELECT COUNT(*) FROM cobros WHERE estado='Pendiente') AS cobros_pendientes
");
$operativo = $operativoStmt->fetch(PDO::FETCH_ASSOC);

$title = "Dashboard Personal - EcoCusco";
$header_title = "Panel Operativo";
$header_subtitle = "Bienvenido, " . htmlspecialchars($user['nombre']) . " [" . htmlspecialchars($cargo) . "]";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?php
    $chart_data = [
        ['label' => 'Viviendas activas', 'short_label' => 'Activas', 'value' => $operativo['viviendas_activas'], 'color' => '#2F806B'],
        ['label' => 'Viviendas suspendidas', 'short_label' => 'Suspendidas', 'value' => $operativo['viviendas_suspendidas'], 'color' => '#B34D4D'],
        ['label' => 'Solicitudes pendientes', 'short_label' => 'Solicitudes', 'value' => $operativo['solicitudes_pendientes'], 'color' => '#D98B35'],
        ['label' => 'Cobros pendientes', 'short_label' => 'Cobros', 'value' => $operativo['cobros_pendientes'], 'color' => '#176B87'],
    ];
    $chart_title = 'Situación operativa';
    $chart_note = 'Estos datos te ayudan a entender rápidamente las prioridades actuales del sistema.';
    include __DIR__ . '/../components/dashboard_charts.php';
    ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <!-- Card Info Personal -->
        <div class="card" style="background: white; padding: 20px; border-radius: 12px; border-left: 4px solid #4C6E91;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 30px;">👷</div>
                <div>
                    <h4 style="margin: 0; color: #111827;"><?= htmlspecialchars($cargo) ?></h4>
                    <p style="margin: 0; font-size: 13px; color: #6B7280;">Turno: <?= htmlspecialchars($detalles['turno'] ?? 'No asignado') ?></p>
                </div>
            </div>
        </div>

    </div>

    <div class="card" style="margin-top: 20px; padding: 24px;">
        <div class="section-heading" style="margin-top:0;">
            <div>
                <span class="eyebrow">Próximamente</span>
                <h3>Gestión Operativa</h3>
            </div>
            <span style="font-size:30px;">🛣️</span>
        </div>
        <p style="color:#657384;margin-bottom:0;">Tu cronograma de trabajo y rutas aparecerán aquí. Mientras tanto, puedes usar los indicadores para conocer las prioridades del día.</p>
    </div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
