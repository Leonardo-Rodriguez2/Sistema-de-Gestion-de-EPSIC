<?php
// views/barrio/reportar_pago.php — Módulo de Verificación de Lotes de Calle
global $pdo;
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
use app\models\mainModel;

if (empty($pdo)) $pdo = (new mainModel())->conectar();

$user = check_dashboard_access([5]);
$page = 'reportar_pago';

// Periodo
$mes  = isset($_GET['mes']) ? (int)$_GET['mes'] : null;
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : null;
$lote_id = (int)($_GET['lote_id'] ?? 0);
$lote_barrio_id = (int)($_GET['lote_barrio_id'] ?? 0);

// 1. Obtener Barrio (Protegido)
$barrio_id = 0;
$barrio = ['nombre' => 'Sin asignar'];
try {
    $barrioStmt = $pdo->prepare("SELECT b.* FROM detalles_encargado_barrio d JOIN barrios b ON d.barrio_id = b.id WHERE d.usuario_id = ?");
    $barrioStmt->execute([$user['id']]);
    if ($res = $barrioStmt->fetch(PDO::FETCH_ASSOC)) {
        $barrio = $res;
        $barrio_id = $barrio['id'];
    }
} catch (\Throwable $e) {}

// Si la notificación apunta a un lote concreto, usar exactamente su periodo.
if ($lote_id > 0) {
    try {
        $periodoStmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(lc.periodo_mes,0), lc.mes) AS mes,
                    COALESCE(NULLIF(lc.periodo_anio,0), lc.anio) AS anio
             FROM lotes_calle lc
             JOIN calles c ON c.id = lc.calle_id
             WHERE lc.id = ? AND c.barrio_id = ?
             LIMIT 1"
        );
        $periodoStmt->execute([$lote_id, $barrio_id]);
        if ($lp = $periodoStmt->fetch(PDO::FETCH_ASSOC)) {
            $mes = (int)$lp['mes'];
            $anio = (int)$lp['anio'];
        }
    } catch (\Throwable $e) {}
}

if (($mes === null || $anio === null) && $lote_barrio_id > 0) {
    try {
        $periodoStmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(periodo_mes,0), mes) AS mes,
                    COALESCE(NULLIF(periodo_anio,0), anio) AS anio
             FROM lotes_barrio
             WHERE id = ? AND barrio_id = ?
             LIMIT 1"
        );
        $periodoStmt->execute([$lote_barrio_id, $barrio_id]);
        if ($lp = $periodoStmt->fetch(PDO::FETCH_ASSOC)) {
            $mes = (int)$lp['mes'];
            $anio = (int)$lp['anio'];
        }
    } catch (\Throwable $e) {}
}

// Si no se solicita un periodo concreto, mostrar el último periodo con lotes
// del barrio para que la bandeja no parezca vacía por una fecha sin actividad.
if ($mes === null || $anio === null) {
    $periodoStmt = $pdo->prepare(
        "SELECT COALESCE(NULLIF(lc.periodo_mes,0), lc.mes) AS mes,
                COALESCE(NULLIF(lc.periodo_anio,0), lc.anio) AS anio
         FROM lotes_calle lc
         JOIN calles c ON c.id = lc.calle_id
         WHERE c.barrio_id = ?
         ORDER BY COALESCE(NULLIF(lc.periodo_anio,0), lc.anio) DESC,
                  COALESCE(NULLIF(lc.periodo_mes,0), lc.mes) DESC, lc.id DESC
         LIMIT 1"
    );
    $periodoStmt->execute([$barrio_id]);
    $ultimoPeriodo = $periodoStmt->fetch(PDO::FETCH_ASSOC);
    $mes = $ultimoPeriodo ? (int)$ultimoPeriodo['mes'] : (int)date('n');
    $anio = $ultimoPeriodo ? (int)$ultimoPeriodo['anio'] : (int)date('Y');
}

