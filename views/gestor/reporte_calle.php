<?php
// views/gestor/reporte_calle.php — Reporte imprimible de una calle específica
global $pdo;

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use app\models\mainModel;

if (empty($pdo)) {
    $pdo = (new mainModel())->conectar();
}

$user = check_dashboard_access([1, 2, 5, 6]);
$rol = (int)($user['rol_id'] ?? 0);
$sid = $_GET['sid'] ?? $_SESSION['active_sid'] ?? '';
$page = 'reporte_calle';

$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// Alcance según el rol: barrio y calle solo pueden ver lo suyo.
$barrioFijo = 0;
$calleFija = 0;

if ($rol === 5) {
    $bStmt = $pdo->prepare("SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1");
    $bStmt->execute([$user['id']]);
    $barrioFijo = (int)$bStmt->fetchColumn();
} elseif ($rol === 6) {
    $cStmt = $pdo->prepare("SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ? LIMIT 1");
    $cStmt->execute([$user['id']]);
    $calleFija = (int)$cStmt->fetchColumn();
    if ($calleFija > 0) {
        $bStmt = $pdo->prepare("SELECT barrio_id FROM calles WHERE id = ?");
        $bStmt->execute([$calleFija]);
        $barrioFijo = (int)$bStmt->fetchColumn();
    }
}

// Filtros
$f_barrio = $rol === 5 || $rol === 6 ? $barrioFijo : (int)($_GET['barrio_id'] ?? 0);
$f_calle = $rol === 6 ? $calleFija : (int)($_GET['calle_id'] ?? 0);
$f_mes = max(1, min(12, (int)($_GET['mes'] ?? date('n'))));
$f_anio = (int)($_GET['anio'] ?? date('Y'));
if ($f_anio < 2020 || $f_anio > 2100) $f_anio = (int)date('Y');
$f_estado = $_GET['estado'] ?? 'todos';
if (!in_array($f_estado, ['todos', 'pagado', 'pendiente', 'sin_cobro', 'exenta'], true)) {
    $f_estado = 'todos';
}

// Catálogos
if ($rol === 5 || $rol === 6) {
    $bStmt = $pdo->prepare("SELECT id, nombre FROM barrios WHERE id = ?");
    $bStmt->execute([$barrioFijo]);
    $barrios = $bStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}

