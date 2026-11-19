<?php
// views/calle/reportar_pago.php — Módulo de Lote de Calle
global $pdo;
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
use app\models\mainModel;

if (empty($pdo)) {
    $pdo = (new mainModel())->conectar();
}

$user = check_dashboard_access([6]);
$page = 'reportar_pago';

// Periodo activo (por defecto mes actual)
$mes  = (int)($_GET['mes']  ?? date('n'));
$anio = (int)($_GET['anio'] ?? date('Y'));

// Array local para traducir los meses de forma nativa sin depender de helper externo
$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

try {
    // Calle asignada — Usando alias limpio 'dc' para evitar conflictos
    $calleStmt = $pdo->prepare("SELECT dc.calle_id, c.nombre as calle_nombre 
                            FROM detalles_encargado_calle dc 
                            JOIN calles c ON c.id = dc.calle_id 
                            WHERE dc.usuario_id = ?");
    $calleStmt->execute([$user['id']]);
    $calleData = $calleStmt->fetch(PDO::FETCH_ASSOC);
    $calle_id = $calleData['calle_id'] ?? null;

    // Fecha límite de pago exigida por el encargado de barrio
    $fechaLimiteStmt = $pdo->prepare(
        "SELECT cb.fecha_limite_pago
           FROM calles c
           LEFT JOIN configuraciones_barrio cb ON cb.barrio_id = c.barrio_id
          WHERE c.id = ?"
    );
    $fechaLimiteStmt->execute([$calle_id]);
    $fecha_limite_pago = $fechaLimiteStmt->fetchColumn() ?: '';

    if (!$calle_id) {
        $title = "Error - EcoReciclaje";
        $header_title = "Sin asignación";
        $header_subtitle = "No se pudo cargar la información de la calle.";
        ob_start();
        ?>
        <div class="card" style="padding: 14px; border-left: 4px solid #8A5A5A; background: #F7F2F2; color: #7A4F4F;">
            <h3 style="margin-top:0;">⚠️ No tienes una calle asignada</h3>
            <p style="font-size:14px; margin-bottom:0;">
                Tu usuario (ID: <strong><?= htmlspecialchars($user['id']) ?></strong>) no figura registrado en la tabla <code>detalles_encargado_calle</code>.
            </p>
        </div>
        <?php
        $content = ob_get_clean();
        include __DIR__ . '/../layouts/dashboard_layout.php';
        return;
    }

    // Lote correspondiente exactamente al periodo seleccionado.
    $loteStmt = $pdo->prepare(
        "SELECT * FROM lotes_calle
         WHERE calle_id = ? AND periodo_mes = ? AND periodo_anio = ?
         LIMIT 1"
    );
    $loteStmt->execute([$calle_id, $mes, $anio]);
    $lote = $loteStmt->fetch(PDO::FETCH_ASSOC);
    $lote_estado = $lote['estado'] ?? 'No Creado';

    // Contar viviendas activas
    $activasStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE calle_id=? AND estado_servicio='Activo'");
    $activasStmt->execute([$calle_id]);
    $total_activas = (int)$activasStmt->fetchColumn();

    // Contar exentas
    $exentasStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE calle_id=? AND estado_servicio='Activo' AND exento_cobro=1");
    $exentasStmt->execute([$calle_id]);
    $exentas_cant = (int)$exentasStmt->fetchColumn();

    // Casas que deben pagar (activas - exentas)
    $casas_a_cobrar = $total_activas - $exentas_cant;

    // Obtener cobros — CORREGIDO: Se cambiaron c.mes_recaudacion y c.anio_recaudacion por c.mes y c.anio
    $cobrosStmt = $pdo->prepare("SELECT c.*, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa 
                                 FROM cobros c 
                                 JOIN viviendas v ON c.vivienda_id = v.id 
                                 WHERE v.calle_id = ? AND c.mes = ? AND c.anio = ? AND c.estado = 'Pagado'");
    $cobrosStmt->execute([$calle_id, $mes, $anio]);
    $cobros = $cobrosStmt->fetchAll(PDO::FETCH_ASSOC);

    $total_recaudado = 0;
    foreach ($cobros as $c) {
        $total_recaudado += $c['monto'];
    }

    $casas_pagadas = count($cobros);
    $casas_morosas = $casas_a_cobrar - $casas_pagadas;

} catch (\Exception $e) {
    // Si la base de datos lanza un error, lo pintamos limpiamente en vez de romper el servidor
    $title = "Error de Base de Datos";
    $header_title = "Error Interno";
    $header_subtitle = "Ocurrió un problema al procesar las consultas SQL.";
    ob_start();
    echo "<div class='card' style='color:#8A5A5A; background:#F7F2F2; padding:20px; border-left:4px solid #8A5A5A;'>";
    echo "<h3>💥 Error detectado en la Base de Datos:</h3>";
    echo "<p><code>" . htmlspecialchars($e->getMessage()) . "</code></p>";
    echo "</div>";
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    return;
}

$title = "Reportar Lote - EcoReciclaje";
$header_title = "Reportar Pagos del Mes";
$header_subtitle = "Administra el envío y estado del lote de recaudación mensual de tu calle.";

ob_start();
?>

<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<?php if (!empty($fecha_limite_pago)): ?>
<div style="margin-bottom:16px; padding:12px 16px; background:#F2EEE7; border-left:4px solid #8A5A5A; border-radius:6px; font-size:13px; color:#6E5F45;">
    📅 <strong>Fecha límite de pago:</strong> <?= date('d/m/Y', strtotime($fecha_limite_pago)) ?>
    <span style="font-size:11px;">— definida por el encargado de barrio para los encargados de calle.</span>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start;">
    
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin:0;">💰 Pagos Recaudados en Calle: <?= htmlspecialchars($calleData['calle_nombre'] ?? 'Sin Nombre') ?></h3>
            
            <form method="GET" action="router.php" style="display:flex; gap:5px;">
                <input type="hidden" name="page" value="reportar_pago">
                <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
                <select name="mes" style="padding: 5px; border-radius:4px; border:1px solid #D1D5DB;">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m===$mes?'selected':'' ?>><?= $mesesNombres[$m] ?></option>
                    <?php endfor; ?>
                </select>
                <select name="anio" style="padding: 5px; border-radius:4px; border:1px solid #D1D5DB;">
                    <option value="<?= date('Y') ?>" selected><?= date('Y') ?></option>
                </select>
                <button type="submit" style="padding:5px 10px; background:#4C6E91; color:white; border:none; border-radius:4px; cursor:pointer;">Ir</button>
            </form>
        </div>

        <p style="font-size: 13px; color:#6B7280; margin-bottom: 10px;">
            A continuación se muestran los pagos que has marcado como 'Pagado' en la sección de viviendas cuyo dinero pertenece a la recaudación de este periodo.
        </p>

        <div style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:6px; margin-bottom:12px;">
            <div style="display:flex; align-items:center; gap:7px; min-width:0; padding:7px 9px; background:#F9FAFB; border:1px solid #E5E7EB;">
                <strong style="font-size:17px; color:#374151;"><?= $total_activas ?></strong>
                <span style="font-size:10px; line-height:1.15; color:#6B7280;">🏠<br>Activas</span>
            </div>
            <div style="display:flex; align-items:center; gap:7px; min-width:0; padding:7px 9px; background:#EEF2F6; border:1px solid #E0E7ED;">
                <strong style="font-size:17px; color:#4A5568;"><?= $exentas_cant ?></strong>
                <span style="font-size:10px; line-height:1.15; color:#4A5568;">🛡️<br>Exoneradas</span>
            </div>
            <div style="display:flex; align-items:center; gap:7px; min-width:0; padding:7px 9px; background:#E9EFF4; border:1px solid #DCE5EC;">
                <strong style="font-size:17px; color:#33485C;"><?= $casas_pagadas ?></strong>
                <span style="font-size:10px; line-height:1.15; color:#33485C;">✅<br>Pagadas</span>
            </div>
            <div style="display:flex; align-items:center; gap:7px; min-width:0; padding:7px 9px; background:#F4EDED; border:1px solid #E8D8D8;">
                <strong style="font-size:17px; color:#7A4F4F;"><?= max(0,$casas_morosas) ?></strong>
                <span style="font-size:10px; line-height:1.15; color:#7A4F4F;">⏳<br>Sin pagar</span>
            </div>
        </div>

        <div class="table-wrap">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB; text-align: left;">
                        <th style="padding: 12px;">Vivienda / Propietario</th>
                        <th style="padding: 12px;">Comprobante / Referencia</th>
                        <th style="padding: 12px; text-align: center;">Monto</th>
                        <th style="padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cobros)): ?>
                        <tr>
                            <td colspan="4" style="padding: 14px; text-align: center; color: #9CA3AF;">
                                No has registrado cobros efectivos para el periodo seleccionado de <?= $mesesNombres[$mes] ?>/<?= $anio ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cobros as $c): ?>
                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                <td style="padding: 12px;">
                                    <div style="font-weight: 600; color: #111827;">Nro. <?= htmlspecialchars($c['numero_casa']) ?></div>
                                    <div style="font-size: 11px; color: #6B7280;"><?= htmlspecialchars($c['propietario']) ?></div>
                                    <?php $identificacion = formato_identificacion($c['cedula'] ?? '', $c['cedula_tipo'] ?? 'V', $c['otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?><div style="font-size: 10px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <?php if (!empty($c['comprobante_calle'])): ?>
                                            <?php if (preg_match('/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i', $c['comprobante_calle'])): ?>
                                                <img src="<?= htmlspecialchars($c['comprobante_calle']) ?>" alt="Comp" style="width:48px;height:48px;border-radius:6px;object-fit:cover;border:1px solid #E5E7EB;cursor:pointer;" onclick="openComprobanteModal(this)" data-url="<?= htmlspecialchars($c['comprobante_calle']) ?>">
                                            <?php else: ?>
                                                <a href="#" data-url="<?= htmlspecialchars($c['comprobante_calle']) ?>" onclick="return openComprobanteModal(this)" style="background:#E9EFF4;color:#3E5C76;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:11px;">📎 Ver PDF</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#9CA3AF;">—</span>
                                        <?php endif; ?>
                                        <span style="font-size:13px;color:#111827;font-weight:700;background:#F9FAFB;padding:4px 8px;border-radius:4px;"><?= htmlspecialchars($c['referencia_pago'] ?? '—') ?></span>
                                    </div>
                                </td>
                                <td style="padding: 12px; text-align: center; font-weight: 700; color: #4C6E91;">
                                    <?= monto($c['monto']) ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <button onclick="openDetalleCobro(<?= $c['id'] ?>, '<?= htmlspecialchars($c['numero_casa'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['propietario'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['referencia_pago'] ?? '', ENT_QUOTES) ?>', <?= (float)$c['monto'] ?>, '<?= htmlspecialchars($c['comprobante_calle'] ?? '', ENT_QUOTES) ?>')"
                                            style="background:#F3F4F6; color:#374151; border:1px solid #D1D5DB; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:11px;">🔍 Detalle</button>
                                    <?php if (!in_array($lote_estado, ['Enviado', 'Aprobado'])): ?>
                                        <button onclick="openEditarCobro(<?= $c['id'] ?>, <?= $c['vivienda_id'] ?>, '<?= htmlspecialchars($c['numero_casa'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['propietario'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['referencia_pago'] ?? '', ENT_QUOTES) ?>')" 
                                                style="background:transparent; color:#6B7280; border:1px solid #D1D5DB; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:11px; margin-left:4px;">✏️ Editar</button>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF; font-size:11px; margin-left:4px;">🔒 Enviado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="background: #FAFAFA; border: 1px solid #E5E7EB;">
        <h4 style="margin-top: 0; margin-bottom: 10px; color: #374151;">📦 Control de Lote Mensual</h4>
        <div style="font-size: 12px; color:#6B7280; margin-bottom: 10px;">
            Periodo: <strong><?= $mesesNombres[$mes] ?> / <?= $anio ?></strong>
        </div>

        <div id="estado-lote" style="margin-bottom: 14px; padding: 12px; border-radius: 8px; background: white; border:1px solid #E5E7EB;">
            <div style="font-size: 11px; color:#9CA3AF; font-weight: 600; text-transform: uppercase;">Estado del Reporte</div>
            <div style="font-size: 16px; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                <?php if (in_array($lote_estado, ['No Creado', 'Abierto', 'Borrador'], true)): ?>
                    <span style="color: #7A6A4F;">🟢 Abierto / Recaudando</span>
                <?php elseif ($lote_estado === 'Enviado'): ?>
                    <span style="color: #3E5C76;">📤 Enviado al Barrio</span>
                <?php elseif ($lote_estado === 'Aprobado'): ?>
                    <span style="color: #3E5C76;">✅ Aprobado</span>
                <?php elseif ($lote_estado === 'Rechazado'): ?>
                    <span style="color: #8A5A5A;">❌ Rechazado (Corregir)</span>
                <?php endif; ?>
            </div>
            <?php if($lote_estado === 'Rechazado' && !empty($lote['observaciones'])): ?>
                <div style="margin-top: 8px; padding: 8px; background: #F7F2F2; border-left: 3px solid #9A6A6A; font-size: 11px; color: #7A4F4F;">
                    <strong>Motivo:</strong> <?= htmlspecialchars($lote['observaciones']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-bottom: 14px;">
            <div style="font-size: 11px; color:#6B7280;">Total Neto Recaudado:</div>
            <div style="font-size: 20px; font-weight: 800; color: #111827; margin-top: 2px;">
                <?= monto($total_recaudado) ?>
            </div>
            <div style="font-size: 11px; color:#9CA3AF; margin-top:2px;">Total transacciones: <?= count($cobros) ?></div>
        </div>

        <!-- Casas sin pagar -->
        <?php
        $pendientesStmt = $pdo->prepare(
            "SELECT v.id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.direccion
             FROM viviendas v
             WHERE v.calle_id = ? 
             AND v.estado_servicio = 'Activo' 
             AND v.exento_cobro = 0
             AND v.id NOT IN (SELECT vivienda_id FROM cobros WHERE mes = ? AND anio = ? AND estado = 'Pagado')
             ORDER BY CAST(v.numero_casa AS UNSIGNED) ASC, v.numero_casa ASC"
        );
        $pendientesStmt->execute([$calle_id, $mes, $anio]);
        $pendientes = $pendientesStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (!empty($pendientes)): ?>
        <div style="margin-bottom: 14px; padding: 12px; background: white; border-radius: 8px; border:1px solid #E8D8D8; border-left:3px solid #9A6A6A;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div style="font-size: 11px; color:#7A4F4F; font-weight: 600; text-transform: uppercase;">Casas sin pagar — <?= count($pendientes) ?></div>
                <a href="router.php?page=viviendas&sid=<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>" style="font-size:10px; color:#4C6E91; text-decoration:none; font-weight:600;">Ir a Marcar Pagos →</a>
            </div>
            <div style="max-height:200px; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#F7F2F2; text-align:left;">
                            <th style="padding:6px 8px;">#</th>
                            <th style="padding:6px 8px;">Propietario</th>
                            <th style="padding:6px 8px;">Dirección</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): ?>
                        <tr style="border-bottom:1px solid #F4EDED;">
                            <td style="padding:5px 8px; font-weight:700; color:#8A5A5A;"><?= htmlspecialchars($p['numero_casa']) ?></td>
                            <td style="padding:5px 8px;">
                                <?= htmlspecialchars($p['propietario']) ?>
                                <?php $identificacion = formato_identificacion($p['cedula'] ?? '', $p['cedula_tipo'] ?? 'V', $p['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?><div style="font-size:9px; color:#6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                            </td>
                            <td style="padding:5px 8px; color:#6B7280;"><?= htmlspecialchars($p['direccion'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: 10px;">
            <?php if (in_array($lote_estado, ['No Creado', 'Abierto', 'Borrador', 'Rechazado'], true)): ?>
                <?php if ($total_activas > $exentas_cant): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
                        <input type="hidden" name="form_type" value="enviar_lote_calle">
                        <input type="hidden" name="mes" value="<?= $mes ?>">
                        <input type="hidden" name="anio" value="<?= $anio ?>">
                        
                        <div style="margin-bottom: 10px; padding: 10px; background: #EEF2F6; border-radius: 6px; border: 1px solid #CDDCE8;">
                            <label style="font-size: 11px; font-weight: 700; color: #33485C; display: block; margin-bottom: 4px;">
                                🔢 Referencia del Lote (número de voucher o transacción del lote):
                            </label>
                            <input type="text" name="referencia_lote" placeholder="Ej: Voucher Lote #00123" required
                                   style="width: 100%; padding: 8px; border: 1px solid #A9C0D3; border-radius: 4px; font-size: 12px; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 8px; padding: 10px; background: #F2EEE7; border-radius: 6px; border: 1px dashed #8A7A5A;">
                            <label style="font-size: 11px; font-weight: 700; color: #6E5F45; display: block; margin-bottom: 4px;">
                                📷 Comprobante Global del Lote (captura de transferencia):
                            </label>
                            <input type="file" name="comprobante_lote" accept="image/*,.pdf" style="font-size: 11px; width: 100%;">
                        </div>
                        
                        <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; font-weight: 700; background: #4C6E91; border: none; border-radius: 8px; cursor: pointer; display: inline-block; text-align: center;" onclick="return confirm('¿Estás seguro de enviar el lote al Encargado de Barrio? Se incluirán las <?= count($pendientes) ?> viviendas pendientes.')">
                            📤 <?= $lote_estado === 'Rechazado' ? 'Reenviar Lote Corregido' : 'Enviar Lote al Barrio' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <button disabled style="width:100%; padding:12px; background:#F3F4F6; color:#9CA3AF; border:none; border-radius:8px; cursor:not-allowed;">
                        Sin viviendas por reportar
                    </button>
                <?php endif; ?>
            <?php elseif ($lote_estado === 'Aprobado'): ?>
                <div style="text-align:center; padding:15px; background:#E9EFF4; border-radius:8px; font-size:13px; color:#33485C;">
                    ✅ <strong>Lote aprobado por el Barrio</strong><br>
                    <small>Monto aprobado: <?= monto($lote['monto_recolectado']) ?></small>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:15px; background:#EEF2F6; border-radius:8px; font-size:13px; color:#33485C;">
                    📤 Lote enviado — esperando revisión del Barrio
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="modal-detalle-cobro" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;" onclick="if(event.target===this)closeDetalleCobro()">
    <div style="background:white; border-radius:16px; padding:28px; width:90%; max-width:480px; box-shadow:0 20px 60px rgba(0,0,0,0.3); position:relative; animation:modalIn .25s ease;">
        <button onclick="closeDetalleCobro()" style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:22px; cursor:pointer; color:#9CA3AF;">✕</button>
        <div style="text-align:center; margin-bottom:20px;">
            <div style="font-size:40px; margin-bottom:8px;">🔍</div>
            <h3 style="margin:0; font-size:18px;">Detalle del Pago</h3>
            <p style="color:#6B7280; font-size:13px; margin:4px 0 0;">Revisa la información antes de enviar</p>
        </div>
        <div id="detalle-vivienda" style="background:#F3F4F6; border-radius:10px; padding:14px; margin-bottom:18px; display:flex; gap:12px; align-items:center;">
            <div style="background:#4C6E91; color:white; width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0;" id="detalle-casa-num">#</div>
            <div>
                <div style="font-weight:700; font-size:14px;" id="detalle-propietario">—</div>
                <div style="font-size:11px; color:#6B7280;">Vivienda</div>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:18px;">
            <div style="background:#F9FAFB; padding:12px; border-radius:8px; text-align:center;">
                <div style="font-size:10px; color:#6B7280; text-transform:uppercase; font-weight:600;">Referencia</div>
                <div style="font-size:14px; font-weight:700; color:#111827; margin-top:4px;" id="detalle-referencia">—</div>
            </div>
            <div style="background:#F9FAFB; padding:12px; border-radius:8px; text-align:center;">
                <div style="font-size:10px; color:#6B7280; text-transform:uppercase; font-weight:600;">Monto</div>
                <div style="font-size:18px; font-weight:800; color:#3E5C76; margin-top:4px;" id="detalle-monto">—</div>
            </div>
        </div>
        <div style="margin-bottom:10px;">
            <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:6px;">COMPROBANTE</div>
            <div id="detalle-comprobante" style="background:#F9FAFB; border-radius:8px; padding:16px; text-align:center; min-height:100px; display:flex; align-items:center; justify-content:center; border:1px solid #E5E7EB;">
                <span style="color:#9CA3AF;">Sin comprobante</span>
            </div>
        </div>
    </div>
</div>

<div id="modal-editar-cobro" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;" onclick="if(event.target===this)closeEditarCobro()">
    <div style="background:white; border-radius:16px; padding:28px; width:90%; max-width:440px; box-shadow:0 20px 60px rgba(0,0,0,0.3); position:relative; animation:modalIn .25s ease;">
        <button onclick="closeEditarCobro()" style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:22px; cursor:pointer; color:#9CA3AF;">✕</button>
        <div style="text-align:center; margin-bottom:20px;">
            <div style="font-size:40px; margin-bottom:8px;">✏️</div>
            <h3 style="margin:0; font-size:18px;">Editar Datos del Cobro</h3>
            <p style="color:#6B7280; font-size:13px; margin:4px 0 0;">Corrige la referencia o el comprobante</p>
        </div>
        <form method="POST" enctype="multipart/form-data" id="form-editar-cobro">
            <input type="hidden" name="action" value="editar_cobro">
            <input type="hidden" name="cobro_id" id="edit-cobro-id">
            <div style="background:#F3F4F6; border-radius:10px; padding:14px; margin-bottom:18px; display:flex; gap:12px; align-items:center;">
                <div style="background:#8A7A5A; color:white; width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0;" id="edit-casa-num">#</div>
                <div>
                    <div style="font-weight:700; font-size:14px;" id="edit-propietario">—</div>
                    <div style="font-size:11px; color:#6B7280;">Vivienda</div>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:5px;">REFERENCIA DE PAGO</label>
                <input type="text" name="referencia_pago" id="edit-referencia" required placeholder="Ej: Voucher #12345" 
                       style="width:100%; padding:12px; border:2px solid #E5E7EB; border-radius:10px; font-size:14px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:5px;">NUEVO COMPROBANTE (opcional)</label>
                <div style="border:2px dashed #D1D5DB; border-radius:10px; padding:20px; text-align:center; cursor:pointer; background:#F9FAFB; transition:.2s;"
                     onclick="document.getElementById('edit-file-input').click()" id="edit-upload-area">
                    <div style="font-size:36px; margin-bottom:6px;">📷</div>
                    <div style="font-size:13px; color:#6B7280;">Haz clic para cambiar la imagen</div>
                    <div style="font-size:11px; color:#9CA3AF; margin-top:2px;">PNG, JPG o PDF · Deja vacío para mantener actual</div>
                </div>
                <input type="file" name="comprobante_pago" id="edit-file-input" accept="image/*,.pdf" style="display:none;" onchange="document.getElementById('edit-upload-area').innerHTML='<div style=font-size:24px;margin-bottom:4px;>✅</div><div style=font-size:13px;color:#3E5C76;font-weight:600;>Archivo seleccionado</div><div style=font-size:11px;color:#6B7280;>' + this.files[0].name + '</div>'">
            </div>
            <button type="submit" style="width:100%; background:#8A7A5A; color:white; border:none; padding:14px; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer;">
                💾 Guardar Cambios
            </button>
        </form>
    </div>
</div>

<style>
@keyframes modalIn { from { opacity:0; transform:scale(.95); } to { opacity:1; transform:scale(1); } }
</style>

<script>
function openDetalleCobro(id, casa, prop, ref, monto, comprobante) {
    document.getElementById('detalle-casa-num').textContent = '#' + casa;
    document.getElementById('detalle-propietario').textContent = prop;
    document.getElementById('detalle-referencia').textContent = ref || '—';
    document.getElementById('detalle-monto').textContent = fmtMonto(monto);
    var compDiv = document.getElementById('detalle-comprobante');
    if (comprobante) {
        var isImage = comprobante.match(/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i);
        if (isImage) {
            compDiv.innerHTML = '<img src="' + encodeURI(comprobante) + '" alt="Comprobante" style="max-width:100%; max-height:240px; border-radius:6px; object-fit:contain;">';
        } else {
            compDiv.innerHTML = '<a href="' + encodeURI(comprobante) + '" target="_blank" style="background:#E9EFF4; color:#3E5C76; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:600;">📄 Ver PDF</a>';
        }
    } else {
        compDiv.innerHTML = '<span style="color:#9CA3AF;">Sin comprobante</span>';
    }
    document.getElementById('modal-detalle-cobro').style.display = 'flex';
}
function closeDetalleCobro() {
    document.getElementById('modal-detalle-cobro').style.display = 'none';
}

function openEditarCobro(id, vivienda_id, casa, prop, ref) {
    document.getElementById('edit-cobro-id').value = id;
    document.getElementById('edit-casa-num').textContent = '#' + casa;
    document.getElementById('edit-propietario').textContent = prop;
    document.getElementById('edit-referencia').value = ref !== '' ? ref : '';
    document.getElementById('edit-upload-area').innerHTML = '<div style="font-size:36px;margin-bottom:6px;">📷</div><div style="font-size:13px;color:#6B7280;">Haz clic para cambiar la imagen</div><div style="font-size:11px;color:#9CA3AF;margin-top:2px;">PNG, JPG o PDF · Deja vacío para mantener actual</div>';
    document.getElementById('edit-file-input').value = '';
    document.getElementById('modal-editar-cobro').style.display = 'flex';
}

function closeEditarCobro() {
    document.getElementById('modal-editar-cobro').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>