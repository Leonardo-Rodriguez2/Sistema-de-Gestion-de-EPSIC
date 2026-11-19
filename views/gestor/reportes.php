<?php
global $pdo;
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
use app\models\mainModel;
if (empty($pdo)) $pdo = (new mainModel())->conectar();

$user = check_dashboard_access([1, 2, 5]);
$page = 'reportes';

$tab = $_GET['tab'] ?? 'recaudacion';
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// El encargado de barrio solo puede consultar su propio barrio.
$esRolBarrio = ((int)($user['rol_id'] ?? 0) === 5);
$barrioUsuarioReporte = 0;
if ($esRolBarrio) {
    $bStmt = $pdo->prepare("SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1");
    $bStmt->execute([$user['id']]);
    $barrioUsuarioReporte = (int)$bStmt->fetchColumn();
}
if ($esRolBarrio && $barrioUsuarioReporte > 0) {
    $barrios = array_values(array_filter($barrios, static function ($b) use ($barrioUsuarioReporte) {
        return (int)$b['id'] === $barrioUsuarioReporte;
    }));
}

$callesReporte = $pdo->query(
    "SELECT c.id, c.nombre, c.barrio_id, b.nombre AS barrio_nombre
     FROM calles c
     JOIN barrios b ON b.id = c.barrio_id
     ORDER BY b.nombre, c.nombre"
)->fetchAll(PDO::FETCH_ASSOC);
if ($esRolBarrio && $barrioUsuarioReporte > 0) {
    $callesReporte = array_values(array_filter($callesReporte, static function ($c) use ($barrioUsuarioReporte) {
        return (int)$c['barrio_id'] === $barrioUsuarioReporte;
    }));
}
$callesPorBarrio = [];
foreach ($callesReporte as $c) {
    $callesPorBarrio[$c['barrio_nombre']][] = $c;
}
$renderCalleOptions = static function (int $seleccionada) use ($callesPorBarrio): void {
    foreach ($callesPorBarrio as $nombreBarrio => $listaCalles) {
        echo '<optgroup label="' . htmlspecialchars($nombreBarrio) . '">';
        foreach ($listaCalles as $c) {
            echo '<option value="' . (int)$c['id'] . '"' . ((int)$seleccionada === (int)$c['id'] ? ' selected' : '') . '>'
                . htmlspecialchars($c['nombre']) . '</option>';
        }
        echo '</optgroup>';
    }
};

$title = "Reportes - EPSIC";
$header_title = "Reportes y Estadísticas";
$header_subtitle = "Visualiza la recaudación, morosos y lotes cerrados por periodo.";

ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<!-- Pestañas -->
<div style="display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap; border-bottom:2px solid #E5E7EB; padding-bottom:10px;">
  <a href="router.php?page=reportes&tab=recaudacion" class="btn-primary" style="text-decoration:none; background:<?= $tab==='recaudacion'?'#4C6E91':'#6B7280' ?>;">💰 Recaudación</a>
  <a href="router.php?page=reportes&tab=morosos" class="btn-primary" style="text-decoration:none; background:<?= $tab==='morosos'?'#8A5A5A':'#6B7280' ?>;">⛔ Morosos</a>
  <a href="router.php?page=reportes&tab=lotes" class="btn-primary" style="text-decoration:none; background:<?= $tab==='lotes'?'#4C6E91':'#6B7280' ?>;">📦 Lotes Cerrados</a>
  <a href="router.php?page=reportes&tab=general" class="btn-primary" style="text-decoration:none; background:<?= $tab==='general'?'#5A6B7D':'#6B7280' ?>;">📊 General</a>
  <a href="router.php?page=reportes&tab=pagos" class="btn-primary" style="text-decoration:none; background:<?= $tab==='pagos'?'#3E5C76':'#6B7280' ?>;">Pagos por vivienda</a>
  <a href="router.php?page=reporte_calle" class="btn-primary" style="text-decoration:none; background:#33485C;">🏘️ Reporte por Calle</a>
</div>

<?php if ($tab === 'recaudacion'): ?>
<?php
$desde = $_GET['desde'] ?? date('Y-m', strtotime('-3 months'));
$hasta = $_GET['hasta'] ?? date('Y-m');
$barrio_id_r = (int)($_GET['barrio_id'] ?? 0);
if ($esRolBarrio && $barrioUsuarioReporte > 0) $barrio_id_r = $barrioUsuarioReporte;
$calle_id_r = (int)($_GET['calle_id'] ?? 0);