if ($rol === 6) {
    $cStmt = $pdo->prepare("SELECT id, nombre, barrio_id FROM calles WHERE id = ?");
    $cStmt->execute([$calleFija]);
    $calles = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($rol === 5) {
    $cStmt = $pdo->prepare("SELECT id, nombre, barrio_id FROM calles WHERE barrio_id = ? ORDER BY nombre");
    $cStmt->execute([$barrioFijo]);
    $calles = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $calles = $pdo->query("SELECT id, nombre, barrio_id FROM calles ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}

// Datos de la calle seleccionada
$calle = null;
if ($f_calle > 0) {
    $cStmt = $pdo->prepare(
        "SELECT c.id, c.nombre, c.barrio_id, b.nombre AS barrio_nombre
         FROM calles c
         JOIN barrios b ON b.id = c.barrio_id
         WHERE c.id = ?"
    );
    $cStmt->execute([$f_calle]);
    $calle = $cStmt->fetch(PDO::FETCH_ASSOC);

    // Validar que la calle pertenezca al alcance del usuario.
    if ($calle && (($rol === 5 && (int)$calle['barrio_id'] !== $barrioFijo) || ($rol === 6 && (int)$calle['id'] !== $calleFija))) {
        $calle = null;
    }
}

$viviendas = [];
$resumen = ['casas' => 0, 'pagadas' => 0, 'pendientes' => 0, 'sin_cobro' => 0, 'exentas' => 0, 'recaudado' => 0, 'deuda' => 0];
$encargadoCalle = null;
$encargadoBarrio = null;

if ($calle) {
    $stmt = $pdo->prepare(
        "SELECT v.id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador,
                v.direccion, v.estado_servicio, v.exento_cobro,
                COALESCE(cp.pagado, 0) AS pagado,
                COALESCE(cp.monto, 0) AS monto_periodo,
                CASE WHEN cp.vivienda_id IS NULL THEN 0 ELSE 1 END AS tiene_cobro,
                cp.fecha_pago,
                (SELECT COALESCE(SUM(c2.monto), 0)
                   FROM cobros c2
                  WHERE c2.vivienda_id = v.id AND c2.estado NOT IN ('Pagado','Anulado')) AS deuda
         FROM viviendas v
         JOIN barrios b ON b.id = v.barrio_id
         JOIN calles c ON c.id = v.calle_id
         LEFT JOIN (
             SELECT vivienda_id,
                    MAX(CASE WHEN estado = 'Pagado' THEN 1 ELSE 0 END) AS pagado,
                    SUM(CASE WHEN estado = 'Pagado' THEN monto ELSE 0 END) AS monto,
                    MAX(CASE WHEN estado = 'Pagado' THEN fecha_emision END) AS fecha_pago
             FROM cobros
             WHERE mes = ? AND anio = ? AND estado <> 'Anulado'
             GROUP BY vivienda_id
         ) cp ON cp.vivienda_id = v.id
         WHERE v.calle_id = ?
         ORDER BY CAST(v.numero_casa AS UNSIGNED), v.numero_casa, v.propietario"
    );
    $stmt->execute([$f_mes, $f_anio, $f_calle]);
    $viviendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($viviendas as $v) {
        $resumen['casas']++;
        $resumen['deuda'] += (float)$v['deuda'];
        if (!empty($v['exento_cobro'])) {
            $resumen['exentas']++;
        } elseif (!empty($v['pagado'])) {
            $resumen['pagadas']++;
            $resumen['recaudado'] += (float)$v['monto_periodo'];
        } elseif (!empty($v['tiene_cobro'])) {
            $resumen['pendientes']++;
        } else {
            $resumen['sin_cobro']++;
        }
    }

    $viviendasVisibles = array_values(array_filter($viviendas, function ($v) use ($f_estado) {
        if ($f_estado === 'todos') return true;
        if ($f_estado === 'exenta') return !empty($v['exento_cobro']);
        if ($f_estado === 'pagado') return empty($v['exento_cobro']) && !empty($v['pagado']);
        if ($f_estado === 'pendiente') return empty($v['exento_cobro']) && empty($v['pagado']) && !empty($v['tiene_cobro']);
        if ($f_estado === 'sin_cobro') return empty($v['exento_cobro']) && empty($v['tiene_cobro']);
        return true;
    }));

    $encCStmt = $pdo->prepare(
        "SELECT u.nombre, u.apellido, d.dni
         FROM detalles_encargado_calle d
         LEFT JOIN usuarios u ON u.id = d.usuario_id
         WHERE d.calle_id = ?
         LIMIT 1"
    );
    $encCStmt->execute([$f_calle]);
    $encargadoCalle = $encCStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $encBStmt = $pdo->prepare(
        "SELECT u.nombre, u.apellido, d.dni
         FROM detalles_encargado_barrio d
         LEFT JOIN usuarios u ON u.id = d.usuario_id
         WHERE d.barrio_id = ?
         LIMIT 1"
    );
    $encBStmt->execute([(int)$calle['barrio_id']]);
    $encargadoBarrio = $encBStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$title = 'Reporte por Calle - EPSIC';
$header_title = 'Reporte de Viviendas por Calle';
$header_subtitle = 'Selecciona un barrio y una calle para generar el reporte con los nombres de los propietarios.';

$estadoTexto = static function (array $v): string {
    if (!empty($v['exento_cobro'])) return 'Exenta';
    if (!empty($v['pagado'])) return 'Pagado';
    if (!empty($v['tiene_cobro'])) return 'Pendiente';
    return 'Sin cobro';
};

ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<div class="card no-print" style="margin-bottom:18px;">
    <form method="GET" action="router.php" class="unified-filter no-print">
        <input type="hidden" name="page" value="reporte_calle">
        <?php if ($sid !== ''): ?><input type="hidden" name="sid" value="<?= htmlspecialchars($sid) ?>"><?php endif; ?>

        <?php if ($rol !== 6): ?>
        <div style="min-width:200px; flex:1;">
            <label style="font-size:11px; font-weight:700; color:#6B7280; display:block; margin-bottom:4px;">BARRIO</label>
            <select name="barrio_id" id="rep-barrio" <?= ($rol === 5) ? 'disabled' : '' ?> style="width:100%; padding:8px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
                <?php if ($rol !== 5): ?><option value="0">Todos los barrios</option><?php endif; ?>
                <?php foreach ($barrios as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= (int)$f_barrio === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($rol === 5): ?><input type="hidden" name="barrio_id" value="<?= (int)$f_barrio ?>"><?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="min-width:230px; flex:1.2;">
            <label style="font-size:11px; font-weight:700; color:#6B7280; display:block; margin-bottom:4px;">CALLE</label>
            <select name="calle_id" id="rep-calle" <?= ($rol === 6) ? 'disabled' : '' ?> style="width:100%; padding:8px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
                <option value="0">Seleccione una calle</option>
                <?php foreach ($calles as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" data-barrio="<?= (int)$c['barrio_id'] ?>" <?= (int)$f_calle === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($rol === 6): ?><input type="hidden" name="calle_id" value="<?= (int)$f_calle ?>"><?php endif; ?>
        </div>

        <div>
            <label style="font-size:11px; font-weight:700; color:#6B7280; display:block; margin-bottom:4px;">MES</label>
            <select name="mes" style="padding:8px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $f_mes === $i ? 'selected' : '' ?>><?= $meses[$i] ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div>
            <label style="font-size:11px; font-weight:700; color:#6B7280; display:block; margin-bottom:4px;">AÑO</label>
            <input type="number" name="anio" value="<?= (int)$f_anio ?>" min="2020" max="2100" style="width:95px; padding:8px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
        </div>

        <div>
            <label style="font-size:11px; font-weight:700; color:#6B7280; display:block; margin-bottom:4px;">ESTADO</label>
            <select name="estado" style="padding:8px; border:1px solid #E5E7EB; border-radius:6px; font-size:13px;">
                <option value="todos" <?= $f_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="pagado" <?= $f_estado === 'pagado' ? 'selected' : '' ?>>Pagaron</option>
                <option value="pendiente" <?= $f_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                <option value="sin_cobro" <?= $f_estado === 'sin_cobro' ? 'selected' : '' ?>>Sin cobro</option>
                <option value="exenta" <?= $f_estado === 'exenta' ? 'selected' : '' ?>>Exentas</option>
            </select>
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn-primary" style="padding:8px 16px;">Consultar</button>
            <?php if ($calle): ?>
                <button type="button" class="btn-primary" style="padding:8px 16px; background:#4B5563;" onclick="window.print()">🖨️ Imprimir</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!$calle): ?>
    <div class="card" style="padding:40px; text-align:center; color:#6B7280;">
        <div style="font-size:42px; margin-bottom:10px;">🏘️</div>
        <?php if ($rol === 5 && $barrioFijo <= 0): ?>
            <strong style="font-size:16px; color:#374151;">No tienes un barrio asignado</strong>
            <div style="font-size:13px; margin-top:6px;">Contacta al administrador para que asigne tu barrio.</div>
        <?php elseif ($rol === 6 && $calleFija <= 0): ?>
            <strong style="font-size:16px; color:#374151;">No tienes una calle asignada</strong>
            <div style="font-size:13px; margin-top:6px;">Contacta al administrador para que asigne tu calle.</div>
        <?php else: ?>
            <strong style="font-size:16px; color:#374151;">Selecciona una calle</strong>
            <div style="font-size:13px; margin-top:6px;">Elige el barrio y la calle para generar el reporte con los nombres de los propietarios.</div>
        <?php endif; ?>
    </div>
<?php else: ?>

<div class="card report-print-area" style="padding:24px;">
    <div style="text-align:center; border-bottom:2px solid #33485C; padding-bottom:14px; margin-bottom:18px;">
        <div style="font-size:11px; letter-spacing:1px; color:#6B7280; text-transform:uppercase;">Sistema de Gestión de Reciclaje — EPSIC / EcoCusco</div>
        <h2 style="margin:6px 0 4px; font-size:22px; color:#111827;">REPORTE DE VIVIENDAS POR CALLE</h2>
        <div style="font-size:12px; color:#6B7280;">Emitido el <?= date('d/m/Y H:i') ?></div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-bottom:18px;">
        <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:10px 12px;">
            <div style="font-size:10px; color:#9CA3AF; font-weight:700; text-transform:uppercase;">Barrio</div>
            <div style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars($calle['barrio_nombre']) ?></div>
        </div>
        <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:10px 12px;">
            <div style="font-size:10px; color:#9CA3AF; font-weight:700; text-transform:uppercase;">Calle</div>
            <div style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars($calle['nombre']) ?></div>
        </div>
        <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:10px 12px;">
            <div style="font-size:10px; color:#9CA3AF; font-weight:700; text-transform:uppercase;">Periodo</div>
            <div style="font-size:14px; font-weight:700; color:#111827;"><?= $meses[$f_mes] ?> <?= $f_anio ?></div>
        </div>
        <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:10px 12px;">
            <div style="font-size:10px; color:#9CA3AF; font-weight:700; text-transform:uppercase;">Viviendas</div>
            <div style="font-size:14px; font-weight:700; color:#111827;"><?= $resumen['casas'] ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(6, 1fr); gap:8px; margin-bottom:18px;">
        <div style="background:#EEF2F6; border-radius:8px; padding:10px; text-align:center;">
            <div style="font-size:20px; font-weight:800; color:#33485C;"><?= $resumen['pagadas'] ?></div>
            <div style="font-size:10px; color:#6B7280; text-transform:uppercase;">Pagadas</div>
        </div>
        <div style="background:#F4EDED; border-radius:8px; padding:10px; text-align:center;">
            <div style="font-size:20px; font-weight:800; color:#7A4F4F;"><?= $resumen['pendientes'] ?></div>
            <div style="font-size:10px; color:#6B7280; text-transform:uppercase;">Pendientes</div>
        </div>
        <div style="background:#F3F4F6; border-radius:8px; padding:10px; text-align:center;">
            <div style="font-size:20px; font-weight:800; color:#6B7280;"><?= $resumen['sin_cobro'] ?></div>
            <div style="font-size:10px; color:#6B7280; text-transform:uppercase;">Sin cobro</div>
        </div>
        <div style="background:#E7EDF3; border-radius:8px; padding:10px; text-align:center;">
            <div style="font-size:20px; font-weight:800; color:#4A5568;"><?= $resumen['exentas'] ?></div>
            <div style="font-size:10px; color:#6B7280; text-transform:uppercase;">Exentas</div>
        </div>
        <div style="background:#E9EFF4; border-radius:8px; padding:10px; text-align:center;">
            <div style="font-size:18px; font-weight:800; color:#33485C;">Bs. <?= number_format($resumen['recaudado'], 2) ?></div>
            <div style="font-size:10px; color:#6B7280; text-transform:uppercase;">Recaudado</div>
        </div>
        <div style="background:#F4EDED; border-radius:8px; padding:10px; text-align:center;">
            <div style="font-size:18px; font-weight:800; color:#8A5A5A;">Bs. <?= number_format($resumen['deuda'], 2) ?></div>
            <div style="font-size:10px; color:#6B7280; text-transform:uppercase;">Deuda acumulada</div>
        </div>
    </div>

    <div class="table-wrap">
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#33485C; color:#fff; text-align:left;">
                    <th style="padding:9px 10px;">#</th>
                    <th style="padding:9px 10px;">Casa</th>
                    <th style="padding:9px 10px;">Propietario</th>
                    <th style="padding:9px 10px;">Cédula</th>
                    <th style="padding:9px 10px;">Dirección</th>
                    <th style="padding:9px 10px;">Servicio</th>
                    <th style="padding:9px 10px;">Pago del periodo</th>
                    <th style="padding:9px 10px; text-align:right;">Monto</th>
                    <th style="padding:9px 10px; text-align:right;">Deuda</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$viviendasVisibles): ?>
                    <tr><td colspan="9" style="padding:30px; text-align:center; color:#9CA3AF;">No hay viviendas para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php $i = 0; foreach ($viviendasVisibles as $v): $i++;
                        $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? '');
                        $estadoPago = $estadoTexto($v);
                    ?>
                    <tr style="border-bottom:1px solid #E5E7EB; <?= !empty($v['exento_cobro']) ? 'background:#F9FAFB;' : '' ?>">
                        <td style="padding:8px 10px; color:#9CA3AF;"><?= $i ?></td>
                        <td style="padding:8px 10px; font-weight:700;"><?= htmlspecialchars($v['numero_casa'] ?: '—') ?></td>
                        <td style="padding:8px 10px; font-weight:600; color:#111827;"><?= htmlspecialchars($v['propietario']) ?></td>
                        <td style="padding:8px 10px; color:#4B5563;"><?= $identificacion !== '' ? htmlspecialchars($identificacion) : '—' ?></td>
                        <td style="padding:8px 10px; color:#6B7280;"><?= htmlspecialchars($v['direccion']) ?></td>
                        <td style="padding:8px 10px;"><?= htmlspecialchars($v['estado_servicio']) ?></td>
                        <td style="padding:8px 10px;">
                            <?php if ($estadoPago === 'Pagado'): ?>
                                <span style="background:#E9EFF4; color:#33485C; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">✓ Pagado</span>
                            <?php elseif ($estadoPago === 'Pendiente'): ?>
                                <span style="background:#F4EDED; color:#7A4F4F; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">⏳ Pendiente</span>
                            <?php elseif ($estadoPago === 'Exenta'): ?>
                                <span style="background:#E7EDF3; color:#4A5568; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">🛡️ Exenta</span>
                            <?php else: ?>
                                <span style="background:#F3F4F6; color:#6B7280; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">Sin cobro</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; text-align:right; font-weight:600;"><?= !empty($v['pagado']) ? 'Bs. ' . number_format((float)$v['monto_periodo'], 2) : '—' ?></td>
                        <td style="padding:8px 10px; text-align:right; font-weight:700; color:<?= (float)$v['deuda'] > 0 ? '#8A5A5A' : '#9CA3AF' ?>;">
                            <?= (float)$v['deuda'] > 0 ? 'Bs. ' . number_format((float)$v['deuda'], 2) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #33485C; background:#EEF2F6;">
                    <td colspan="7" style="padding:10px; text-align:right; font-weight:800;">TOTALES DEL REPORTE</td>
                    <td style="padding:10px; text-align:right; font-weight:800; color:#33485C;">Bs. <?= number_format($resumen['recaudado'], 2) ?></td>
                    <td style="padding:10px; text-align:right; font-weight:800; color:#8A5A5A;">Bs. <?= number_format($resumen['deuda'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:46px;">
        <div style="text-align:center; padding-top:34px; border-top:1px solid #9CA3AF;">
            <div style="font-size:11px; color:#6B7280;">Encargado de Calle</div>
            <div style="font-size:13px; font-weight:700; margin-top:4px;">
                <?= htmlspecialchars(trim(($encargadoCalle['nombre'] ?? '') . ' ' . ($encargadoCalle['apellido'] ?? ''))) ?: '—' ?>
            </div>
            <div style="font-size:10px; color:#9CA3AF;">CI: <?= htmlspecialchars($encargadoCalle['dni'] ?? '—') ?></div>
        </div>
        <div style="text-align:center; padding-top:34px; border-top:1px solid #9CA3AF;">
            <div style="font-size:11px; color:#6B7280;">Encargado de Barrio</div>
            <div style="font-size:13px; font-weight:700; margin-top:4px;">
                <?= htmlspecialchars(trim(($encargadoBarrio['nombre'] ?? '') . ' ' . ($encargadoBarrio['apellido'] ?? ''))) ?: '—' ?>
            </div>
            <div style="font-size:10px; color:#9CA3AF;">CI: <?= htmlspecialchars($encargadoBarrio['dni'] ?? '—') ?></div>
        </div>
    </div>
</div>

<style>
@media print {
    @page { size: landscape; margin: 10mm; }
    .sidebar, .header, .notif-wrap, .menu-toggle, .sidebar-overlay, .no-print, form, .btn-primary { display:none !important; }
    .main { margin-left:0 !important; padding:0 !important; }
    .report-print-area { border:0 !important; box-shadow:none !important; padding:0 !important; }
    table { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>

<script>
(function () {
    var barrio = document.getElementById('rep-barrio');
    var calle = document.getElementById('rep-calle');
    if (!barrio || !calle) return;
    var opciones = Array.prototype.slice.call(calle.querySelectorAll('option[data-barrio]'));

    function filtrar() {
        var seleccion = barrio.value;
        opciones.forEach(function (opcion) {
            var coincide = seleccion === '0' || opcion.getAttribute('data-barrio') === seleccion;
            opcion.hidden = !coincide;
            opcion.disabled = !coincide;
        });
        var actual = calle.options[calle.selectedIndex];
        if (actual && actual.disabled) calle.value = '0';
    }

    barrio.addEventListener('change', filtrar);
    filtrar();
})();
</script>

<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