// 2. Lote de Barrio del periodo (Protegido)
$loteBarrio = false;
try {
    if ($lote_barrio_id > 0) {
        $loteBarrioStmt = $pdo->prepare(
            "SELECT lb.*, u.nombre as encargado_nombre
             FROM lotes_barrio lb
             LEFT JOIN usuarios u ON lb.encargado_barrio_id = u.id
             WHERE lb.id = ? AND lb.barrio_id = ?"
        );
        $loteBarrioStmt->execute([$lote_barrio_id, $barrio_id]);
    } else {
        $loteBarrioStmt = $pdo->prepare(
            "SELECT lb.*, u.nombre as encargado_nombre
             FROM lotes_barrio lb
             LEFT JOIN usuarios u ON lb.encargado_barrio_id = u.id
             WHERE lb.barrio_id = ?
               AND ((lb.mes = ? AND lb.anio = ?) OR (lb.periodo_mes = ? AND lb.periodo_anio = ?))
             LIMIT 1"
        );
        $loteBarrioStmt->execute([$barrio_id, $mes, $anio, $mes, $anio]);
    }
    $loteBarrio = $loteBarrioStmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 3. Lotes de Calle del periodo en el barrio (Protegido)
$lotes_calle = [];
try {
    $lotesCalle = $pdo->prepare(
        "SELECT lc.*, c.nombre as calle_nombre, u.nombre as encargado_nombre, u.apellido as encargado_apellido,
                (SELECT COUNT(*) FROM viviendas v WHERE v.calle_id = lc.calle_id AND v.estado_servicio = 'Activo' 
                 AND v.id NOT IN (SELECT vivienda_id FROM solicitudes_vivienda WHERE tipo='Baja' AND estado='Aprobado')) as total_viviendas,
                (SELECT COUNT(*) FROM viviendas v WHERE v.calle_id = lc.calle_id AND v.estado_servicio = 'Activo' AND v.exento_cobro = 1
                 AND v.id NOT IN (SELECT vivienda_id FROM solicitudes_vivienda WHERE tipo='Baja' AND estado='Aprobado')) as viviendas_exentas
         FROM lotes_calle lc
         JOIN calles c ON lc.calle_id = c.id
         JOIN usuarios u ON lc.encargado_calle_id = u.id
         WHERE c.barrio_id=?
           AND COALESCE(NULLIF(lc.periodo_mes,0), lc.mes)=?
           AND COALESCE(NULLIF(lc.periodo_anio,0), lc.anio)=?
         ORDER BY lc.fecha_envio DESC, lc.id DESC"
    );
    $lotesCalle->execute([$barrio_id, $mes, $anio]);
    $lotes_calle = $lotesCalle->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 4. Config tarifa (Protegido)
$config = ['cuota_mensual' => 0.00, 'multa_renovacion' => 0.00];
try {
    $cuotaStmt = $pdo->prepare("SELECT cuota_mensual, multa_renovacion FROM configuraciones_barrio WHERE barrio_id=?");
    $cuotaStmt->execute([$barrio_id]);
    if ($cfg = $cuotaStmt->fetch(PDO::FETCH_ASSOC)) {
        $config = $cfg;
    }
} catch (\Throwable $e) {}

// 5. Totals del barrio (Protegido)
$viviendas_activas = 0;
$casas_pagadas_total = 0;
$casas_morosas_total = 0;
$monto_reportado_total = 0;
$monto_esperado_lotes = 0;
$exentas_count = 0;
try {
    $vActStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE barrio_id = ? AND estado_servicio = 'Activo'");
    $vActStmt->execute([$barrio_id]);
    $viviendas_activas = (int)$vActStmt->fetchColumn();

    $exentasStmt = $pdo->prepare("SELECT COUNT(*) FROM viviendas WHERE barrio_id = ? AND estado_servicio = 'Activo' AND exento_cobro = 1");
    $exentasStmt->execute([$barrio_id]);
    $exentas_count = (int)$exentasStmt->fetchColumn();

    $sumStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(casas_pagadas),0), COALESCE(SUM(casas_morosas),0),
                COALESCE(SUM(monto_recolectado),0), COALESCE(SUM(monto_esperado),0)
         FROM lotes_calle lc
         JOIN calles c ON c.id = lc.calle_id
         WHERE c.barrio_id = ?
           AND COALESCE(NULLIF(lc.periodo_mes,0), lc.mes) = ?
           AND COALESCE(NULLIF(lc.periodo_anio,0), lc.anio) = ?"
    );
    $sumStmt->execute([$barrio_id, $mes, $anio]);
    $row = $sumStmt->fetch(PDO::FETCH_NUM);
    $casas_pagadas_total = (int)$row[0];
    $casas_morosas_total = (int)$row[1];
    $monto_reportado_total = (float)$row[2];
    $monto_esperado_lotes = (float)$row[3];
} catch (\Throwable $e) {}

$casas_sin_reportar = $viviendas_activas - $exentas_count - $casas_pagadas_total - $casas_morosas_total;
$total_esperado_barrio = ($viviendas_activas - $exentas_count) * ($config['cuota_mensual'] ?: 0);
$diferencia_total = $total_esperado_barrio - $monto_reportado_total;

$meses_nombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$pagos_contingencia = [];
if ($barrio_id > 0) {
    $pagosContingenciaStmt = $pdo->prepare(
        "SELECT co.id, co.monto, co.mes, co.anio, co.estado, co.referencia_pago, co.fecha_emision,
                v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.numero_casa, v.direccion, v.telefono,
                c.nombre AS calle_nombre
         FROM cobros co
         JOIN viviendas v ON v.id = co.vivienda_id
         JOIN calles c ON c.id = v.calle_id
         WHERE v.barrio_id = ? AND co.mes = ? AND co.anio = ?
           AND co.estado = 'Pendiente' AND v.estado_servicio = 'Activo'
         ORDER BY c.nombre, v.numero_casa"
    );
    $pagosContingenciaStmt->execute([$barrio_id, $mes, $anio]);
    $pagos_contingencia = $pagosContingenciaStmt->fetchAll(PDO::FETCH_ASSOC);
}

$title          = "Lotes de Calle - EcoCusco";
$header_title   = "Verificación de Pagos";
$header_subtitle = "Barrio: " . htmlspecialchars($barrio['nombre']) . " — {$meses_nombres[$mes]} {$anio}";
$modoMarcar = ($_GET['modo'] ?? '') === 'marcar';
if ($modoMarcar) {
    $title = "Marcar Pagos - EcoCusco";
    $header_title = "Marcar Pagos";
    $header_subtitle = "Registra rápidamente los pagos de las casas de tu barrio.";
}

$estado_color = ['Borrador'=>'#6B7280','Enviado'=>'#4C6E91','Aprobado'=>'#4C6E91','Rechazado'=>'#8A5A5A'];