// Parse periodo
$d = explode('-', $desde); $dmes = (int)($d[1] ?? 1); $danio = (int)($d[0] ?? date('Y'));
$h = explode('-', $hasta); $hmes = (int)($h[1] ?? 12); $hanio = (int)($h[0] ?? date('Y'));

$stmt = $pdo->prepare("
  SELECT c.mes, c.anio, b.nombre as barrio, SUM(c.monto) as total, COUNT(c.id) as transacciones
  FROM cobros c
  JOIN viviendas v ON c.vivienda_id = v.id
  JOIN barrios b ON v.barrio_id = b.id
  JOIN calles ca ON ca.id = v.calle_id
  WHERE c.estado = 'Pagado'
    AND (c.anio > ? OR (c.anio = ? AND c.mes >= ?))
    AND (c.anio < ? OR (c.anio = ? AND c.mes <= ?))
  " . ($barrio_id_r > 0 ? " AND v.barrio_id = ?" : "") . "
  " . ($calle_id_r > 0 ? " AND v.calle_id = ?" : "") . "
  GROUP BY c.anio, c.mes, v.barrio_id, b.nombre
  ORDER BY c.anio DESC, c.mes DESC, b.nombre
");
$recaudacionParams = [$danio, $danio, $dmes, $hanio, $hanio, $hmes];
if ($barrio_id_r > 0) $recaudacionParams[] = $barrio_id_r;
if ($calle_id_r > 0) $recaudacionParams[] = $calle_id_r;
$stmt->execute($recaudacionParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gran_total = 0;
foreach ($rows as $r) $gran_total += $r['total'];
$filasRecaudacion = array_map(static function ($r) use ($meses) {
    return [$meses[(int)$r['mes']] . ' ' . $r['anio'], $r['barrio'], $r['transacciones'], monto((float)$r['total'])];
}, $rows);
reporte_exportar_si_solicitado(
    'recaudacion_periodo',
    'Recaudación por periodo',
    ['Periodo', 'Barrio', 'Transacciones', 'Total'],
    $filasRecaudacion,
    ['Total recaudado' => monto($gran_total)],
    'Reporte general de recaudación'
);
?>
<div class="card report-print-area">
  <div class="report-filter-heading">
    <h3 style="margin:0;">💰 Recaudación por Periodo</h3>
    <?= reporte_botonera('Excel general', 'Imprimir reporte') ?>
    <form method="GET" action="router.php" class="unified-filter no-print">
      <input type="hidden" name="page" value="reportes">
      <input type="hidden" name="tab" value="recaudacion">
      <label style="font-size:12px; color:#6B7280;">Desde:</label>
      <input type="month" name="desde" value="<?= $desde ?>" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:6px; font-size:12px;">
      <label style="font-size:12px; color:#6B7280;">Hasta:</label>
      <input type="month" name="hasta" value="<?= $hasta ?>" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:6px; font-size:12px;">
      <label style="font-size:12px;color:#6B7280;">Barrio:</label>
      <select name="barrio_id" style="padding:5px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:12px;">
        <option value="0">Todos</option>
        <?php foreach ($barrios as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $barrio_id_r === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <label style="font-size:12px;color:#6B7280;">Calle:</label>
      <select name="calle_id" style="padding:5px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:12px;">
        <option value="0">Todas</option>
        <?php $renderCalleOptions($calle_id_r); ?>
      </select>
      <button type="submit" class="btn-primary" style="padding:5px 12px;font-size:12px;">Filtrar</button>
      <button type="button" class="btn-primary no-print" style="padding:5px 12px;font-size:12px;background:#4B5563;" onclick="window.print()">Imprimir</button>
    </form>
  </div>
  <div style="font-size:24px; font-weight:800; color:#3E5C76; margin-bottom:15px;">
    Total recaudado: <?= monto($gran_total) ?>
  </div>
  <?php if (empty($rows)): ?>
    <div style="text-align:center; padding:30px; color:#9CA3AF;">Sin datos para el periodo seleccionado.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table data-page-size="10" data-no-tools="true">
      <thead>
        <tr><th>Periodo</th><th>Barrio</th><th>Transacciones</th><th>Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td style="padding:10px;"><?= $meses[$r['mes']] ?> <?= $r['anio'] ?></td>
          <td style="padding:10px;"><?= htmlspecialchars($r['barrio']) ?></td>
          <td style="padding:10px;"><?= $r['transacciones'] ?></td>
          <td style="padding:10px; font-weight:700; color:#3E5C76;"><?= monto($r['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <style>
  @media print {
    .sidebar, .header, .notif-wrap, .menu-toggle, .sidebar-overlay, .no-print, form, .btn-primary { display:none !important; }
    .report-print-area { box-shadow:none !important; border:0 !important; }
  }
  </style>
</div>
<?php endif; ?>

<?php if ($tab === 'pagos'): ?>
<?php
$pagoMes = max(1, min(12, (int)($_GET['mes'] ?? date('n'))));
$pagoAnio = (int)($_GET['anio'] ?? date('Y'));
$pagoBarrio = (int)($_GET['barrio_id'] ?? 0);
if ($esRolBarrio && $barrioUsuarioReporte > 0) $pagoBarrio = $barrioUsuarioReporte;
$pagoCalle = (int)($_GET['calle_id'] ?? 0);
$pagoEstado = ($_GET['estado_pago'] ?? 'todos') === 'pagado' ? 'pagado' : (($_GET['estado_pago'] ?? '') === 'pendiente' ? 'pendiente' : 'todos');
$sqlPagos = "SELECT v.id AS vivienda_id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, b.nombre AS barrio, c.nombre AS calle,
                    COALESCE(cp.pagado, 0) AS pagado, COALESCE(cp.monto, 0) AS monto
             FROM viviendas v
             JOIN barrios b ON b.id=v.barrio_id
             JOIN calles c ON c.id=v.calle_id
             LEFT JOIN (
                SELECT vivienda_id,
                       MAX(CASE WHEN estado='Pagado' THEN 1 ELSE 0 END) AS pagado,
                       MAX(CASE WHEN estado='Pagado' THEN monto ELSE 0 END) AS monto
                FROM cobros WHERE mes=? AND anio=? AND estado <> 'Anulado'
                GROUP BY vivienda_id
             ) cp ON cp.vivienda_id=v.id
             WHERE v.estado_servicio='Activo'";
$paramsPagos = [$pagoMes, $pagoAnio];
if ($pagoBarrio > 0) {
    $sqlPagos .= " AND v.barrio_id=?";
    $paramsPagos[] = $pagoBarrio;
}
if ($pagoCalle > 0) {
    $sqlPagos .= " AND v.calle_id=?";
    $paramsPagos[] = $pagoCalle;
}
if ($pagoEstado === 'pagado') $sqlPagos .= " AND COALESCE(cp.pagado,0)=1";
if ($pagoEstado === 'pendiente') $sqlPagos .= " AND COALESCE(cp.pagado,0)=0";
$sqlPagos .= " ORDER BY b.nombre, c.nombre, v.numero_casa, v.propietario";
$stmtPagos = $pdo->prepare($sqlPagos);
$stmtPagos->execute($paramsPagos);
$pagosViviendas = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
$historialClientes = [];
if ($pagosViviendas) {
    $idsViviendas = array_values(array_unique(array_map('intval', array_column($pagosViviendas, 'vivienda_id'))));
    $placeholders = implode(',', array_fill(0, count($idsViviendas), '?'));
    $histStmt = $pdo->prepare(
        "SELECT vivienda_id, mes, anio, monto, estado, fecha_emision
         FROM cobros WHERE vivienda_id IN ($placeholders) ORDER BY anio DESC, mes DESC"
    );
    $histStmt->execute($idsViviendas);
    foreach ($histStmt->fetchAll(PDO::FETCH_ASSOC) as $cobro) {
        $historialClientes[(int)$cobro['vivienda_id']][] = $cobro;
    }
}
$filasPagos = array_map(static function ($p) {
    return [$p['barrio'], $p['calle'], $p['numero_casa'], $p['propietario'], formato_identificacion($p['cedula'] ?? '', $p['cedula_tipo'] ?? 'V', $p['otro_identificador'] ?? ''), $p['pagado'] ? 'Pagado' : 'Pendiente', monto((float)$p['monto'])];
}, $pagosViviendas);
$modalClientes = [];
foreach ($pagosViviendas as $p) {
    $modalClientes[(string)$p['vivienda_id']] = [
        'propietario' => $p['propietario'],
        'numero_casa' => $p['numero_casa'],
        'barrio' => $p['barrio'],
        'calle' => $p['calle'],
        'identificacion' => formato_identificacion($p['cedula'] ?? '', $p['cedula_tipo'] ?? 'V', $p['otro_identificador'] ?? ''),
        'historial' => $historialClientes[(int)$p['vivienda_id']] ?? [],
    ];
}
reporte_exportar_si_solicitado(
    'pagos_por_vivienda',
    'Pagos por vivienda',
    ['Barrio', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Estado', 'Monto'],
    $filasPagos,
    ['Periodo' => $meses[$pagoMes] . ' ' . $pagoAnio, 'Total viviendas' => count($pagosViviendas)],
    'Reporte general de pagos'
);
?>
<div class="card report-print-area">
  <div class="report-filter-heading">
    <div><h3 style="margin:0;">Pagos por vivienda</h3><p style="margin:4px 0 0;color:#6B7280;">Consulta quién pagó o quién tiene pendiente un periodo específico.</p></div>
    <?= reporte_botonera('Excel general', 'Imprimir reporte') ?>
    <form method="GET" action="router.php" class="unified-filter no-print">
      <input type="hidden" name="page" value="reportes"><input type="hidden" name="tab" value="pagos">
      <label>Mes<select name="mes"><?php for($i=1;$i<=12;$i++): ?><option value="<?= $i ?>" <?= $pagoMes===$i?'selected':'' ?>><?= $meses[$i] ?></option><?php endfor; ?></select></label>
      <label>Año<input type="number" name="anio" value="<?= $pagoAnio ?>" min="2020" max="2100"></label>
      <label>Barrio<select name="barrio_id"><option value="0">Todos</option><?php foreach($barrios as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $pagoBarrio===(int)$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?></select></label>
      <label>Calle<select name="calle_id"><option value="0">Todas</option><?php $renderCalleOptions($pagoCalle); ?></select></label>
      <label>Estado<select name="estado_pago"><option value="todos" <?= $pagoEstado==='todos'?'selected':'' ?>>Todos</option><option value="pagado" <?= $pagoEstado==='pagado'?'selected':'' ?>>Pagaron</option><option value="pendiente" <?= $pagoEstado==='pendiente'?'selected':'' ?>>No pagaron</option></select></label>
      <button class="btn-primary" type="submit">Filtrar</button><button class="btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </form>
  </div>
  <p style="color:#6B7280;"><?= $meses[$pagoMes] ?> <?= $pagoAnio ?> · <?= count($pagosViviendas) ?> vivienda(s)</p>
  <div class="table-wrap"><table data-page-size="10" data-no-tools="true"><thead><tr><th>Barrio</th><th>Calle</th><th>Casa</th><th>Propietario</th><th>Cédula</th><th>Estado</th><th>Monto</th><th>Reporte</th></tr></thead><tbody>
  <?php if (!$pagosViviendas): ?><tr><td colspan="8" style="text-align:center;color:#9CA3AF;">No hay viviendas para los filtros seleccionados.</td></tr>
  <?php else: foreach($pagosViviendas as $p): ?><tr><td><?= htmlspecialchars($p['barrio']) ?></td><td><?= htmlspecialchars($p['calle']) ?></td><td><?= htmlspecialchars($p['numero_casa']) ?></td><td><?= htmlspecialchars($p['propietario']) ?></td><td><?php $identificacion = formato_identificacion($p['cedula'] ?? '', $p['cedula_tipo'] ?? 'V', $p['otro_identificador'] ?? ''); ?><?= $identificacion !== '' ? htmlspecialchars($identificacion) : '—' ?></td><td><?= $p['pagado'] ? 'Pagado' : 'Pendiente' ?></td><td><?= monto((float)$p['monto']) ?></td><td><button type="button" class="btn-edit cliente-reporte-btn" data-vivienda-id="<?= (int)$p['vivienda_id'] ?>">Ver reporte</button></td></tr><?php endforeach; endif; ?>
  </tbody></table></div>
</div>
<div id="clienteReporteModal" class="cliente-reporte-modal" aria-hidden="true">
  <div class="cliente-reporte-backdrop" data-cerrar-cliente></div>
  <section class="cliente-reporte-dialog" role="dialog" aria-modal="true" aria-labelledby="clienteReporteTitulo">
    <div class="cliente-reporte-header">
      <div>
        <span class="eyebrow">Reporte personal</span>
        <h2 id="clienteReporteTitulo">Historial del cliente</h2>
        <p id="clienteReporteDatos"></p>
      </div>
      <button type="button" class="cliente-reporte-close" data-cerrar-cliente aria-label="Cerrar">×</button>
    </div>
    <div class="cliente-reporte-actions no-print">
      <button type="button" class="btn-primary" id="clienteReporteExcel">📊 Descargar Excel</button>
      <button type="button" class="btn-primary" id="clienteReporteImprimir">🖨️ Imprimir reporte</button>
    </div>
    <div class="table-wrap">
      <table class="cliente-reporte-table">
        <thead><tr><th>Periodo</th><th>Monto</th><th>Estado</th><th>Fecha de emisión</th></tr></thead>
        <tbody id="clienteReporteFilas"></tbody>
      </table>
    </div>
  </section>
</div>
<style>
.cliente-reporte-modal { display:none; position:fixed; inset:0; z-index:10001; align-items:center; justify-content:center; padding:20px; }
.cliente-reporte-modal.is-open { display:flex; }
.cliente-reporte-backdrop { position:absolute; inset:0; background:rgba(23,33,43,.72); }
.cliente-reporte-dialog { position:relative; width:min(100%,900px); max-height:90vh; overflow:auto; background:#fff; border:1px solid #d9e1e7; box-shadow:0 20px 60px rgba(23,33,43,.24); padding:24px; }
.cliente-reporte-header { display:flex; justify-content:space-between; gap:20px; align-items:flex-start; border-bottom:1px solid #e8eef2; padding-bottom:16px; }
.cliente-reporte-header h2 { margin:4px 0; color:#17212b; font-size:19px; }
.cliente-reporte-header p { margin:0; color:#657384; font-size:12px; }
.cliente-reporte-close { border:0; background:#eef2f5; color:#344454; font-size:24px; width:34px; height:34px; cursor:pointer; }
.cliente-reporte-actions { display:flex; justify-content:flex-end; gap:8px; padding:16px 0; }
.cliente-reporte-table th { background:#176b87; color:#fff; }
body.cliente-reporte-open { overflow:hidden; }
@media print {
  .sidebar, .header, .notif-wrap, .menu-toggle, .sidebar-overlay, .no-print, form, .btn-primary { display:none !important; }
  .main { margin-left:0 !important; padding:0 !important; }
  .report-print-area { border:0 !important; box-shadow:none !important; }
}
</style>
<script>
(function () {
  var clientes = <?= json_encode($modalClientes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var meses = <?= json_encode($meses, JSON_UNESCAPED_UNICODE) ?>;
  var modal = document.getElementById('clienteReporteModal');
  var datos = document.getElementById('clienteReporteDatos');
  var filas = document.getElementById('clienteReporteFilas');
  var clienteActivo = null;
  function texto(valor) {
    return String(valor === null || valor === undefined || valor === '' ? '—' : valor)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  function abrir(id) {
    clienteActivo = clientes[String(id)];
    if (!clienteActivo) return;
    datos.textContent = clienteActivo.propietario + ' · Casa ' + clienteActivo.numero_casa + ' · ' + clienteActivo.barrio + ' · ' + clienteActivo.calle;
    filas.innerHTML = '';
    if (!clienteActivo.historial.length) {
      filas.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#9CA3AF;">No hay historial de pagos.</td></tr>';
    } else {
      clienteActivo.historial.forEach(function (cobro) {
        filas.insertAdjacentHTML('beforeend', '<tr><td>' + texto(meses[Number(cobro.mes)] + ' ' + cobro.anio) + '</td><td>' + fmtMonto(cobro.monto || 0) + '</td><td>' + texto(cobro.estado) + '</td><td>' + texto(cobro.fecha_emision) + '</td></tr>');
      });
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('cliente-reporte-open');
  }
  function cerrar() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('cliente-reporte-open');
    clienteActivo = null;
  }
  function excel() {
    if (!clienteActivo) return;
    var html = '<table><tr><th>Periodo</th><th>Monto</th><th>Estado</th><th>Fecha de emisión</th></tr>';
    clienteActivo.historial.forEach(function (c) {
      html += '<tr><td>' + meses[Number(c.mes)] + ' ' + c.anio + '</td><td>' + fmtMonto(c.monto || 0) + '</td><td>' + c.estado + '</td><td>' + (c.fecha_emision || '') + '</td></tr>';
    });
    html += '</table>';
    var blob = new Blob(['\ufeff', '<html><meta charset="UTF-8"><body><h2>Reporte personal de ' + clienteActivo.propietario + '</h2>' + html + '</body></html>'], { type: 'application/vnd.ms-excel;charset=utf-8' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_cliente_' + clienteActivo.numero_casa + '.xls';
    link.click();
    URL.revokeObjectURL(link.href);
  }
  function imprimir() {
    if (!clienteActivo) return;
    var ventana = window.open('', '_blank', 'width=900,height=700');
    if (!ventana) return;
    ventana.document.write('<html><head><title>Reporte personal</title><style>body{font-family:Arial;color:#17212b;padding:25px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccd5dc;padding:8px;text-align:left}th{background:#176b87;color:#fff}</style></head><body><h1>Reporte personal del cliente</h1><p><strong>' + texto(clienteActivo.propietario) + '</strong> · Casa ' + texto(clienteActivo.numero_casa) + ' · ' + texto(clienteActivo.barrio) + ' · ' + texto(clienteActivo.calle) + '</p>' + document.querySelector('.cliente-reporte-table').outerHTML + '</body></html>');
    ventana.document.close();
    ventana.focus();
    ventana.print();
  }
  document.querySelectorAll('.cliente-reporte-btn').forEach(function (button) {
    button.addEventListener('click', function () { abrir(button.dataset.viviendaId); });
  });
  document.querySelectorAll('[data-cerrar-cliente]').forEach(function (element) { element.addEventListener('click', cerrar); });
  document.getElementById('clienteReporteExcel').addEventListener('click', excel);
  document.getElementById('clienteReporteImprimir').addEventListener('click', imprimir);
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && clienteActivo) cerrar(); });
})();
</script>
<?php endif; ?>

<?php if ($tab === 'morosos'): ?>
<?php
$barrio_id_m = (int)($_GET['barrio_id'] ?? 0);
if ($esRolBarrio && $barrioUsuarioReporte > 0) $barrio_id_m = $barrioUsuarioReporte;
$calle_id_m = (int)($_GET['calle_id'] ?? 0);

$sql = "SELECT v.id, v.numero_casa, v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador, v.direccion, b.nombre as barrio, c.nombre as calle,
               SUM(cb.monto) as deuda_total, COUNT(cb.id) as meses_deuda
        FROM viviendas v
        JOIN barrios b ON v.barrio_id = b.id
        JOIN calles c ON v.calle_id = c.id
        LEFT JOIN cobros cb ON cb.vivienda_id = v.id AND cb.estado NOT IN ('Pagado','Anulado')
        WHERE v.estado_servicio = 'Activo'";
$params = [];
if ($barrio_id_m) {
  $sql .= " AND v.barrio_id = ?";
  $params[] = $barrio_id_m;
}
if ($calle_id_m) {
  $sql .= " AND v.calle_id = ?";
  $params[] = $calle_id_m;
}
$sql .= " GROUP BY v.id HAVING deuda_total > 0 ORDER BY deuda_total DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$morosos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$filasMorosos = array_map(static function ($m) {
    return [$m['numero_casa'], $m['propietario'], formato_identificacion($m['cedula'] ?? '', $m['cedula_tipo'] ?? 'V', $m['otro_identificador'] ?? ''), $m['barrio'], $m['calle'], $m['meses_deuda'], monto((float)$m['deuda_total'])];
}, $morosos);
reporte_exportar_si_solicitado(
    'casas_morosas',
    'Casas morosas',
    ['Casa', 'Propietario', 'Identificación', 'Barrio', 'Calle', 'Meses deuda', 'Deuda total'],
    $filasMorosos,
    ['Total registros' => count($morosos)],
    'Reporte general de viviendas con deuda'
);
?>
<div class="card">
  <div class="report-filter-heading">
    <h3 style="margin:0;">⛔ Casas Morosas</h3>
    <?= reporte_botonera('Excel general', 'Imprimir reporte') ?>
    <form method="GET" action="router.php" class="unified-filter no-print">
      <input type="hidden" name="page" value="reportes">
      <input type="hidden" name="tab" value="morosos">
      <select name="barrio_id" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:6px; font-size:12px;">
        <option value="0">Todos los barrios</option>
        <?php foreach ($barrios as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $barrio_id_m==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="calle_id" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:6px; font-size:12px;">
        <option value="0">Todas las calles</option>
        <?php $renderCalleOptions($calle_id_m); ?>
      </select>
      <button type="submit" class="btn-primary" style="padding:5px 12px; font-size:12px;">Filtrar</button>
    </form>
  </div>
  <?php if (empty($morosos)): ?>
    <div style="text-align:center; padding:30px; color:#9CA3AF;">No hay casas morosas<?= $barrio_id_m?' en este barrio':'' ?>.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table data-page-size="10" data-no-tools="true">
      <thead>
        <tr><th>Casa</th><th>Propietario</th><th>Cédula</th><th>Barrio</th><th>Calle</th><th>Meses Deuda</th><th>Deuda Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($morosos as $m): ?>
        <tr>
          <td style="padding:10px; font-weight:700;">#<?= htmlspecialchars($m['numero_casa']) ?></td>
          <td style="padding:10px;"><?= htmlspecialchars($m['propietario']) ?></td>
          <td style="padding:10px;"><?php $identificacion = formato_identificacion($m['cedula'] ?? '', $m['cedula_tipo'] ?? 'V', $m['otro_identificador'] ?? ''); ?><?= $identificacion !== '' ? htmlspecialchars($identificacion) : '—' ?></td>
          <td style="padding:10px;"><?= htmlspecialchars($m['barrio']) ?></td>
          <td style="padding:10px;"><?= htmlspecialchars($m['calle']) ?></td>
          <td style="padding:10px;"><?= $m['meses_deuda'] ?></td>
          <td style="padding:10px; font-weight:700; color:#8A5A5A;"><?= monto($m['deuda_total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'lotes'): ?>
<?php
$barrio_id_l = (int)($_GET['barrio_id'] ?? 0);
if ($esRolBarrio && $barrioUsuarioReporte > 0) $barrio_id_l = $barrioUsuarioReporte;
$lotesSql = "
  SELECT lb.*, b.nombre as barrio_nombre, u.nombre as encargado, rf.numero_recibo
  FROM lotes_barrio lb
  JOIN barrios b ON lb.barrio_id = b.id
  LEFT JOIN usuarios u ON lb.encargado_barrio_id = u.id
  LEFT JOIN recibos_finiquito rf ON rf.lote_barrio_id = lb.id
  WHERE 1=1";
$lotesParams = [];
if ($barrio_id_l > 0) {
  $lotesSql .= " AND lb.barrio_id = ?";
  $lotesParams[] = $barrio_id_l;
}
$lotesSql .= " ORDER BY lb.fecha_envio DESC LIMIT 50";
$lotesStmt = $pdo->prepare($lotesSql);
$lotesStmt->execute($lotesParams);
$lotes = $lotesStmt->fetchAll(PDO::FETCH_ASSOC);
$filasLotes = array_map(static function ($l) {
    return [$l['barrio_nombre'], $l['periodo_mes'] . '/' . $l['periodo_anio'], $l['estado'], monto((float)$l['monto_esperado']), monto((float)$l['monto_total_recolectado']), $l['numero_recibo'] ?? '', $l['fecha_envio'] ?? ''];
}, $lotes);
reporte_exportar_si_solicitado(
    'lotes_barrio_cerrados',
    'Lotes de barrio cerrados',
    ['Barrio', 'Periodo', 'Estado', 'Esperado', 'Recolectado', 'Recibo', 'Envío'],
    $filasLotes,
    ['Total lotes' => count($lotes)],
    'Reporte general de lotes cerrados'
);
?>
<div class="card">
  <div class="report-filter-heading">
    <h3 style="margin:0;">📦 Lotes de Barrio Cerrados</h3>
    <?= reporte_botonera('Excel general', 'Imprimir reporte') ?>
    <form method="GET" action="router.php" class="unified-filter no-print">
      <input type="hidden" name="page" value="reportes">
      <input type="hidden" name="tab" value="lotes">
      <select name="barrio_id" style="padding:5px 8px; border:1px solid #D1D5DB; border-radius:6px; font-size:12px;">
        <option value="0">Todos los barrios</option>
        <?php foreach ($barrios as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $barrio_id_l === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary" style="padding:5px 12px; font-size:12px;">Filtrar</button>
      <button type="button" class="btn-primary no-print" style="padding:5px 12px; font-size:12px; background:#4B5563;" onclick="window.print()">Imprimir</button>
    </form>
  </div>
  <?php if (empty($lotes)): ?>
    <div style="text-align:center; padding:30px; color:#9CA3AF;">No hay lotes registrados.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table data-page-size="10" data-no-tools="true">
      <thead>
        <tr><th>Barrio</th><th>Periodo</th><th>Estado</th><th>Esperado</th><th>Recolectado</th><th>Recibo</th><th>Envío</th></tr>
      </thead>
      <tbody>
        <?php foreach ($lotes as $l):
          $ec = ['Enviado'=>'#8A7A5A','Aprobado'=>'#4C6E91','Rechazado'=>'#8A5A5A'];
        ?>
        <tr>
          <td style="padding:10px; font-weight:700;"><?= htmlspecialchars($l['barrio_nombre']) ?></td>
          <td style="padding:10px;"><?= $meses[$l['periodo_mes']] ?> <?= $l['periodo_anio'] ?></td>
          <td style="padding:10px;"><span class="badge" style="background:<?= ($ec[$l['estado']]??'#6B7280') ?>22; color:<?= ($ec[$l['estado']]??'#6B7280') ?>;"><?= $l['estado'] ?></span></td>
          <td style="padding:10px;"><?= monto($l['monto_total_esperado']) ?></td>
          <td style="padding:10px; font-weight:700; color:#3E5C76;"><?= monto($l['monto_total_recolectado']) ?></td>
          <td style="padding:10px;"><?= $l['numero_recibo'] ? htmlspecialchars($l['numero_recibo']) : '—' ?></td>
          <td style="padding:10px; font-size:12px; color:#6B7280;"><?= $l['fecha_envio'] ? date('d/m/Y', strtotime($l['fecha_envio'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <style>
  @media print {
    .sidebar, .header, .notif-wrap, .menu-toggle, .sidebar-overlay, .no-print, form, .btn-primary { display:none !important; }
    .main { margin-left:0 !important; padding:0 !important; }
    .card { border:0 !important; box-shadow:none !important; }
  }
  </style>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'general'): ?>
<?php
$g = $pdo->query("
  SELECT
    (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Activo') as activas,
    (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Suspendido') as suspendidas,
    (SELECT COUNT(*) FROM viviendas WHERE estado_servicio='Anulado') as anuladas,
    (SELECT COUNT(*) FROM barrios) as barrios,
    (SELECT COUNT(*) FROM calles) as calles,
    (SELECT COUNT(*) FROM usuarios) as usuarios,
    (SELECT SUM(monto_total_recolectado) FROM lotes_barrio WHERE estado='Aprobado') as total_aprobado,
    (SELECT COUNT(*) FROM lotes_barrio WHERE estado='Aprobado') as lotes_aprobados,
    (SELECT SUM(monto) FROM cobros WHERE estado='Pagado' AND YEAR(fecha_emision)=YEAR(CURDATE())) as anual
")->fetch(PDO::FETCH_ASSOC);
$filasGeneral = [[
    $g['activas'], $g['suspendidas'], $g['anuladas'], $g['barrios'], $g['calles'],
    $g['usuarios'], $g['lotes_aprobados'], monto((float)($g['total_aprobado'] ?? 0)),
    monto((float)($g['anual'] ?? 0))
]];
reporte_exportar_si_solicitado(
    'resumen_general',
    'Resumen general del sistema',
    ['Casas activas', 'Suspendidas', 'Anuladas', 'Barrios', 'Calles', 'Usuarios', 'Lotes aprobados', 'Total aprobado', 'Recaudación anual'],
    $filasGeneral,
    [],
    'Reporte general de estadísticas'
);
?>
<div class="report-toolbar unified-report-toolbar no-print">
  <?= reporte_botonera('Excel general', 'Imprimir reporte') ?>
</div>
<div class="grid" style="margin-bottom:20px;">
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Casas Activas</div><div style="font-size:28px;font-weight:800;color:#4C6E91;"><?= $g['activas'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Suspendidas</div><div style="font-size:28px;font-weight:800;color:#8A7A5A;"><?= $g['suspendidas'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Anuladas</div><div style="font-size:28px;font-weight:800;color:#8A5A5A;"><?= $g['anuladas'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Barrios</div><div style="font-size:28px;font-weight:800;color:#5A6B7D;"><?= $g['barrios'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Calles</div><div style="font-size:28px;font-weight:800;color:#4C6E91;"><?= $g['calles'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Usuarios</div><div style="font-size:28px;font-weight:800;color:#4B5563;"><?= $g['usuarios'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Lotes Aprobados</div><div style="font-size:28px;font-weight:800;color:#3E5C76;"><?= $g['lotes_aprobados'] ?></div></div>
  <div class="card" style="text-align:center;"><div style="font-size:11px;color:#6B7280;">Total Aprobado</div><div style="font-size:28px;font-weight:800;color:#3E5C76;"><?= monto($g['total_aprobado']??0) ?></div></div>
  <div class="card" style="text-align:center; grid-column: span 2;"><div style="font-size:11px;color:#6B7280;">Recaudación Anual (este año)</div><div style="font-size:28px;font-weight:800;color:#3E5C76;"><?= monto($g['anual']??0) ?></div></div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
