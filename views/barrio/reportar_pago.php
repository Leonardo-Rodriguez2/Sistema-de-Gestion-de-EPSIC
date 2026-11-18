<?php
// views/barrio/reportar_pago.php — Verificación y registro de pagos del barrio
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
$anioMaximo = (int)date('Y') + 1;
if ($mes !== null && ($mes < 1 || $mes > 12)) $mes = null;
if ($anio !== null && ($anio < 2000 || $anio > $anioMaximo)) $anio = null;
$lote_id = (int)($_GET['lote_id'] ?? 0);
$lote_barrio_id = (int)($_GET['lote_barrio_id'] ?? 0);
$modoMarcar = ($_GET['modo'] ?? '') === 'marcar';

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

// En "Marcar Pagos" el periodo natural es el mes actual.
if ($modoMarcar && ($mes === null || $anio === null)) {
    $mes = (int)date('n');
    $anio = (int)date('Y');
}

// En Verificación de Pagos, si hay pagos recién registrados en el mes actual,
// abrir ese periodo para que aparezcan de inmediato en la bandeja.
if (!$modoMarcar && $mes === null && $anio === null && $barrio_id > 0) {
    try {
        $pendStmt = $pdo->prepare(
            "SELECT 1 FROM cobros co
             JOIN viviendas v ON v.id = co.vivienda_id
             JOIN calles c ON c.id = v.calle_id
             WHERE c.barrio_id = ? AND co.mes = ? AND co.anio = ?
               AND co.estado = 'Pagado'
               AND (co.estado_verificacion = 'Pendiente' OR co.estado_verificacion IS NULL)
             LIMIT 1"
        );
        $pendStmt->execute([$barrio_id, (int)date('n'), (int)date('Y')]);
        if ($pendStmt->fetchColumn()) {
            $mes = (int)date('n');
            $anio = (int)date('Y');
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
                (SELECT COUNT(*) FROM viviendas v WHERE v.calle_id = lc.calle_id AND v.estado_servicio = 'Activo') as total_viviendas,
                (SELECT COUNT(*) FROM viviendas v WHERE v.calle_id = lc.calle_id AND v.estado_servicio = 'Activo' AND v.exento_cobro = 1) as viviendas_exentas
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
$config = ['cuota_mensual' => 0.00, 'multa_renovacion' => 0.00, 'fecha_limite_pago' => date('Y-m-t')];
try {
    $cuotaStmt = $pdo->prepare("SELECT cuota_mensual, multa_renovacion, fecha_limite_pago FROM configuraciones_barrio WHERE barrio_id=?");
    $cuotaStmt->execute([$barrio_id]);
    if ($cfg = $cuotaStmt->fetch(PDO::FETCH_ASSOC)) {
        $config = $cfg;
    }
} catch (\Throwable $e) {}

$meses_nombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$pagosPorVerificar = [];
if ($barrio_id > 0) {
    $pagosVerificarStmt = $pdo->prepare(
        "SELECT co.id, co.monto, co.mes, co.anio, co.referencia_pago, co.fecha_emision,
                co.fecha_confirmacion_barrio, co.fecha_confirmacion_calle,
                co.comprobante_barrio, co.comprobante_calle,
                COALESCE(NULLIF(co.comprobante_barrio, ''), co.comprobante_calle) AS comprobante_pago,
                v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador,
                v.numero_casa, v.direccion, v.telefono,
                c.nombre AS calle_nombre
         FROM cobros co
         JOIN viviendas v ON v.id = co.vivienda_id
         JOIN calles c ON c.id = v.calle_id
         WHERE c.barrio_id = ? AND co.mes = ? AND co.anio = ?
           AND co.estado = 'Pagado'
           AND (co.estado_verificacion = 'Pendiente' OR co.estado_verificacion IS NULL)
         ORDER BY co.id DESC"
    );
    $pagosVerificarStmt->execute([$barrio_id, $mes, $anio]);
    $pagosPorVerificar = $pagosVerificarStmt->fetchAll(PDO::FETCH_ASSOC);
}

// 7. Viviendas del barrio y su estado de pago del periodo (Marcar Pagos)
$viviendasMarcar = [];
$callesBarrio = [];
$errorMarcar = null;
$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_calle = (int)($_GET['calle_id'] ?? 0);
$f_estado_pago = (string)($_GET['estado_pago'] ?? 'Todos');
$estadosPagoPermitidos = ['Todos', 'Pendiente', 'Pagado', 'PorVerificar', 'Exonerada'];
if (!in_array($f_estado_pago, $estadosPagoPermitidos, true)) $f_estado_pago = 'Todos';

if ($modoMarcar && $barrio_id > 0) {
    try {
        $callesStmt = $pdo->prepare("SELECT id, nombre FROM calles WHERE barrio_id = ? ORDER BY nombre");
        $callesStmt->execute([$barrio_id]);
        $callesBarrio = $callesStmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlMarcar = "SELECT v.id, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador,
                             v.numero_casa, v.direccion, v.telefono, v.exento_cobro, v.estado_servicio,
                             c.id AS calle_id, c.nombre AS calle_nombre,
                             co.id AS cobro_id, co.monto, co.estado AS cobro_estado,
                             co.estado_verificacion, co.referencia_pago, co.fecha_emision,
                             COALESCE(NULLIF(co.comprobante_barrio, ''), co.comprobante_calle) AS comprobante_pago
                      FROM viviendas v
                      JOIN calles c ON c.id = v.calle_id
                      LEFT JOIN cobros co ON co.id = (
                          SELECT c2.id FROM cobros c2
                          WHERE c2.vivienda_id = v.id AND c2.mes = ? AND c2.anio = ? AND c2.estado != 'Anulado'
                          ORDER BY c2.id DESC LIMIT 1
                      )
                      WHERE c.barrio_id = ? AND v.estado_servicio = 'Activo'";
        $paramsMarcar = [$mes, $anio, $barrio_id];

        if ($f_calle > 0) {
            $sqlMarcar .= " AND c.id = ?";
            $paramsMarcar[] = $f_calle;
        }
        if ($f_buscar !== '') {
            $sqlMarcar .= " AND (v.propietario LIKE ? OR v.cedula LIKE ? OR v.otro_identificador LIKE ?
                                 OR v.numero_casa LIKE ? OR c.nombre LIKE ?)";
            $q = '%' . $f_buscar . '%';
            array_push($paramsMarcar, $q, $q, $q, $q, $q);
        }
        if ($f_estado_pago === 'Pendiente') {
            $sqlMarcar .= " AND COALESCE(v.exento_cobro,0) = 0 AND (co.id IS NULL OR co.estado != 'Pagado')";
        } elseif ($f_estado_pago === 'Pagado') {
            $sqlMarcar .= " AND co.estado = 'Pagado'";
        } elseif ($f_estado_pago === 'PorVerificar') {
            $sqlMarcar .= " AND co.estado = 'Pagado' AND (co.estado_verificacion = 'Pendiente' OR co.estado_verificacion IS NULL)";
        } elseif ($f_estado_pago === 'Exonerada') {
            $sqlMarcar .= " AND COALESCE(v.exento_cobro,0) = 1";
        }

        $sqlMarcar .= " ORDER BY c.nombre ASC, CAST(v.numero_casa AS UNSIGNED) ASC, v.numero_casa ASC";
        $stmtMarcar = $pdo->prepare($sqlMarcar);
        $stmtMarcar->execute($paramsMarcar);
        $viviendasMarcar = $stmtMarcar->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        error_log('Error al cargar viviendas para marcar pagos del barrio: ' . $e->getMessage());
        $errorMarcar = 'No fue posible cargar las viviendas. Actualiza la página o inténtalo más tarde.';
    }
} elseif ($modoMarcar) {
    $errorMarcar = 'Tu usuario no tiene un barrio asignado para registrar pagos.';
}

$totalMarcar = count($viviendasMarcar);
$cuotaMarcar = (float)($config['cuota_mensual'] ?: 10.00);

$title          = "Lotes de Calle - EcoCusco";
$header_title   = "Verificación de Pagos";
$header_subtitle = "Barrio: " . htmlspecialchars($barrio['nombre']) . " — {$meses_nombres[$mes]} {$anio}";
if ($modoMarcar) {
    $title = "Marcar Pagos - EcoCusco";
    $header_title = "Marcar Pagos";
    $header_subtitle = "";
}

$estado_color = ['Borrador'=>'#6B7280','Enviado'=>'#4C6E91','Aprobado'=>'#4C6E91','Rechazado'=>'#8A5A5A'];

ob_start();
?>
<?php if ($modoMarcar): ?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? $errorMarcar); ?>
    <?php
    $mpReturnParams = [
        'page' => 'reportar_pago',
        'modo' => 'marcar',
        'mes' => $mes,
        'anio' => $anio,
    ];
    if ($f_buscar !== '') $mpReturnParams['search'] = $f_buscar;
    if ($f_calle > 0) $mpReturnParams['calle_id'] = $f_calle;
    if ($f_estado_pago !== 'Todos') $mpReturnParams['estado_pago'] = $f_estado_pago;
    $mpAction = 'router.php?' . http_build_query($mpReturnParams);
    $mpLimpiar = 'router.php?page=reportar_pago&modo=marcar';
    $hayFiltrosMarcar = ($f_buscar !== '' || $f_calle > 0 || $f_estado_pago !== 'Todos');
    $anioMin = min($anio, (int)date('Y') - 3);
    $anioMax = max($anio, (int)date('Y') + 1);
    ?>
    <style>
        .mp-filters {
            display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px;
            padding:16px; margin-bottom:16px; background:#fff;
            border:1px solid #E5E7EB; border-radius:12px;
        }
        .mp-field { display:flex; flex-direction:column; gap:5px; flex:1 1 135px; min-width:125px; }
        .mp-field-search { flex:2 1 240px; }
        .mp-field label { color:#6B7280; font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
        .mp-field input, .mp-field select {
            width:100%; box-sizing:border-box; padding:9px 11px; border:1px solid #D1D5DB;
            border-radius:8px; background:#fff; color:#111827; font-size:12px; outline:none;
        }
        .mp-field input:focus, .mp-field select:focus { border-color:#3E5C76; box-shadow:0 0 0 3px rgba(62,92,118,.10); }
        .mp-filter-actions { display:flex; align-items:center; gap:8px; flex:0 0 auto; }
        .mp-btn {
            border:1px solid #D1D5DB; border-radius:8px; padding:9px 13px;
            background:#fff; color:#374151; font-size:12px; font-weight:700;
            cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;
        }
        .mp-btn:hover { background:#F9FAFB; }
        .mp-btn-primary { background:#3E5C76; border-color:#3E5C76; color:#fff; }
        .mp-btn-primary:hover { background:#33485C; }

        .mp-list-card { background:#fff; border:1px solid #E5E7EB; border-radius:12px; overflow:hidden; }
        .mp-list-head {
            display:flex; justify-content:space-between; align-items:center;
            gap:12px; flex-wrap:wrap; padding:16px 18px; border-bottom:1px solid #F1F3F5;
        }
        .mp-list-head strong { display:block; color:#111827; font-size:14px; }
        .mp-list-head span { display:block; margin-top:3px; color:#6B7280; font-size:11px; }
        .mp-list-legend { display:flex; align-items:center; gap:8px; flex-wrap:wrap; color:#6B7280; font-size:10px; }
        .mp-list-card .table-pagination {
            margin:0; padding:13px 16px; border-top:1px solid #E8EEF2;
            background:#F8FAFC; color:#526171; font-size:11px;
        }
        .mp-list-card .table-page-button {
            min-width:88px; padding:8px 13px; border:1px solid #CBD7DE;
            border-radius:7px; background:#fff; color:#3E5C76; font-size:11px;
            font-weight:700; transition:background .15s, color .15s, border-color .15s;
        }
        .mp-list-card .table-page-button:hover:not(:disabled) {
            border-color:#3E5C76; background:#3E5C76; color:#fff;
        }
        .mp-list-card .table-page-button:disabled { opacity:.45; }
        .mp-list-card .table-page-indicator { color:#263746; font-size:11px; }
        .mp-table { width:100%; min-width:760px; border-collapse:collapse; font-size:12px; }
        .mp-table thead tr { background:#F9FAFB; border-bottom:1px solid #E5E7EB; color:#374151; }
        .mp-table th { padding:11px 12px; text-align:left; font-size:10px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
        .mp-table td { padding:12px; border-top:1px solid #F3F4F6; vertical-align:middle; }
        .mp-table tbody tr:hover { background:#FCFCFD; }
        .mp-house-number { color:#111827; font-size:13px; font-weight:800; }
        .mp-table small { display:block; margin-top:3px; color:#9CA3AF; font-size:10px; }
        .mp-owner { color:#111827; font-weight:700; }
        .mp-amount { margin-top:5px; color:#4B5563; font-size:10px; }
        .mp-badge {
            display:inline-flex; align-items:center; gap:4px; padding:4px 9px;
            border-radius:999px; font-size:9px; font-weight:800;
            letter-spacing:.03em; text-transform:uppercase;
        }
        .mp-badge-pending { background:#FBF0E3; color:#9A5B18; }
        .mp-badge-paid { background:#E8F1EA; color:#2F6B45; }
        .mp-badge-exempt { background:#EEF2F6; color:#4A5568; }
        .mp-substatus { display:block; margin-top:4px; color:#6B7280; font-size:10px; }
        .mp-substatus.is-ok { color:#2F6B45; }
        .mp-substatus.is-rejected { color:#8A5A5A; }
        .mp-ref { color:#374151; font-weight:600; overflow-wrap:anywhere; }
        .mp-action-cell { text-align:right; white-space:nowrap; }
        .mp-link { color:#3E5C76; font-size:11px; font-weight:700; text-decoration:none; }
        .mp-link:hover { text-decoration:underline; }
        .mp-empty { padding:48px 20px; text-align:center; }
        .mp-empty-icon { font-size:38px; margin-bottom:10px; }
        .mp-empty strong { display:block; color:#111827; font-size:15px; }
        .mp-empty p { margin:7px 0 16px; color:#6B7280; font-size:12px; }

        .mp-modal {
            display:none; position:fixed; inset:0; z-index:3000; align-items:center;
            justify-content:center; padding:18px; background:rgba(17,24,39,.62);
        }
        .mp-modal.is-open { display:flex; }
        .mp-modal-panel {
            width:min(100%,560px); max-height:calc(100vh - 36px); overflow:auto;
            background:#fff; border-radius:14px; box-shadow:0 30px 70px rgba(17,24,39,.28);
        }
        .mp-modal-head {
            display:flex; justify-content:space-between; align-items:flex-start;
            gap:14px; padding:20px 22px; border-bottom:1px solid #EEF0F2;
        }
        .mp-modal-kicker { color:#6B7280; font-size:9px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .mp-modal-head h3 { margin:3px 0 3px; color:#111827; font-size:18px; }
        .mp-modal-head p { margin:0; color:#6B7280; font-size:11px; }
        .mp-modal-close {
            flex:0 0 auto; width:32px; height:32px; border:0; border-radius:8px;
            background:#F3F4F6; color:#374151; font-size:18px; cursor:pointer;
        }
        .mp-modal-close:hover { background:#E5E7EB; }
        .mp-modal-body { padding:18px 22px 22px; }
        .mp-house-box {
            display:flex; align-items:center; gap:12px; padding:12px 14px;
            margin-bottom:16px; background:#F8FAFB; border:1px solid #E8EDF1; border-radius:10px;
        }
        .mp-house-icon {
            flex:0 0 auto; width:44px; height:44px; border-radius:10px; background:#3E5C76;
            color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;
        }
        .mp-house-box strong { display:block; color:#111827; font-size:13px; }
        .mp-house-box span { display:block; margin-top:3px; color:#6B7280; font-size:11px; }
        .mp-pay-summary {
            display:flex; justify-content:space-between; align-items:center; gap:10px;
            margin-bottom:15px; padding:13px 14px; border-radius:10px;
            background:#EEF4F7; border-left:3px solid #3E5C76;
        }
        .mp-pay-summary span { color:#4B5563; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .mp-pay-summary strong { color:#3E5C76; font-size:20px; }
        .mp-form-group { margin-bottom:15px; }
        .mp-form-group label { display:block; margin-bottom:6px; color:#374151; font-size:11px; font-weight:700; }
        .mp-form-group .mp-hint { margin-left:4px; color:#9CA3AF; font-weight:500; }
        .mp-input {
            width:100%; box-sizing:border-box; padding:11px 12px; border:1px solid #D1D5DB;
            border-radius:8px; color:#111827; font-size:13px; outline:none;
        }
        .mp-input:focus { border-color:#3E5C76; box-shadow:0 0 0 3px rgba(62,92,118,.10); }
        .mp-upload {
            display:block; padding:16px; border:1.5px dashed #CBD5E1; border-radius:10px;
            background:#FBFCFD; text-align:center; cursor:pointer; transition:border-color .15s, background .15s;
        }
        .mp-upload:hover { border-color:#3E5C76; background:#F8FAFB; }
        .mp-upload strong { display:block; color:#374151; font-size:12px; }
        .mp-upload small { display:block; margin-top:4px; color:#9CA3AF; font-size:10px; }
        .mp-file-name { display:none; margin-top:7px; color:#3E5C76; font-size:11px; font-weight:700; overflow-wrap:anywhere; }
        .mp-file-name.has-file { display:block; }
        .mp-modal-actions { display:flex; justify-content:flex-end; gap:9px; margin-top:20px; }

        @media(max-width:640px) {
            .mp-filter-actions { width:100%; }
            .mp-filter-actions .mp-btn { flex:1 1 auto; }
            .mp-modal-actions { flex-direction:column-reverse; }
            .mp-modal-actions .mp-btn { width:100%; }
        }
    </style>

    <form method="GET" action="router.php" class="mp-filters">
        <input type="hidden" name="page" value="reportar_pago">
        <input type="hidden" name="modo" value="marcar">
        <div class="mp-field mp-field-search">
            <label for="mpBuscar">Buscar vivienda</label>
            <input type="text" id="mpBuscar" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, identificación, casa o calle">
        </div>
        <div class="mp-field">
            <label for="mpCalleFiltro">Calle</label>
            <select id="mpCalleFiltro" name="calle_id">
                <option value="0">Todas las calles</option>
                <?php foreach ($callesBarrio as $cb): ?>
                    <option value="<?= (int)$cb['id'] ?>" <?= $f_calle === (int)$cb['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cb['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mp-field">
            <label for="mpEstadoFiltro">Estado</label>
            <select id="mpEstadoFiltro" name="estado_pago">
                <option value="Todos" <?= $f_estado_pago === 'Todos' ? 'selected' : '' ?>>Todos</option>
                <option value="Pendiente" <?= $f_estado_pago === 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
                <option value="Pagado" <?= $f_estado_pago === 'Pagado' ? 'selected' : '' ?>>Pagadas</option>
                <option value="PorVerificar" <?= $f_estado_pago === 'PorVerificar' ? 'selected' : '' ?>>Por verificar</option>
                <option value="Exonerada" <?= $f_estado_pago === 'Exonerada' ? 'selected' : '' ?>>Exoneradas</option>
            </select>
        </div>
        <div class="mp-field">
            <label for="mpMesFiltro">Mes</label>
            <select id="mpMesFiltro" name="mes">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= $meses_nombres[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="mp-field">
            <label for="mpAnioFiltro">Año</label>
            <select id="mpAnioFiltro" name="anio">
                <?php for ($a = $anioMin; $a <= $anioMax; $a++): ?>
                    <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="mp-filter-actions">
            <button type="submit" class="mp-btn mp-btn-primary">Filtrar</button>
            <a href="<?= htmlspecialchars($mpLimpiar) ?>" class="mp-btn">Limpiar</a>
        </div>
    </form>

    <div class="mp-list-card">
        <div class="mp-list-head">
            <div>
                <strong>Viviendas del barrio</strong>
                <span><?= $totalMarcar ?> resultado<?= $totalMarcar === 1 ? '' : 's' ?> · Cuota del barrio: Bs. <?= number_format($cuotaMarcar, 2) ?></span>
            </div>
            <div class="mp-list-legend">
                <span>💡 Usa “Registrar pago” para enviar el pago a verificación.</span>
            </div>
        </div>

        <?php if (empty($viviendasMarcar)): ?>
            <div class="mp-empty">
                <div class="mp-empty-icon">🏘️</div>
                <strong>No se encontraron viviendas</strong>
                <p><?= $hayFiltrosMarcar ? 'Prueba con otros filtros o cambia el periodo seleccionado.' : 'Este barrio aún no tiene viviendas activas registradas.' ?></p>
                <?php if ($hayFiltrosMarcar): ?>
                    <a href="<?= htmlspecialchars($mpLimpiar) ?>" class="mp-btn">Limpiar filtros</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="mp-table" data-page-size="10" data-no-tools="true">
                    <thead>
                        <tr>
                            <th>Casa</th>
                            <th>Propietario</th>
                            <th>Calle</th>
                            <th>Estado del pago</th>
                            <th>Referencia</th>
                            <th style="text-align:right;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viviendasMarcar as $v): ?>
                            <?php
                            $esExenta = ((int)($v['exento_cobro'] ?? 0) === 1);
                            $esPagado = (($v['cobro_estado'] ?? '') === 'Pagado');
                            $estadoVerificacion = (string)($v['estado_verificacion'] ?? '');
                            $estaPorVerificar = $esPagado && ($estadoVerificacion === 'Pendiente' || $estadoVerificacion === '');
                            $comprobantePago = trim((string)($v['comprobante_pago'] ?? ''));
                            $montoPago = ($v['monto'] !== null && $v['monto'] !== '') ? (float)$v['monto'] : $cuotaMarcar;
                            $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? '');
                            $casaNumero = trim((string)($v['numero_casa'] ?? '')) !== '' ? $v['numero_casa'] : 'S/N';
                            ?>
                            <tr>
                                <td>
                                    <span class="mp-house-number">#<?= htmlspecialchars($casaNumero) ?></span>
                                    <?php if (!empty($v['direccion'])): ?>
                                        <small><?= htmlspecialchars($v['direccion']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="mp-owner"><?= htmlspecialchars($v['propietario']) ?></span>
                                    <?php if ($identificacion !== ''): ?>
                                        <small>ID: <?= htmlspecialchars($identificacion) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v['calle_nombre']) ?></td>
                                <td>
                                    <?php if ($esExenta): ?>
                                        <span class="mp-badge mp-badge-exempt">Exonerada</span>
                                    <?php elseif ($esPagado): ?>
                                        <span class="mp-badge mp-badge-paid">Pagado</span>
                                        <span class="mp-substatus <?= $estadoVerificacion === 'Verificado' ? 'is-ok' : '' ?>">
                                            <?= $estadoVerificacion === 'Verificado' ? 'Verificado' : ($estadoVerificacion === 'Rechazado' ? 'Rechazado' : 'Por verificar') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="mp-badge mp-badge-pending">Pendiente</span>
                                    <?php endif; ?>
                                    <div class="mp-amount">Bs. <?= number_format($montoPago, 2) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($v['referencia_pago'])): ?>
                                        <span class="mp-ref"><?= htmlspecialchars($v['referencia_pago']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mp-action-cell">
                                    <?php if (!$esExenta && !$esPagado): ?>
                                        <button
                                            type="button"
                                            class="mp-btn mp-btn-primary js-marcar-pago"
                                            data-vivienda-id="<?= (int)$v['id'] ?>"
                                            data-propietario="<?= htmlspecialchars($v['propietario'], ENT_QUOTES) ?>"
                                            data-casa="<?= htmlspecialchars((string)$casaNumero, ENT_QUOTES) ?>"
                                            data-calle="<?= htmlspecialchars($v['calle_nombre'], ENT_QUOTES) ?>"
                                            data-monto="<?= htmlspecialchars(number_format($montoPago, 2), ENT_QUOTES) ?>"
                                            data-periodo="<?= htmlspecialchars($meses_nombres[$mes] . ' ' . $anio, ENT_QUOTES) ?>"
                                        >Registrar pago</button>
                                    <?php elseif ($esPagado && $comprobantePago !== ''): ?>
                                        <a href="#" class="mp-link" data-url="<?= htmlspecialchars($comprobantePago) ?>" onclick="return openComprobanteModal(this)">Ver comprobante</a>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div id="mpPagoModal" class="mp-modal" role="dialog" aria-modal="true" aria-labelledby="mpPagoTitulo">
        <div class="mp-modal-panel">
            <div class="mp-modal-head">
                <div>
                    <span class="mp-modal-kicker">Registrar pago</span>
                    <h3 id="mpPagoTitulo">Nuevo pago de vivienda</h3>
                    <p>Completa la referencia y adjunta el comprobante si lo tienes.</p>
                </div>
                <button type="button" class="mp-modal-close" id="mpCerrarModal" aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars($mpAction) ?>" enctype="multipart/form-data" id="mpPagoForm">
                <div class="mp-modal-body">
                    <input type="hidden" name="page" value="reportar_pago">
                    <input type="hidden" name="action" value="marcar_pago_barrio">
                    <input type="hidden" name="vivienda_id" id="mpViviendaId" value="">
                    <input type="hidden" name="mes" value="<?= (int)$mes ?>">
                    <input type="hidden" name="anio" value="<?= (int)$anio ?>">

                    <div class="mp-house-box">
                        <div class="mp-house-icon" id="mpCasaNum">#</div>
                        <div>
                            <strong id="mpPropietario">—</strong>
                            <span id="mpDetalle">—</span>
                        </div>
                    </div>

                    <div class="mp-pay-summary">
                        <span>Monto a registrar</span>
                        <strong id="mpMonto">Bs. 0.00</strong>
                    </div>

                    <div class="mp-form-group">
                        <label for="mpReferencia">Referencia de pago <span class="mp-hint">(obligatoria)</span></label>
                        <input type="text" class="mp-input" id="mpReferencia" name="referencia_pago" maxlength="150" placeholder="Ej. transferencia, depósito o recibo" required autocomplete="off">
                    </div>

                    <div class="mp-form-group">
                        <label for="mpComprobante">Comprobante <span class="mp-hint">(opcional)</span></label>
                        <label class="mp-upload" for="mpComprobante">
                            <strong id="mpUploadTitle">Seleccionar imagen o PDF</strong>
                            <small>JPG, PNG, WEBP o PDF · máximo 2 MB</small>
                            <span class="mp-file-name" id="mpFileName"></span>
                        </label>
                        <input type="file" id="mpComprobante" name="comprobante_pago" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" style="display:none;">
                    </div>

                    <div class="mp-modal-actions">
                        <button type="button" class="mp-btn" id="mpCancelarModal">Cancelar</button>
                        <button type="submit" class="mp-btn mp-btn-primary" id="mpSubmitPago">Confirmar pago</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var modal = document.getElementById('mpPagoModal');
        if (!modal) return;

        var form = document.getElementById('mpPagoForm');
        var viviendaInput = document.getElementById('mpViviendaId');
        var casaNum = document.getElementById('mpCasaNum');
        var propietario = document.getElementById('mpPropietario');
        var detalle = document.getElementById('mpDetalle');
        var monto = document.getElementById('mpMonto');
        var submitPago = document.getElementById('mpSubmitPago');
        var referencia = document.getElementById('mpReferencia');
        var fileInput = document.getElementById('mpComprobante');
        var fileName = document.getElementById('mpFileName');
        var uploadTitle = document.getElementById('mpUploadTitle');

        function resetUpload() {
            if (!fileName || !uploadTitle) return;
            fileName.textContent = '';
            fileName.classList.remove('has-file');
            uploadTitle.textContent = 'Seleccionar imagen o PDF';
        }

        function closeModal() {
            modal.classList.remove('is-open');
            document.body.classList.remove('epsic-modal-open');
        }

        document.querySelectorAll('.js-marcar-pago').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.reset();
                resetUpload();

                viviendaInput.value = btn.dataset.viviendaId || '';
                casaNum.textContent = '#' + (btn.dataset.casa || 'S/N');
                propietario.textContent = btn.dataset.propietario || '—';
                detalle.textContent = (btn.dataset.calle || '—') + ' · ' + (btn.dataset.periodo || '—');
                monto.textContent = 'Bs. ' + (btn.dataset.monto || '0.00');
                submitPago.textContent = 'Confirmar pago · Bs. ' + (btn.dataset.monto || '0.00');

                modal.classList.add('is-open');
                document.body.classList.add('epsic-modal-open');
                window.setTimeout(function () { referencia.focus(); }, 60);
            });
        });

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files && this.files.length) {
                    fileName.textContent = this.files[0].name;
                    fileName.classList.add('has-file');
                    uploadTitle.textContent = 'Comprobante seleccionado';
                } else {
                    resetUpload();
                }
            });
        }

        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });
        document.getElementById('mpCerrarModal').addEventListener('click', closeModal);
        document.getElementById('mpCancelarModal').addEventListener('click', closeModal);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
    })();
    </script>
<?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    return;
endif;
?>
<style>
    .pv-modal {
        display:none; position:fixed; inset:0; z-index:3000; align-items:center;
        justify-content:center; padding:18px; background:rgba(17,24,39,.66);
    }
    .pv-modal.is-open { display:flex; }
    .pv-modal [hidden] { display:none !important; }
    .pv-panel {
        display:flex; flex-direction:column; width:min(100%,760px);
        max-height:calc(100vh - 36px); overflow:hidden; background:#fff;
        border:1px solid #E5E7EB; border-radius:14px; box-shadow:0 28px 80px rgba(17,24,39,.3);
    }
    .pv-head {
        display:flex; justify-content:space-between; align-items:flex-start; gap:16px;
        padding:19px 22px; border-bottom:1px solid #EEF0F2;
    }
    .pv-kicker { color:#718096; font-size:9px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
    .pv-head h3 { margin:3px 0 4px; color:#17212B; font-size:18px; }
    .pv-head p { margin:0; color:#6B7280; font-size:11px; }
    .pv-close {
        flex:0 0 auto; width:34px; height:34px; border:0; border-radius:8px;
        background:#F3F4F6; color:#374151; font-size:20px; cursor:pointer;
    }
    .pv-close:hover { background:#E5E7EB; }
    .pv-body { overflow:auto; padding:18px 22px 22px; }
    .pv-summary {
        display:flex; justify-content:space-between; align-items:center; gap:12px;
        margin-bottom:15px; padding:14px 16px; border:1px solid #DCE7ED;
        border-left:4px solid #3E5C76; border-radius:10px; background:#F3F7F9;
    }
    .pv-summary span { display:block; color:#64748B; font-size:9px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .pv-summary strong { display:block; margin-top:3px; color:#2F5269; font-size:22px; }
    .pv-status { padding:6px 10px; border-radius:999px; background:#FFF3E5; color:#9A5B18; font-size:9px; font-weight:800; text-transform:uppercase; }
    .pv-section-title { margin:0 0 9px; color:#374151; font-size:11px; font-weight:800; }
    .pv-details {
        display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px;
        margin-bottom:16px;
    }
    .pv-detail { min-width:0; padding:10px 12px; border:1px solid #EDF0F2; border-radius:8px; background:#fff; }
    .pv-detail span { display:block; margin-bottom:3px; color:#8A94A2; font-size:9px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .pv-detail strong { display:block; color:#263746; font-size:12px; font-weight:700; overflow-wrap:anywhere; }
    .pv-receipt { padding:13px; border:1px solid #E8EDF1; border-radius:10px; background:#FAFBFC; }
    .pv-receipt-image {
        display:block; width:100%; max-height:340px; margin-top:10px; border-radius:7px;
        background:#EEF2F6; object-fit:contain; cursor:zoom-in;
    }
    .pv-receipt-link {
        display:inline-flex; align-items:center; margin-top:8px; padding:8px 11px;
        border:1px solid #CAD8E0; border-radius:7px; color:#3E5C76;
        background:#fff; font-size:11px; font-weight:700; text-decoration:none;
    }
    .pv-receipt-empty { color:#7B8794; font-size:11px; }
    .pv-reject-box {
        display:none; margin-top:14px; padding:13px; border:1px solid #E8D8D8;
        border-radius:9px; background:#FBF6F6;
    }
    .pv-reject-box.is-open { display:block; }
    .pv-reject-box label { display:block; margin-bottom:6px; color:#7A4F4F; font-size:11px; font-weight:700; }
    .pv-reject-box textarea {
        display:block; width:100%; min-height:76px; box-sizing:border-box; padding:9px 10px;
        border:1px solid #D8C6C6; border-radius:7px; background:#fff; color:#263746;
        font:inherit; font-size:12px; resize:vertical;
    }
    .pv-actions {
        display:flex; justify-content:flex-end; align-items:center; gap:8px;
        margin-top:16px; padding-top:14px; border-top:1px solid #EEF0F2;
    }
    .pv-action {
        min-height:36px; padding:8px 13px; border:1px solid #D1D5DB; border-radius:8px;
        background:#fff; color:#374151; font-size:11px; font-weight:700; cursor:pointer;
    }
    .pv-action-verify { border-color:#3E5C76; background:#3E5C76; color:#fff; }
    .pv-action-verify:hover { background:#33485C; }
    .pv-action-reject { border-color:#E8D8D8; color:#8A5A5A; }
    .pv-action-reject:hover { background:#FBF2F2; }
    .pv-action-confirm-reject { display:none; border-color:#8A5A5A; background:#8A5A5A; color:#fff; }
    .pv-action-confirm-reject.is-open { display:inline-flex; align-items:center; }
    @media(max-width:600px) {
        .pv-modal { padding:10px; }
        .pv-panel { max-height:calc(100vh - 20px); }
        .pv-head, .pv-body { padding-left:15px; padding-right:15px; }
        .pv-details { grid-template-columns:1fr; }
        .pv-actions { flex-direction:column-reverse; align-items:stretch; }
        .pv-action { width:100%; }
    }
</style>

<div class="card" style="margin-bottom:18px; border-left:3px solid #3E5C76;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
        <div>
            <h3 style="margin:0 0 5px; color:#111827;">🔎 Pagos por verificar</h3>
            <p style="margin:0; color:#6B7280; font-size:12px;">
                Pagos registrados desde el barrio o por los encargados de calle que aún esperan confirmación para este periodo.
            </p>
        </div>
        <span style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:999px; background:#EEF2F6; color:#33485C; font-size:11px; font-weight:700;">
            <?= count($pagosPorVerificar) ?> pendiente<?= count($pagosPorVerificar) === 1 ? '' : 's' ?>
        </span>
    </div>

    <?php if (empty($pagosPorVerificar)): ?>
        <div style="display:flex; align-items:center; gap:10px; padding:16px; border:1px dashed #D1D5DB; border-radius:8px; background:#F9FAFB; color:#6B7280; font-size:12px;">
            <span style="font-size:20px;">✅</span>
            <span>No hay pagos pendientes de verificación para <?= $meses_nombres[$mes] ?> <?= $anio ?>.</span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table style="width:100%; border-collapse:collapse; font-size:12px;" data-page-size="10">
                <thead>
                    <tr style="background:#F9FAFB; border-bottom:1px solid #E5E7EB; color:#374151;">
                        <th style="padding:9px 10px; text-align:left;">Vivienda</th>
                        <th style="padding:9px 10px; text-align:left;">Propietario</th>
                        <th style="padding:9px 10px; text-align:left;">Calle</th>
                        <th style="padding:9px 10px; text-align:left;">Referencia</th>
                        <th style="padding:9px 10px; text-align:center;">Comprobante</th>
                        <th style="padding:9px 10px; text-align:right;">Monto</th>
                        <th style="padding:9px 10px; text-align:center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pagosPorVerificar as $pago): ?>
                    <?php
                    $identificacion = formato_identificacion($pago['cedula'] ?? '', $pago['cedula_tipo'] ?? 'V', $pago['otro_identificador'] ?? '');
                    $comprobantePago = trim((string)($pago['comprobante_pago'] ?? ''));
                    $esImagenComprobante = $comprobantePago !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i', $comprobantePago);
                    $fechaPago = $pago['fecha_confirmacion_barrio'] ?: ($pago['fecha_confirmacion_calle'] ?: ($pago['fecha_emision'] ?? ''));
                    $fechaPagoTexto = $fechaPago && strtotime((string)$fechaPago) ? date('d/m/Y', strtotime((string)$fechaPago)) : '—';
                    $origenPago = !empty($pago['fecha_confirmacion_barrio']) || !empty($pago['comprobante_barrio'])
                        ? 'Encargado de barrio'
                        : 'Encargado de calle';
                    ?>
                    <tr style="border-top:1px solid #F3F4F6;">
                        <td style="padding:9px 10px; font-weight:700; color:#111827;">#<?= htmlspecialchars($pago['numero_casa'] ?: 'S/N') ?></td>
                        <td style="padding:9px 10px;">
                            <span style="color:#111827; font-weight:600;"><?= htmlspecialchars($pago['propietario']) ?></span>
                            <?php if ($identificacion !== ''): ?>
                                <div style="font-size:9px; color:#9CA3AF;">ID: <?= htmlspecialchars($identificacion) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:9px 10px; color:#4B5563;"><?= htmlspecialchars($pago['calle_nombre']) ?></td>
                        <td style="padding:9px 10px; font-weight:600; color:#374151; overflow-wrap:anywhere;"><?= htmlspecialchars($pago['referencia_pago'] ?: '—') ?></td>
                        <td style="padding:9px 10px; text-align:center;">
                            <?php if ($esImagenComprobante): ?>
                                <img src="<?= htmlspecialchars($comprobantePago) ?>" alt="Comprobante" style="width:36px; height:36px; border-radius:5px; object-fit:cover; cursor:pointer; border:1px solid #E5E7EB;" onclick="openComprobanteModal(this)" data-url="<?= htmlspecialchars($comprobantePago) ?>">
                            <?php elseif ($comprobantePago !== ''): ?>
                                <a href="#" data-url="<?= htmlspecialchars($comprobantePago) ?>" onclick="return openComprobanteModal(this)" style="color:#3E5C76; text-decoration:none; font-size:11px; font-weight:700;">📎 PDF</a>
                            <?php else: ?>
                                <span style="color:#9CA3AF;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:9px 10px; text-align:right; font-weight:700; color:#3E5C76;">Bs. <?= number_format((float)$pago['monto'], 2) ?></td>
                        <td style="padding:9px 10px; text-align:center;">
                            <button
                                type="button"
                                class="pv-action pv-action-verify js-pv-open"
                                data-cobro-id="<?= (int)$pago['id'] ?>"
                                data-casa="<?= htmlspecialchars((string)($pago['numero_casa'] ?: 'S/N'), ENT_QUOTES, 'UTF-8') ?>"
                                data-propietario="<?= htmlspecialchars((string)$pago['propietario'], ENT_QUOTES, 'UTF-8') ?>"
                                data-identificacion="<?= htmlspecialchars((string)$identificacion, ENT_QUOTES, 'UTF-8') ?>"
                                data-calle="<?= htmlspecialchars((string)$pago['calle_nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                data-direccion="<?= htmlspecialchars((string)($pago['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-telefono="<?= htmlspecialchars((string)($pago['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-periodo="<?= htmlspecialchars($meses_nombres[(int)$pago['mes']] . ' ' . (int)$pago['anio'], ENT_QUOTES, 'UTF-8') ?>"
                                data-referencia="<?= htmlspecialchars((string)($pago['referencia_pago'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>"
                                data-monto="<?= htmlspecialchars(number_format((float)$pago['monto'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-fecha="<?= htmlspecialchars($fechaPagoTexto, ENT_QUOTES, 'UTF-8') ?>"
                                data-origen="<?= htmlspecialchars($origenPago, ENT_QUOTES, 'UTF-8') ?>"
                                data-comprobante="<?= htmlspecialchars($comprobantePago, ENT_QUOTES, 'UTF-8') ?>"
                                data-es-imagen="<?= $esImagenComprobante ? '1' : '0' ?>"
                            >Ver detalles</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="pvPagoModal" class="pv-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="pvPagoTitulo">
    <div class="pv-panel">
        <div class="pv-head">
            <div>
                <span class="pv-kicker">Revisión de pago</span>
                <h3 id="pvPagoTitulo">Detalles del pago</h3>
                <p id="pvSubtitulo">Revisa los datos y el comprobante antes de confirmar.</p>
            </div>
            <button type="button" class="pv-close" id="pvCerrarModal" aria-label="Cerrar">×</button>
        </div>
        <div class="pv-body">
            <div class="pv-summary">
                <div>
                    <span>Monto reportado</span>
                    <strong id="pvMonto">Bs. 0.00</strong>
                </div>
                <span class="pv-status">Pendiente de verificación</span>
            </div>

            <h4 class="pv-section-title">Datos de la vivienda y del reporte</h4>
            <div class="pv-details">
                <div class="pv-detail"><span>Vivienda</span><strong id="pvCasa">—</strong></div>
                <div class="pv-detail"><span>Propietario</span><strong id="pvPropietario">—</strong></div>
                <div class="pv-detail"><span>Identificación</span><strong id="pvIdentificacion">—</strong></div>
                <div class="pv-detail"><span>Calle</span><strong id="pvCalle">—</strong></div>
                <div class="pv-detail"><span>Dirección</span><strong id="pvDireccion">—</strong></div>
                <div class="pv-detail"><span>Teléfono</span><strong id="pvTelefono">—</strong></div>
                <div class="pv-detail"><span>Periodo</span><strong id="pvPeriodo">—</strong></div>
                <div class="pv-detail"><span>Referencia de pago</span><strong id="pvReferencia">—</strong></div>
                <div class="pv-detail"><span>Registrado por</span><strong id="pvOrigen">—</strong></div>
                <div class="pv-detail"><span>Fecha de registro</span><strong id="pvFecha">—</strong></div>
            </div>

            <section class="pv-receipt" aria-labelledby="pvComprobanteTitulo">
                <h4 class="pv-section-title" id="pvComprobanteTitulo">Comprobante</h4>
                <p class="pv-receipt-empty" id="pvSinComprobante">No se adjuntó un comprobante.</p>
                <img id="pvImagenComprobante" class="pv-receipt-image" alt="Comprobante del pago" hidden>
                <a id="pvPdfComprobante" class="pv-receipt-link" href="#" data-url="" onclick="return openComprobanteModal(this)" hidden>📎 Abrir comprobante PDF</a>
            </section>

            <form method="POST" action="router.php?page=reportar_pago&mes=<?= (int)$mes ?>&anio=<?= (int)$anio ?>" id="pvVerificarForm">
                <input type="hidden" name="action" value="verificar_pago_casa">
                <input type="hidden" name="page" value="reportar_pago">
                <input type="hidden" name="cobro_id" id="pvCobroId" value="">
                <input type="hidden" name="decision" id="pvDecision" value="Verificado">
                <div class="pv-reject-box" id="pvRechazoBox">
                    <label for="pvMotivoRechazo">Motivo del rechazo</label>
                    <textarea id="pvMotivoRechazo" name="motivo_rechazo" minlength="3" maxlength="1000" placeholder="Indica qué dato o comprobante debe corregirse."></textarea>
                </div>
                <div class="pv-actions">
                    <button type="button" class="pv-action" id="pvCancelarModal">Cerrar</button>
                    <button type="button" class="pv-action pv-action-reject" id="pvAlternarRechazo" aria-expanded="false">Rechazar pago</button>
                    <button type="submit" class="pv-action pv-action-verify" id="pvConfirmarVerificacion">Verificar pago</button>
                    <button type="submit" class="pv-action pv-action-confirm-reject" id="pvConfirmarRechazo">Confirmar rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('pvPagoModal');
    if (!modal) return;

    var form = document.getElementById('pvVerificarForm');
    var decision = document.getElementById('pvDecision');
    var rechazoBox = document.getElementById('pvRechazoBox');
    var motivo = document.getElementById('pvMotivoRechazo');
    var alternarRechazo = document.getElementById('pvAlternarRechazo');
    var confirmarVerificacion = document.getElementById('pvConfirmarVerificacion');
    var confirmarRechazo = document.getElementById('pvConfirmarRechazo');
    var imagen = document.getElementById('pvImagenComprobante');
    var pdf = document.getElementById('pvPdfComprobante');
    var sinComprobante = document.getElementById('pvSinComprobante');
    var ultimoDisparador = null;

    function cerrarModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('epsic-modal-open');
        if (ultimoDisparador) ultimoDisparador.focus();
    }

    function abrirModal(boton) {
        ultimoDisparador = boton;
        form.reset();
        rechazoBox.classList.remove('is-open');
        motivo.required = false;
        alternarRechazo.textContent = 'Rechazar pago';
        alternarRechazo.setAttribute('aria-expanded', 'false');
        alternarRechazo.hidden = false;
        confirmarVerificacion.hidden = false;
        confirmarRechazo.classList.remove('is-open');
        decision.value = 'Verificado';

        [
            ['pvCasa', 'casa'],
            ['pvPropietario', 'propietario'],
            ['pvIdentificacion', 'identificacion'],
            ['pvCalle', 'calle'],
            ['pvDireccion', 'direccion'],
            ['pvTelefono', 'telefono'],
            ['pvPeriodo', 'periodo'],
            ['pvReferencia', 'referencia'],
            ['pvFecha', 'fecha'],
            ['pvOrigen', 'origen']
        ].forEach(function (item) {
            document.getElementById(item[0]).textContent = boton.dataset[item[1]] || '—';
        });
        document.getElementById('pvCobroId').value = boton.dataset.cobroId || '';
        document.getElementById('pvMonto').textContent = 'Bs. ' + (boton.dataset.monto || '0.00');

        var comprobante = boton.dataset.comprobante || '';
        imagen.hidden = true;
        pdf.hidden = true;
        sinComprobante.hidden = Boolean(comprobante);
        if (comprobante && boton.dataset.esImagen === '1') {
            imagen.src = comprobante;
            imagen.dataset.url = comprobante;
            imagen.hidden = false;
        } else if (comprobante) {
            pdf.href = '#';
            pdf.dataset.url = comprobante;
            pdf.hidden = false;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('epsic-modal-open');
        document.getElementById('pvCerrarModal').focus();
    }

    document.querySelectorAll('.js-pv-open').forEach(function (boton) {
        boton.addEventListener('click', function () { abrirModal(boton); });
    });

    alternarRechazo.addEventListener('click', function () {
        var abierto = rechazoBox.classList.toggle('is-open');
        alternarRechazo.textContent = abierto ? 'Cancelar rechazo' : 'Rechazar pago';
        alternarRechazo.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        confirmarVerificacion.hidden = abierto;
        confirmarRechazo.classList.toggle('is-open', abierto);
        decision.value = 'Verificado';
        motivo.required = abierto;
        if (abierto) motivo.focus();
        else motivo.value = '';
    });

    confirmarVerificacion.addEventListener('click', function () {
        decision.value = 'Verificado';
    });
    confirmarRechazo.addEventListener('click', function () {
        decision.value = 'Rechazado';
    });
    form.addEventListener('submit', function (event) {
        if (event.submitter === confirmarRechazo && motivo.value.trim().length < 3) {
            event.preventDefault();
            motivo.focus();
        }
    });

    document.getElementById('pvCerrarModal').addEventListener('click', cerrarModal);
    document.getElementById('pvCancelarModal').addEventListener('click', cerrarModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) cerrarModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) cerrarModal();
    });
})();
</script>
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
        <input type="hidden" name="fecha_limite_pago" value="<?= htmlspecialchars($config['fecha_limite_pago'] ?: date('Y-m-t'), ENT_QUOTES, 'UTF-8') ?>">
        <label style="font-size:11px; color:#6B7280;">Cuota Bs.</label>
        <input type="number" step="0.01" name="cuota_mensual" value="<?= $config['cuota_mensual'] ?? 0 ?>"
            style="width:70px; padding:4px 6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
        <label style="font-size:11px; color:#6B7280;">Multa Bs.</label>
        <input type="number" step="0.01" name="multa_renovacion" value="<?= $config['multa_renovacion'] ?? 0 ?>"
            style="width:70px; padding:4px 6px; border:1px solid #D1D5DB; border-radius:4px; font-size:12px;">
        <button type="submit" style="background:#6B7280; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">Guardar</button>
    </form>
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
                    <button type="button" id="ver-viviendas-<?= (int)$lc['id'] ?>" aria-haspopup="dialog" aria-controls="detalle-<?= (int)$lc['id'] ?>" onclick="toggleDetalle(<?= (int)$lc['id'] ?>, this)" style="background:#374151; color:white; border:none; padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer;">🔍 Ver Viviendas</button>
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

                <div id="detalle-<?= (int)$lc['id'] ?>" class="lote-viviendas-modal" data-lote-id="<?= (int)$lc['id'] ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="detalle-titulo-<?= (int)$lc['id'] ?>" hidden onclick="if(event.target === this) toggleDetalle(<?= (int)$lc['id'] ?>)">
                    <div class="lote-viviendas-panel">
                        <div class="lote-viviendas-head">
                            <div>
                                <span class="lote-viviendas-kicker">Detalle del lote enviado</span>
                                <h3 id="detalle-titulo-<?= (int)$lc['id'] ?>">Viviendas — <?= htmlspecialchars($lc['calle_nombre']) ?></h3>
                                <p><?= htmlspecialchars($meses_nombres[(int)$lc['mes']] ?? '') ?> <?= (int)$lc['anio'] ?></p>
                            </div>
                            <button type="button" class="lote-viviendas-close" aria-label="Cerrar viviendas" onclick="toggleDetalle(<?= (int)$lc['id'] ?>)">×</button>
                        </div>
                        <div class="lote-viviendas-body">
                    <?php
                    $detalles_lc = [];
                    try {
                        $detStmt = $pdo->prepare(
                            "SELECT v.id as vivienda_id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.direccion, v.exento_cobro, v.estado_servicio,
                                   c.id as cobro_id, c.monto, c.estado, c.estado_verificacion, c.referencia_pago,
                                   c.comprobante_calle, c.comprobante_barrio, c.fecha_emision
                             FROM viviendas v
                             LEFT JOIN cobros c ON c.vivienda_id = v.id AND c.mes = ? AND c.anio = ? AND c.estado != 'Anulado'
                             WHERE v.calle_id = ?
                             ORDER BY CAST(v.numero_casa AS UNSIGNED), v.numero_casa"
                        );
                        $detStmt->execute([$lc['mes'], $lc['anio'], $lc['calle_id']]);
                        $detalles_lc = $detStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (\Throwable $e) {}
                    ?>
                    <div class="lote-viviendas-table-wrap">
                        <table class="lote-viviendas-table">
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
                                    $comprobanteDetalle = trim((string)($dl['comprobante_barrio'] ?? ''));
                                    if ($comprobanteDetalle === '') $comprobanteDetalle = trim((string)($dl['comprobante_calle'] ?? ''));
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
                                        <?php if($comprobanteDetalle !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i', $comprobanteDetalle)): ?>
                                            <img src="<?= htmlspecialchars($comprobanteDetalle) ?>" alt="Comp" style="width:36px; height:36px; border-radius:4px; object-fit:cover; cursor:pointer; border:1px solid #E5E7EB;" onclick="openComprobanteModal(this)" data-url="<?= htmlspecialchars($comprobanteDetalle) ?>">
                                        <?php elseif($comprobanteDetalle !== ''): ?>
                                            <a href="#" data-url="<?= htmlspecialchars($comprobanteDetalle) ?>" onclick="return openComprobanteModal(this)" style="color:#3E5C76; text-decoration:none;">📎 PDF</a>
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

<style>
    .lote-viviendas-modal {
        display:none; position:fixed; inset:0; z-index:3000; align-items:center;
        justify-content:center; padding:18px; background:rgba(17,24,39,.66);
    }
    .lote-viviendas-modal.is-open { display:flex; }
    .lote-viviendas-panel {
        display:flex; flex-direction:column; width:min(100%,1120px);
        max-height:calc(100vh - 36px); overflow:hidden; background:#fff;
        border:1px solid #E5E7EB; border-radius:14px; box-shadow:0 28px 80px rgba(17,24,39,.3);
    }
    .lote-viviendas-head {
        display:flex; justify-content:space-between; align-items:flex-start; gap:16px;
        padding:19px 22px; border-bottom:1px solid #EEF0F2;
    }
    .lote-viviendas-kicker { color:#718096; font-size:9px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
    .lote-viviendas-head h3 { margin:3px 0 4px; color:#17212B; font-size:18px; }
    .lote-viviendas-head p { margin:0; color:#6B7280; font-size:11px; }
    .lote-viviendas-close {
        flex:0 0 auto; width:34px; height:34px; border:0; border-radius:8px;
        background:#F3F4F6; color:#374151; font-size:20px; cursor:pointer;
    }
    .lote-viviendas-close:hover { background:#E5E7EB; }
    .lote-viviendas-body { overflow:auto; padding:16px 22px 22px; }
    .lote-viviendas-table-wrap { overflow:auto; border:1px solid #E5E7EB; border-radius:8px; }
    .lote-viviendas-table { width:100%; min-width:850px; border-collapse:collapse; font-size:11px; }
    .lote-viviendas-table thead { position:sticky; top:0; z-index:1; background:#F9FAFB; }
    @media(max-width:600px) {
        .lote-viviendas-modal { padding:10px; }
        .lote-viviendas-panel { max-height:calc(100vh - 20px); }
        .lote-viviendas-head, .lote-viviendas-body { padding-left:14px; padding-right:14px; }
    }
</style>

<script>
function toggleDetalle(id) {
    const modal = document.getElementById('detalle-' + id);
    if (!modal) return;

    const abrir = !modal.classList.contains('is-open');
    modal.hidden = !abrir;
    modal.classList.toggle('is-open', abrir);
    modal.setAttribute('aria-hidden', abrir ? 'false' : 'true');

    if (abrir) {
        document.body.classList.add('epsic-modal-open');
        modal.querySelector('.lote-viviendas-close').focus();
    } else {
        const otroModalAbierto = document.querySelector('.lote-viviendas-modal.is-open, .pv-modal.is-open');
        if (!otroModalAbierto) document.body.classList.remove('epsic-modal-open');
        const botonAbrir = document.getElementById('ver-viviendas-' + id);
        if (botonAbrir) botonAbrir.focus();
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    const modal = document.querySelector('.lote-viviendas-modal.is-open');
    if (modal) toggleDetalle(modal.dataset.loteId);
});

function toggleRechazo(id) {
    const el = document.getElementById('rechazo-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>