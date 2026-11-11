<?php
// views/components/notificaciones_page.php
// Centro de notificaciones compartido por todos los roles.
global $pdo;

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

$user = check_dashboard_access([1, 2, 3, 5, 6]);
$page = 'notificaciones';

$notificaciones = [];
try {
    $notifModel = new \app\models\notificacionModel();
    $notificaciones = $notifModel->paraUsuario($user, $pdo);
} catch (\Throwable $e) {
    $notificaciones = [];
}

$orden_tipos = [
    'solicitud_baja', 'solicitud_renovacion', 'solicitud_alta',
    'solicitud_baja_rechazada', 'solicitud_renovacion_rechazada', 'solicitud_alta_rechazada',
    'solicitud_servicio', 'lote_barrio', 'lote_barrio_rechazado',
    'lote_calle', 'lote_calle_rechazado', 'exencion',
    'facturas_por_enviar', 'certificado', 'factura_recibida',
    'pago_por_verificar', 'pago_pendiente', 'cobro_rechazado', 'reporte_ciudadano',
    'solicitud_rechazada', 'solicitud_gestion', 'solicitud_gestion_rechazada',
];
$tiposDisponibles = [];
foreach ($notificaciones as $n) {
    $tipo = (string)($n['tipo'] ?? 'otros');
    $tiposDisponibles[$tipo] = true;
}
$tipoFiltro = trim((string)($_GET['tipo'] ?? ''));
if ($tipoFiltro !== '' && !isset($tiposDisponibles[$tipoFiltro])) {
    $tipoFiltro = '';
}
$totalDisponibles = count($notificaciones);
if ($tipoFiltro !== '') {
    $notificaciones = array_values(array_filter(
        $notificaciones,
        static function ($n) use ($tipoFiltro) {
            return (string)($n['tipo'] ?? 'otros') === $tipoFiltro;
        }
    ));
}
$total = count($notificaciones);

// Un grupo por tipo de notificación, con su propio icono.
$grupos = [];
foreach ($notificaciones as $n) {
    $tipo = $n['tipo'] ?? 'otros';
    if (!isset($grupos[$tipo])) {
        $grupos[$tipo] = [
            'label' => notif_estilo($tipo)['label'],
            'icono' => $n['icono'],
            'items' => [],
        ];
    }
    $grupos[$tipo]['items'][] = $n;
}

uksort($grupos, function ($a, $b) use ($orden_tipos) {
    $ia = array_search($a, $orden_tipos, true);
    $ib = array_search($b, $orden_tipos, true);
    return ($ia === false ? 999 : $ia) <=> ($ib === false ? 999 : $ib);
});

$title = 'Notificaciones - EPSIC';
$header_title = 'Centro de Notificaciones';
$header_subtitle = $total > 0
    ? 'Tienes ' . $total . ' pendiente' . ($total === 1 ? '' : 's') . ' por atender.'
    : 'No tienes pendientes por atender.';

ob_start();
?>