ob_start();
?>
<?php if ($modoMarcar): ?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>
    <style>
        .marcar-pagos-toolbar {
            display:flex; align-items:center; justify-content:space-between;
            gap:12px; flex-wrap:wrap; margin-bottom:18px;
        }
        .marcar-pagos-toolbar form {
            display:flex; align-items:center; gap:8px; flex-wrap:wrap;
        }
        .marcar-pagos-toolbar select {
            padding:10px 12px; border:1px solid #D1D5DB; border-radius:7px;
            background:#fff; font-size:13px;
        }
        .marcar-pagos-calle {
            border:1px solid #E5E7EB; border-radius:10px; overflow:hidden;
            margin-bottom:14px; background:#fff;
        }
        .marcar-pagos-calle h3 {
            margin:0; padding:13px 16px; background:#F9FAFB;
            color:#111827; font-size:14px;
        }
        .marcar-pago-row {
            display:flex; align-items:center; justify-content:space-between;
            gap:12px; padding:13px 16px; border-top:1px solid #F3F4F6;
        }
        .marcar-pago-datos { min-width:0; }
        .marcar-pago-nombre { font-weight:700; color:#111827; }
        .marcar-pago-casa { color:#6B7280; font-size:12px; margin-top:3px; }
        .marcar-pago-boton {
            flex-shrink:0; border:0; border-radius:7px; padding:10px 14px;
            background:#3E5C76; color:#fff; font-weight:700; cursor:pointer;
        }
        .marcar-pago-ver { flex-shrink:0; border:1px solid #D1D5DB; padding:9px 12px; background:#fff; color:#33485C; font-weight:700; cursor:pointer; }
        .marcar-pago-acciones { display:flex; gap:7px; align-items:center; flex-wrap:wrap; }
        .pago-detalle-modal { display:none; position:fixed; inset:0; z-index:2000; align-items:center; justify-content:center; padding:18px; background:rgba(23,33,43,.68); }
        .pago-detalle-modal.is-open { display:flex; }
        .pago-detalle-box { width:min(100%,480px); background:#fff; border:1px solid #D9E1E7; box-shadow:0 20px 60px rgba(23,33,43,.25); padding:24px; }
        .pago-detalle-head { display:flex; justify-content:space-between; gap:15px; border-bottom:1px solid #E8EEF2; padding-bottom:16px; margin-bottom:16px; }
        .pago-detalle-head h3 { margin:0 0 4px; color:#17212B; }
        .pago-detalle-head p { margin:0; color:#657384; font-size:11px; }
        .pago-detalle-close { border:0; background:#EEF2F5; color:#344454; width:30px; height:30px; cursor:pointer; font-size:16px; }
        .pago-detalle-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .pago-detalle-item { border:1px solid #E8EEF2; padding:12px; }
        .pago-detalle-item small { display:block; color:#657384; text-transform:uppercase; font-weight:700; font-size:9px; margin-bottom:5px; }
        .pago-detalle-item strong { color:#17212B; font-size:13px; overflow-wrap:anywhere; }
        .pago-detalle-total { grid-column:1/-1; background:#EEF4F7; border-left:3px solid #176B87; padding:14px; display:flex; justify-content:space-between; align-items:center; }
        .pago-detalle-total strong { color:#176B87; font-size:20px; }
        @media(max-width:600px) {
            .marcar-pago-row { align-items:flex-start; flex-direction:column; }
            .marcar-pago-boton { width:100%; }
        }
    </style>

    <div class="marcar-pagos-toolbar">
        <div>
            <strong style="font-size:16px; color:#111827;">Pagos pendientes</strong>
            <div style="font-size:12px; color:#6B7280; margin-top:4px;">
                <?= $meses_nombres[$mes] ?> <?= $anio ?> · Selecciona una casa para marcarla como pagada.
            </div>
        </div>
        <form method="GET" action="router.php">
            <input type="hidden" name="page" value="reportar_pago">
            <input type="hidden" name="modo" value="marcar">
            <select name="mes" aria-label="Mes">
                <?php for($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= $meses_nombres[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="anio" aria-label="Año">
                <?php for($a=date('Y')-1;$a<=date('Y')+1;$a++): ?>
                    <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn-primary" style="padding:10px 14px;">Ver pagos</button>
        </form>
    </div>

    <?php if (!$pagos_contingencia): ?>
        <div class="card" style="text-align:center; padding:42px 20px;">
            <div style="font-size:38px; margin-bottom:10px;">✅</div>
            <strong style="color:#111827;">No hay pagos pendientes</strong>
            <p style="margin:7px 0 0; color:#6B7280; font-size:13px;">
                Todas las casas cobrables están marcadas como pagadas para este periodo.
            </p>
        </div>
    <?php else:
        $pagosPorCalle = [];
        foreach ($pagos_contingencia as $pago) {
            $pagosPorCalle[$pago['calle_nombre']][] = $pago;
        }
    ?>
        <?php foreach ($pagosPorCalle as $calleNombre => $pagosCalle): ?>
            <section class="marcar-pagos-calle">
                <h3>🛣️ <?= htmlspecialchars($calleNombre) ?> <span style="font-weight:400; color:#6B7280;">(<?= count($pagosCalle) ?>)</span></h3>
                <?php foreach ($pagosCalle as $pago): ?>
                    <div class="marcar-pago-row" id="cobro-<?= (int)$pago['id'] ?>">
                        <div class="marcar-pago-datos">
                            <div class="marcar-pago-nombre"><?= htmlspecialchars($pago['propietario']) ?></div>
                            <?php $identificacion = formato_identificacion($pago['cedula'] ?? '', $pago['cedula_tipo'] ?? 'V', $pago['otro_identificador'] ?? ''); ?>
                            <?php if ($identificacion !== ''): ?><div class="marcar-pago-casa" style="font-size:11px;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                            <div class="marcar-pago-casa">Casa #<?= htmlspecialchars($pago['numero_casa']) ?> · Bs. <?= number_format((float)$pago['monto'], 2) ?></div>
                        </div>
                        <div class="marcar-pago-acciones">
                        <button type="button" class="marcar-pago-ver" onclick='openPagoDetalle(<?= json_encode([
                            "casa" => $pago["numero_casa"],
                            "propietario" => $pago["propietario"],
                            "calle" => $pago["calle_nombre"],
                            "direccion" => $pago["direccion"],
                            "telefono" => $pago["telefono"],
                            "monto" => number_format((float)$pago["monto"], 2),
                            "periodo" => $meses_nombres[$mes] . " " . $anio,
                            "referencia" => $pago["referencia_pago"] ?? "Sin referencia",
                            "estado" => $pago["estado"]
                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ver detalle</button>
                        <form method="POST" action="router.php?page=reportar_pago&modo=marcar&mes=<?= (int)$mes ?>&anio=<?= (int)$anio ?>" onsubmit="return confirm('¿Marcar este pago como recibido?')">
                            <input type="hidden" name="page" value="reportar_pago">
                            <input type="hidden" name="action" value="verificar_pago_casa">
                            <input type="hidden" name="cobro_id" value="<?= (int)$pago['id'] ?>">
                            <input type="hidden" name="decision" value="Verificado">
                            <button type="submit" class="marcar-pago-boton">✅ Marcar pagado</button>
                        </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <div id="pagoDetalleModal" class="pago-detalle-modal" onclick="if(event.target===this)closePagoDetalle()">
        <div class="pago-detalle-box" role="dialog" aria-modal="true" aria-labelledby="pagoDetalleTitulo">
            <div class="pago-detalle-head">
                <div><span class="eyebrow">Revisión de pago</span><h3 id="pagoDetalleTitulo">Datos de la vivienda</h3><p>Verifica la información antes de marcar el pago.</p></div>
                <button type="button" class="pago-detalle-close" onclick="closePagoDetalle()" aria-label="Cerrar">×</button>
            </div>
            <div class="pago-detalle-grid">
                <div class="pago-detalle-item"><small>Propietario</small><strong id="pd-propietario">—</strong></div>
                <div class="pago-detalle-item"><small>Casa</small><strong id="pd-casa">—</strong></div>
                <div class="pago-detalle-item"><small>Calle</small><strong id="pd-calle">—</strong></div>
                <div class="pago-detalle-item"><small>Teléfono</small><strong id="pd-telefono">—</strong></div>
                <div class="pago-detalle-item"><small>Dirección</small><strong id="pd-direccion">—</strong></div>
                <div class="pago-detalle-item"><small>Periodo</small><strong id="pd-periodo">—</strong></div>
                <div class="pago-detalle-item"><small>Referencia</small><strong id="pd-referencia">—</strong></div>
                <div class="pago-detalle-item"><small>Estado</small><strong id="pd-estado">Pendiente</strong></div>
                <div class="pago-detalle-total"><span>Total a registrar</span><strong id="pd-monto">Bs. 0.00</strong></div>
            </div>
        </div>
    </div>
    <script>
    function openPagoDetalle(data) {
        Object.keys(data).forEach(function(key) {
            var target = document.getElementById('pd-' + key);
            if (target) target.textContent = data[key] || '—';
        });
        document.getElementById('pd-casa').textContent = '#' + (data.casa || '—');
        document.getElementById('pd-monto').textContent = 'Bs. ' + (data.monto || '0.00');
        document.getElementById('pagoDetalleModal').classList.add('is-open');
        document.body.classList.add('epsic-modal-open');
    }
    function closePagoDetalle() {
        document.getElementById('pagoDetalleModal').classList.remove('is-open');
        document.body.classList.remove('epsic-modal-open');
    }
    </script>
<?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    return;
endif;
?>
<div class="card" style="margin-bottom:18px; border-left:3px solid #7A6A4F;">
    <h3 style="margin:0 0 6px;">🧾 Registro manual por contingencia</h3>
    <p style="margin:0 0 12px; color:#6B7280; font-size:12px;">
        Use esta opción si el encargado de calle no pudo enviar su lote. Solo aparecen cobros pendientes del periodo seleccionado.
    </p>
    <?php if (!$pagos_contingencia): ?>
        <div style="color:#6B7280; font-size:12px;">No hay pagos pendientes para marcar manualmente.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead><tr><th style="text-align:left;padding:7px;">Casa</th><th style="text-align:left;padding:7px;">Calle</th><th style="padding:7px;">Monto</th><th style="padding:7px;">Acción</th></tr></thead>
                <tbody>
                <?php foreach ($pagos_contingencia as $pago): ?>
                    <tr>
                        <td style="padding:7px;">
                            <?= htmlspecialchars($pago['propietario']) ?> #<?= htmlspecialchars($pago['numero_casa']) ?>
                            <?php $identificacion = formato_identificacion($pago['cedula'] ?? '', $pago['cedula_tipo'] ?? 'V', $pago['otro_identificador'] ?? ''); ?>
                            <?php if ($identificacion !== ''): ?><div style="font-size:9px; color:#6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                        </td>
                        <td style="padding:7px;"><?= htmlspecialchars($pago['calle_nombre']) ?></td>
                        <td style="padding:7px;text-align:center;">Bs. <?= number_format((float)$pago['monto'], 2) ?></td>
                        <td style="padding:7px;text-align:center;">
                            <form method="POST" action="router.php" onsubmit="return confirm('¿Marcar este pago como recibido?')">
                                <input type="hidden" name="action" value="verificar_pago_casa">
                                <input type="hidden" name="page" value="reportar_pago">
                                <input type="hidden" name="cobro_id" value="<?= (int)$pago['id'] ?>">
                                <input type="hidden" name="decision" value="Verificado">
                                <button type="submit" style="background:#3E5C76;color:#fff;border:0;border-radius:5px;padding:5px 9px;cursor:pointer;">Marcar pagado</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<div class="card" style="margin-bottom:18px; padding:10px 14px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <form method="GET" action="router.php" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="page" value="reportar_pago">
        <label style="font-size:11px; font-weight:600; color:#6B7280;">PERIODO:</label>
        <select name="mes" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
            <?php for($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $m==$mes?'selected':'' ?>><?= $meses_nombres[$m] ?></option>
            <?php endfor; ?>
        </select>
        <select name="anio" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
            <?php for($a=date('Y')-1;$a<=date('Y')+1;$a++): ?>
                <option value="<?= $a ?>" <?= $a==$anio?'selected':'' ?>><?= $a ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" style="background:#374151; color:white; border:none; padding:5px 12px; border-radius:4px; font-size:12px; cursor:pointer;">Ver</button>
    </form>
    <form method="POST" style="display:flex; gap:6px; align-items:center; margin-left:auto;">
        <input type="hidden" name="form_type" value="actualizar_configuracion_barrio">
        <label style="font-size:11px; color:#6B7280;">Cuota Bs.</label>
        <input type="number" step="0.01" name="cuota_mensual" value="<?= $config['cuota_mensual'] ?? 0 ?>"
            style="width:70px; padding:4px 6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
        <label style="font-size:11px; color:#6B7280;">Multa Bs.</label>
        <input type="number" step="0.01" name="multa_renovacion" value="<?= $config['multa_renovacion'] ?? 0 ?>"
            style="width:70px; padding:4px 6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
        <button type="submit" style="background:#6B7280; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">Guardar</button>
    </form>
</div>

<div class="card" style="margin-bottom:18px; border-left:3px solid #6B7280; background:#fff;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
        <div>
            <strong style="font-size:14px;">Resumen del Barrio — <?= $meses_nombres[$mes] ?> <?= $anio ?></strong>
            <div style="font-size:12px; color:#6B7280; margin-top:3px;">
                Cuota: Bs. <?= number_format($config['cuota_mensual'] ?: 0, 2) ?>
                <?php if($viviendas_activas > 0): ?>
                • Esperado: <strong>Bs. <?= number_format($total_esperado_barrio, 2) ?></strong>
                (<?= $viviendas_activas ?> casas × Bs. <?= number_format($config['cuota_mensual'] ?: 0, 2) ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(100px, 1fr)); gap:8px; margin-top:10px; font-size:12px; text-align:center;">
        <div style="background:#F9FAFB; padding:8px; border-radius:6px;">
            <div style="font-size:20px; font-weight:700; color:#111827;"><?= $viviendas_activas ?></div>
            <div style="color:#6B7280;">Activas</div>
        </div>
        <div style="background:#F9FAFB; padding:8px; border-radius:6px;">
            <div style="font-size:20px; font-weight:700; color:#4A5568;"><?= $exentas_count ?></div>
            <div style="color:#6B7280;">Exoneradas</div>
        </div>
        <div style="background:#F9FAFB; padding:8px; border-radius:6px;">
            <div style="font-size:20px; font-weight:700; color:#3E5C76;"><?= $casas_pagadas_total ?></div>
            <div style="color:#6B7280;">Pagadas</div>
        </div>
        <div style="background:#F9FAFB; padding:8px; border-radius:6px;">
            <div style="font-size:20px; font-weight:700;"><?= max(0, $casas_sin_reportar) ?></div>
            <div style="color:#6B7280;">Sin pagar</div>
        </div>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:8px; margin-top:8px; font-size:12px; text-align:center;">
        <div style="padding:6px; border-radius:4px; background:#F9FAFB;">
            <span style="color:#6B7280;">Reportado:</span>
            <strong style="margin-left:4px;">Bs. <?= number_format($monto_reportado_total, 2) ?></strong>
        </div>
        <div style="padding:6px; border-radius:4px; background:#F9FAFB;">
            <span style="color:#6B7280;">Esperado:</span>
            <strong style="margin-left:4px;">Bs. <?= number_format($total_esperado_barrio, 2) ?></strong>
        </div>
        <div style="padding:6px; border-radius:4px; background:#F9FAFB;">
            <span style="color:#6B7280;">Diferencia:</span>
            <strong style="margin-left:4px; color:<?= $diferencia_total > 0 ? '#8A5A5A' : '#3E5C76' ?>;">
                Bs. <?= number_format(abs($diferencia_total), 2) ?>
            </strong>
        </div>
    </div>
</div>

<?php if($loteBarrio): ?>
<div class="card" id="lote-barrio-<?= (int)$loteBarrio['id'] ?>" style="margin-bottom:18px; font-size:12px;<?= ((int)($_GET['lote_id'] ?? 0) === (int)$loteBarrio['id'] || (int)($_GET['lote_barrio_id'] ?? 0) === (int)$loteBarrio['id']) ? 'background:#FFF7ED;' : '' ?>">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <strong>Lote de Barrio — <?= $loteBarrio['estado'] ?></strong>
            <div style="color:#6B7280; margin-top:2px;">
                <?= $loteBarrio['calles_completas'] ?>/<?= $loteBarrio['total_calles'] ?> calles •
                Esperado: Bs. <?= number_format($loteBarrio['monto_total_esperado'],2) ?> •
                Recolectado: Bs. <?= number_format($loteBarrio['monto_total_recolectado'],2) ?>
            </div>
        </div>
    </div>
    <?php if($loteBarrio['estado']==='Rechazado' && $loteBarrio['observaciones_gestor']): ?>
    <div style="margin-top:6px; font-size:11px; color:#8A5A5A;">
        Rechazado por Gestor: <?= htmlspecialchars($loteBarrio['observaciones_gestor']) ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
  @media (max-width: 768px) {
    .reportar-pago-grid { grid-template-columns: 1fr !important; }
  }
</style>
<div class="reportar-pago-grid" style="display:grid; grid-template-columns:1fr 320px; gap:20px;">
    <div style="display:flex; flex-direction:column; gap:20px;">

        <div class="card">
            <h3 style="margin-top:0; color:#111827;">📋 Lotes de Calle — <?= $meses_nombres[$mes] ?> <?= $anio ?></h3>

            <?php $numLotesBloqueado = $loteBarrio && $loteBarrio['estado'] === 'Enviado'; ?>
            <?php if(empty($lotes_calle)): ?>
            <div style="text-align:center; padding:40px; color:#9CA3AF;">
                No hay lotes de calle enviados por los Encargados de Calle para este periodo.
            </div>
            <?php else: ?>

            <?php 
            foreach($lotes_calle as $lc): 
                $lc_color = $estado_color[$lc['estado']] ?? '#6B7280';
                $porcentaje = $lc['total_casas'] > 0 ? round($lc['casas_pagadas']/$lc['total_casas']*100) : 0;
            ?>
            <div id="lote-calle-<?= (int)$lc['id'] ?>" style="border:1px solid #E5E7EB; border-radius:8px; padding:16px; margin-bottom:14px; background:<?= (int)($_GET['lote_id'] ?? 0) === (int)$lc['id'] ? '#FFF7ED' : '#fff' ?>;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                    <div>
                        <div style="font-size:15px; font-weight:700; color:#111827;">
                            <?= htmlspecialchars($lc['calle_nombre']) ?>
                        </div>
                        <div style="font-size:11px; color:#6B7280; margin-top:2px;">
                            Encargado: <?= htmlspecialchars($lc['encargado_nombre'] . ' ' . $lc['encargado_apellido']) ?>
                            <?php if($lc['fecha_envio']): ?> • <?= date('d/m/Y H:i', strtotime($lc['fecha_envio'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <span style="font-size:11px; color:#6B7280;"><?= $lc['estado'] ?></span>
                </div>

                <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:12px 0; font-size:12px; text-align:center;">
                    <div style="background:#F9FAFB; padding:8px; border-radius:6px;">
                        <div style="font-size:18px; font-weight:700;"><?= $lc['total_viviendas'] ?? $lc['total_casas'] ?></div>
                        <div style="color:#6B7280; font-size:11px;">🏠 Total viviendas</div>
                    </div>
                    <div style="background:#EEF2F6; padding:8px; border-radius:6px;">
                        <div style="font-size:18px; font-weight:700; color:#4A5568;"><?= $lc['viviendas_exentas'] ?? 0 ?></div>
                        <div style="color:#4A5568; font-size:11px;">🛡️ Exoneradas</div>
                    </div>
                    <div style="background:#E9EFF4; padding:8px; border-radius:6px;">
                        <div style="font-size:18px; font-weight:700; color:#33485C;"><?= $lc['casas_pagadas'] ?></div>
                        <div style="color:#33485C; font-size:11px;">✅ Pagaron</div>
                    </div>
                    <div style="background:#F4EDED; padding:8px; border-radius:6px;">
                        <?php 
                        $cobrables = ($lc['total_viviendas'] ?? $lc['total_casas']) - ($lc['viviendas_exentas'] ?? 0);
                        $pendientes = $cobrables - $lc['casas_pagadas'];
                        ?>
                        <div style="font-size:18px; font-weight:700; color:#7A4F4F;"><?= max(0, $pendientes) ?></div>
                        <div style="color:#7A4F4F; font-size:11px;">⏳ Sin pagar</div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; font-size:12px; margin-bottom:12px; padding:10px; background:#F9FAFB; border-radius:6px;">
                    <div>
                        <div style="color:#6B7280; font-size:10px; text-transform:uppercase; font-weight:600;">Monto esperado</div>
                        <div style="font-weight:700; color:#111827; margin-top:2px;">Bs. <?= number_format($lc['monto_esperado'],2) ?></div>
                    </div>
                    <div>
                        <div style="color:#6B7280; font-size:10px; text-transform:uppercase; font-weight:600;">Recolectado</div>
                        <div style="font-weight:700; color:<?= $lc['monto_recolectado'] < $lc['monto_esperado']?'#8A5A5A':'#3E5C76' ?>; margin-top:2px;">Bs. <?= number_format($lc['monto_recolectado'],2) ?></div>
                    </div>
                    <div>
                        <div style="color:#6B7280; font-size:10px; text-transform:uppercase; font-weight:600;">Diferencia</div>
                        <div style="font-weight:700; color:<?= $lc['monto_recolectado'] < $lc['monto_esperado']?'#8A5A5A':'#3E5C76' ?>; margin-top:2px;">
                            <?= $lc['monto_recolectado'] >= $lc['monto_esperado'] ? '✅ Completo' : 'Bs. '.number_format($lc['monto_esperado']-$lc['monto_recolectado'],2).' faltante' ?>
                        </div>
                    </div>
                </div>

                <?php if(!empty($lc['comprobante_lote'])): ?>
                <div style="margin-bottom:10px; font-size:12px;">
                    <span style="color:#6B7280;">Comprobante del lote:</span>
                    <a href="#" data-url="<?= htmlspecialchars($lc['comprobante_lote']) ?>" onclick="return openComprobanteModal(this)" style="color:#3E5C76; text-decoration:none; font-weight:600;">Ver captura</a>
                </div>
                <?php endif; ?>

                <?php if(!empty($lc['referencia_lote'])): ?>
                <div style="margin-bottom:10px; font-size:12px;">
                    <span style="color:#6B7280;">Referencia del lote:</span>
                    <strong style="color:#111827; background:#F3F4F6; padding:3px 8px; border-radius:4px;"><?= htmlspecialchars($lc['referencia_lote']) ?></strong>
                </div>
                <?php endif; ?>

                <?php if(!empty($lc['observaciones_calle'])): ?>
                <div style="background:#F9FAFB; padding:6px 10px; border-radius:4px; font-size:11px; color:#374151; margin-bottom:10px;">
                    <?= htmlspecialchars($lc['observaciones_calle']) ?>
                </div>
                <?php endif; ?>

                <?php if($lc['estado'] === 'Enviado'): ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button onclick="toggleDetalle(<?= $lc['id'] ?>)" style="background:#374151; color:white; border:none; padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer;">🔍 Ver Viviendas</button>
                    <form method="POST" action="router.php" onsubmit="return confirm('¿Aprobar el lote de <?= htmlspecialchars($lc['calle_nombre']) ?>?')">
                        <input type="hidden" name="form_type" value="aprobar_lote_calle">
                        <input type="hidden" name="lote_id" value="<?= $lc['id'] ?>">
                        <button type="submit" style="background:#3E5C76; color:white; border:none; padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer;">✅ Aprobar</button>
                    </form>
                    <button onclick="toggleRechazo(<?= $lc['id'] ?>)" style="background:#8A5A5A; color:white; border:none; padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer;">❌ Rechazar</button>
                </div>

                <div id="rechazo-<?= $lc['id'] ?>" style="display:none; margin-top:10px;">
                    <form method="POST" action="router.php" style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="hidden" name="form_type" value="rechazar_lote_calle">
                        <input type="hidden" name="lote_id" value="<?= $lc['id'] ?>">
                        <input type="text" name="motivo_rechazo" placeholder="Motivo del rechazo" required
                            style="flex:1; min-width:200px; padding:7px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:12px;">
                        <button type="submit" style="background:#8A5A5A; color:white; border:none; padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer;">Confirmar Rechazo</button>
                    </form>
                </div>

                <div id="detalle-<?= $lc['id'] ?>" style="display:none; margin-top:12px;">
                    <?php
                    $detalles_lc = [];
                    try {
                        $detStmt = $pdo->prepare(
                            "SELECT v.id as vivienda_id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.direccion, v.exento_cobro, v.estado_servicio,
                                   c.id as cobro_id, c.monto, c.estado, c.estado_verificacion, c.referencia_pago, c.comprobante_calle, c.fecha_emision
                             FROM viviendas v
                             LEFT JOIN cobros c ON c.vivienda_id = v.id AND c.mes = ? AND c.anio = ? AND c.estado != 'Anulado'
                             WHERE v.calle_id = ?
                             AND v.id NOT IN (SELECT vivienda_id FROM solicitudes_vivienda WHERE tipo='Baja' AND estado='Aprobado')
                             ORDER BY CAST(v.numero_casa AS UNSIGNED), v.numero_casa"
                        );
                        $detStmt->execute([$lc['mes'], $lc['anio'], $lc['calle_id']]);
                        $detalles_lc = $detStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (\Throwable $e) {}
                    ?>
                    <div style="border:1px solid #E5E7EB; border-radius:6px; overflow:hidden;">
                        <table style="width:100%; border-collapse:collapse; font-size:11px;">
                            <thead style="background:#F9FAFB;">
                                <tr>
                                    <th style="padding:8px; text-align:left;">Casa</th>
                                    <th style="padding:8px; text-align:left;">Propietario</th>
                                    <th style="padding:8px; text-align:left;">Dirección</th>
                                    <th style="padding:8px; text-align:center;">Estado</th>
                                    <th style="padding:8px;">Referencia</th>
                                    <th style="padding:8px;">Comprobante</th>
                                    <th style="padding:8px; text-align:right;">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($detalles_lc as $dl): ?>
                                <?php
                                    $pagado = $dl['estado'] === 'Pagado';
                                    $exenta = $dl['exento_cobro'] == 1;
                                    $suspendida = $dl['estado_servicio'] === 'Suspendido';
                                    $rowBg = $suspendida ? '#F9FAFB' : ($exenta ? '#EEF2F6' : ($pagado ? '#F4F6F8' : '#F7F2F2'));
                                ?>
                                <tr id="cobro-<?= (int)$dl['cobro_id'] ?>" style="border-top:1px solid #F3F4F6; background:<?= (int)($_GET['cobro_id'] ?? 0) === (int)$dl['cobro_id'] ? '#FFF4E5' : $rowBg ?>; opacity:<?= $suspendida?'0.6':'1' ?>;">
                                    <td style="padding:8px; font-weight:600; color:#111827;">#<?= htmlspecialchars($dl['numero_casa']) ?></td>
                                    <td style="padding:8px;">
                                        <?= htmlspecialchars($dl['propietario']) ?>
                                        <?php $identificacion = formato_identificacion($dl['cedula'] ?? '', $dl['cedula_tipo'] ?? 'V', $dl['otro_identificador'] ?? ''); ?>
                                        <?php if ($identificacion !== ''): ?><div style="font-size:9px; color:#6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                                    </td>
                                    <td style="padding:8px; color:#6B7280; font-size:10px;"><?= htmlspecialchars($dl['direccion'] ?? '—') ?></td>
                                    <td style="padding:8px; text-align:center;">
                                        <?php if($suspendida): ?>
                                            <span style="background:#F3F4F6; color:#6B7280; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600;">⛔ Suspendida</span>
                                        <?php elseif($exenta): ?>
                                            <span style="background:#E7EDF3; color:#3E4C5A; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600;">🛡️ Exonerada</span>
                                        <?php elseif($pagado): ?>
                                            <span style="background:#E9EFF4; color:#33485C; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600;">✅ Pagado</span>
                                        <?php else: ?>
                                            <span style="background:#F4EDED; color:#7A4F4F; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600;">⏳ Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px; font-weight:600; font-size:11px;"><?= htmlspecialchars($dl['referencia_pago'] ?? '—') ?></td>
                                    <td style="padding:8px; text-align:center;">
                                        <?php if(!empty($dl['comprobante_calle']) && preg_match('/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i', $dl['comprobante_calle'])): ?>
                                            <img src="<?= htmlspecialchars($dl['comprobante_calle']) ?>" alt="Comp" style="width:36px; height:36px; border-radius:4px; object-fit:cover; cursor:pointer; border:1px solid #E5E7EB;" onclick="openComprobanteModal(this)" data-url="<?= htmlspecialchars($dl['comprobante_calle']) ?>">
                                        <?php elseif(!empty($dl['comprobante_calle'])): ?>
                                            <a href="#" data-url="<?= htmlspecialchars($dl['comprobante_calle']) ?>" onclick="return openComprobanteModal(this)" style="color:#3E5C76; text-decoration:none;">📎 PDF</a>
                                        <?php else: ?>
                                            <span style="color:#9CA3AF;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px; text-align:right; font-weight:700; color:<?= $pagado?'#3E5C76':($exenta?'#4A5568':($suspendida?'#9CA3AF':'#8A5A5A')) ?>;">
                                        <?php if($suspendida): ?>
                                            <span style="color:#9CA3AF;">—</span>
                                        <?php elseif($exenta): ?>
                                            <span style="color:#9CA3AF;">Exenta</span>
                                        <?php else: ?>
                                            Bs. <?= number_format($dl['monto'] ?? 0,2) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($detalles_lc)): ?>
                                <tr><td colspan="7" style="padding:12px; text-align:center; color:#9CA3AF;">Sin viviendas registradas en esta calle</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif($lc['estado'] === 'Aprobado'): ?>
                <div style="font-size:12px; color:#374151; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <span>✅ Aprobado — pendiente de envío al Gestor</span>
                    <?php if(!$numLotesBloqueado): ?>
                    <span style="font-size:11px; color:#6B7280;">Usa el panel lateral para enviar</span>
                    <?php endif; ?>
                </div>
                <?php elseif($lc['estado'] === 'Rechazado'): ?>
                <div style="font-size:12px; color:#8A5A5A;">
                    ❌ Rechazado — <?= htmlspecialchars($lc['observaciones_barrio'] ?? 'Sin motivo') ?>
                </div>
                <?php else: ?>
                <div style="font-size:12px; color:#6B7280;">
                    ✏️ En borrador — el encargado de calle aún no ha enviado el lote.
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:20px;">
        <div class="card" style="border-top:3px solid #6B7280; position:sticky; top:20px;">
            <h3 style="margin-top:0; font-size:15px; color:#111827;">Lote de Barrio</h3>

            <?php
            // CORRECCIÓN PARA COMPATIBILIDAD ABSOLUTA (Reemplaza al array_filter / array_column)
            $lotesAprobados = [];
            $totalEsp = 0;
            $totalRec = 0;

            if (is_array($lotes_calle)) {
                foreach ($lotes_calle as $l) {
                    if ($l['estado'] === 'Aprobado' && empty($l['lote_barrio_id'])) {
                        $lotesAprobados[] = $l;
                        $totalEsp += (float)$l['monto_esperado'];
                        $totalRec += (float)$l['monto_recolectado'];
                    }
                }
            }
            
            $cantidadAprobados = count($lotesAprobados);
            ?>

            <div style="margin-bottom:12px; font-size:13px;">
                <div style="display:flex; justify-content:space-between; padding:3px 0;">
                    <span style="color:#6B7280;">Lotes aprobados:</span>
                    <strong style="color:#3E5C76;"><?= $cantidadAprobados ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; padding:3px 0;">
                    <span style="color:#6B7280;">Total calles:</span>
                    <strong><?= count($lotes_calle) ?></strong>
                </div>
                <hr style="border:none; border-top:1px solid #E5E7EB; margin:4px 0;">
                <div style="display:flex; justify-content:space-between; padding:3px 0;">
                    <span style="color:#6B7280;">Esperado:</span>
                    <strong>Bs. <?= number_format($totalEsp,2) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; padding:3px 0;">
                    <span style="color:#6B7280;">Recolectado:</span>
                    <strong style="color:#3E5C76;">Bs. <?= number_format($totalRec,2) ?></strong>
                </div>
                <?php if($totalRec < $totalEsp): ?>
                <div style="font-size:12px; color:#8A5A5A; padding:3px 0;">
                    Diferencia: Bs. <?= number_format($totalEsp - $totalRec,2) ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if($loteBarrio && !empty($loteBarrio['comprobante_lote'])): ?>
            <div style="margin-bottom:12px; font-size:12px;">
                <span style="color:#6B7280;">Comprobante enviado al Gestor:</span><br>
                <a href="#" data-url="<?= htmlspecialchars($loteBarrio['comprobante_lote']) ?>" onclick="return openComprobanteModal(this)" style="color:#3E5C76; text-decoration:none; font-weight:600;">Ver captura</a>
            </div>
            <?php endif; ?>

            <?php if(!$numLotesBloqueado): ?>
            <?php if($cantidadAprobados > 0): ?>
            <form method="POST" action="router.php" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px;">
                <input type="hidden" name="form_type" value="enviar_lote_barrio">
                <input type="hidden" name="periodo_mes" value="<?= $mes ?>">
                <input type="hidden" name="periodo_anio" value="<?= $anio ?>">
                <div style="padding:10px; background:#F9FAFB; border-radius:6px; border:1px dashed #D1D5DB;">
                    <label style="font-size:11px; color:#374151; font-weight:600; display:block; margin-bottom:4px;">
                        Comprobante de pago (captura de transferencia):
                    </label>
                    <input type="file" name="comprobante_lote_barrio" accept="image/*,.pdf" style="font-size:11px; width:100%;">
                </div>
                <textarea name="observaciones_lote_barrio" placeholder="Observaciones" rows="2"
                    style="padding:6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;"></textarea>
                <button type="submit" style="background:#374151; color:white; border:none; padding:10px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;"
                    onclick="return confirm('¿Enviar el lote de barrio al Gestor?')">
                    <?= ($loteBarrio && $loteBarrio['estado']==='Rechazado') ? 'Reenviar al Gestor' : 'Enviar al Gestor' ?>
                </button>
            </form>
            <?php else: ?>
            <button disabled style="width:100%; padding:10px; background:#F3F4F6; color:#9CA3AF; border:none; border-radius:6px; font-size:12px;">
                Aprueba lotes de calle primero
            </button>
            <?php endif; ?>
            <?php elseif($loteBarrio && $loteBarrio['estado']==='Aprobado'): ?>
            <div style="font-size:12px; color:#3E5C76;">✅ Lote aprobado por el Gestor</div>
            <?php else: ?>
            <div style="font-size:12px; color:#6B7280;">📤 Enviado al Gestor — esperando aprobación</div>
            <?php endif; ?>

            <div style="margin-top:10px; font-size:10px; color:#9CA3AF;">
                Solo se consolidan lotes de calle en estado Aprobado.
            </div>
        </div>
    </div>
</div>

<script>
function toggleDetalle(id) {
    const el = document.getElementById('detalle-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function toggleRechazo(id) {
    const el = document.getElementById('rechazo-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>