<style>
.notif-page-head {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    flex-wrap:wrap; margin-bottom:18px;
}
.notif-page-count {
    background:#F2EEE7; color:#6E5F45; padding:6px 14px;
    border-radius:20px; font-size:12px; font-weight:700;
}
.notif-page-group { margin-bottom:22px; }
.notif-page-group h3 {
    margin:0 0 10px; font-size:13px; color:#6B7280;
    text-transform:uppercase; letter-spacing:.5px; font-weight:700;
    display:flex; align-items:center; gap:8px;
}
.notif-page-group-icon {
    width:24px; height:24px; border-radius:6px; display:inline-flex;
    align-items:center; justify-content:center; font-size:14px; line-height:1;
    flex-shrink:0;
}
.notif-page-list {
    background:#fff; border:1px solid #E5E7EB; border-radius:8px;
    overflow:hidden;
}
.notif-page-item {
    display:flex; align-items:flex-start; gap:14px;
    padding:14px 18px; text-decoration:none; color:#374151;
    border-bottom:1px solid #F3F4F6; transition:.15s;
}
.notif-page-item:last-child { border-bottom:none; }
.notif-page-item:hover { background:#F9FAFB; }
.notif-page-item .notif-icon-box {
    width:40px; height:40px; border-radius:9px; display:flex;
    align-items:center; justify-content:center; font-size:20px;
    flex-shrink:0; line-height:1;
}
.notif-page-item .notif-body { display:flex; flex-direction:column; gap:3px; flex:1; min-width:0; }
.notif-page-item .notif-title { font-size:14px; font-weight:700; color:#111827; line-height:1.25; }
.notif-page-item .notif-detail { font-size:12px; color:#6B7280; line-height:1.4; }
.notif-page-item .notif-meta { font-size:10px; color:#9CA3AF; margin-top:2px; }
.notif-page-item .notif-cat { font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
.notif-page-arrow { color:#D1D5DB; font-size:18px; align-self:center; flex-shrink:0; }
.notif-page-empty {
    background:#fff; border:1px solid #E5E7EB; border-radius:8px;
    padding:60px 20px; text-align:center; color:#9CA3AF;
}
.notif-page-empty .big { font-size:48px; margin-bottom:12px; }
.notif-page-empty h3 { margin:0 0 6px; color:#374151; }
.notif-page-empty p { margin:0; font-size:13px; }
.notif-page-filter {
    display:flex; align-items:end; gap:10px; flex-wrap:wrap;
    background:#fff; border:1px solid #E5E7EB; padding:14px 16px;
    margin-bottom:22px;
}
.notif-page-filter label {
    display:flex; flex-direction:column; gap:6px; color:#344454;
    font-size:11px; font-weight:700; min-width:240px;
}
.notif-page-filter select {
    min-height:38px; border:1px solid #D9E1E7; background:#fff;
    color:#344454; padding:8px 10px; font:inherit;
}
</style>

<div class="notif-page-head">
    <p style="margin:0; font-size:13px; color:#6B7280;">
        Cada aviso corresponde a un pendiente real. Cuando lo resuelvas, desaparecerá automáticamente.
    </p>
    <?php if($total > 0): ?>
        <span class="notif-page-count"><?= $total ?> pendiente<?= $total === 1 ? '' : 's' ?></span>
    <?php endif; ?>
</div>

<form method="GET" action="router.php" class="notif-page-filter no-print">
    <input type="hidden" name="page" value="notificaciones">
    <label>
        Tipo de notificación
        <select name="tipo">
            <option value="">Todos los tipos (<?= $totalDisponibles ?>)</option>
            <?php
            $tiposOrdenados = array_values(array_unique(array_merge(
                array_keys($tiposDisponibles),
                $orden_tipos
            )));
            usort($tiposOrdenados, static function ($a, $b) use ($orden_tipos) {
                $ia = array_search($a, $orden_tipos, true);
                $ib = array_search($b, $orden_tipos, true);
                return ($ia === false ? 999 : $ia) <=> ($ib === false ? 999 : $ib);
            });
            foreach ($tiposOrdenados as $tipo):
                if (!isset($tiposDisponibles[$tipo])) continue;
            ?>
                <option value="<?= htmlspecialchars($tipo) ?>" <?= $tipoFiltro === $tipo ? 'selected' : '' ?>>
                    <?= htmlspecialchars(notif_estilo($tipo)['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn-primary">Filtrar</button>
    <?php if ($tipoFiltro !== ''): ?>
        <a href="router.php?page=notificaciones" class="btn-edit">Limpiar</a>
    <?php endif; ?>
</form>

<?php if(empty($notificaciones)): ?>
    <div class="notif-page-empty">
        <div class="big">✅</div>
        <h3>Todo al día</h3>
        <p>No tienes notificaciones pendientes en este momento.</p>
    </div>
<?php else: ?>
    <?php foreach($grupos as $tipo => $grupo):
        $est = notif_estilo($tipo);
    ?>
        <div class="notif-page-group" id="notif-tipo-<?= htmlspecialchars($tipo) ?>">
            <h3>
                <span class="notif-page-group-icon" style="background:<?= $est['bg'] ?>; color:<?= $est['color'] ?>;"><?= $grupo['icono'] ?></span>
                <?= htmlspecialchars($grupo['label']) ?> (<?= count($grupo['items']) ?>)
            </h3>
            <div class="notif-page-list">
                <?php foreach($grupo['items'] as $n): ?>
                    <a href="<?= htmlspecialchars($n['link']) ?>" class="notif-page-item">
                        <span class="notif-icon-box" style="background:<?= $est['bg'] ?>; color:<?= $est['color'] ?>;"><?= $n['icono'] ?></span>
                        <span class="notif-body">
                            <span class="notif-title"><?= htmlspecialchars($n['titulo']) ?></span>
                            <?php if(!empty($n['detalle'])): ?>
                                <span class="notif-detail"><?= htmlspecialchars($n['detalle']) ?></span>
                            <?php endif; ?>
                            <span class="notif-meta">
                                <span class="notif-cat" style="color:<?= $est['color'] ?>;"><?= htmlspecialchars($est['label']) ?></span>
                                <?php if(!empty($n['fecha'])): ?> · <?= htmlspecialchars(notif_tiempo_relativo($n['fecha'])) ?><?php endif; ?>
                            </span>
                        </span>
                        <span class="notif-page-arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
