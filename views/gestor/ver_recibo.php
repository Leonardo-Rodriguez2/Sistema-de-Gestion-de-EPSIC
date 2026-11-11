<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use app\models\mainModel;

$pdo = (new mainModel())->conectar();

$sid = $_SESSION['active_sid'] ?? 'main';
$uid = $_SESSION['identities'][$sid]['user_id'] ?? $_SESSION['user_id'] ?? null;

if (!$uid) {
    exit('Acceso denegado.');
}

$uStmt = $pdo->prepare("SELECT rol_id FROM usuarios WHERE id = ?");
$uStmt->execute([$uid]);
$rol_id = (int)($uStmt->fetchColumn() ?: 0);

$cobro_id = (int)($_GET['cobro_id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT c.*, v.propietario as v_prop, v.cedula as v_cedula, v.cedula_tipo as v_cedula_tipo,
            v.otro_identificador as v_otro_identificador,
            v.numero_casa as v_casa, v.direccion, v.barrio_id, v.calle_id,
            b.nombre as barrio_nombre, v.estado_servicio, cl.nombre as calle_nombre,
            (SELECT cuota_mensual FROM configuraciones_barrio WHERE barrio_id = v.barrio_id) as cuota
     FROM cobros c
     JOIN viviendas v ON c.vivienda_id = v.id
     JOIN barrios b ON v.barrio_id = b.id
     LEFT JOIN calles cl ON v.calle_id = cl.id
     WHERE c.id = ?"
);
$stmt->execute([$cobro_id]);
$cobro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cobro) {
    exit('Recibo no encontrado.');
}

// Control de acceso: admin y gestor ven todo; barrio/calle solo su jurisdicción.
$permitido = in_array($rol_id, [1, 2], true);
if (!$permitido && $rol_id === 5) {
    $chk = $pdo->prepare("SELECT 1 FROM detalles_encargado_barrio WHERE usuario_id = ? AND barrio_id = ?");
    $chk->execute([$uid, $cobro['barrio_id']]);
    $permitido = (bool)$chk->fetchColumn();
} elseif (!$permitido && $rol_id === 6) {
    $chk = $pdo->prepare("SELECT 1 FROM detalles_encargado_calle WHERE usuario_id = ? AND calle_id = ?");
    $chk->execute([$uid, $cobro['calle_id']]);
    $permitido = (bool)$chk->fetchColumn();
}
if (!$permitido) {
    exit('No tienes permiso para ver este recibo.');
}

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$periodo = $meses[(int)$cobro['mes']] . ' ' . $cobro['anio'];
$fecha_pago = $cobro['fecha_confirmacion_barrio'] ?? $cobro['fecha_confirmacion_calle'] ?? $cobro['fecha_emision'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recibo de Pago #<?= $cobro_id ?> - EcoCusco</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #F3F4F6; padding: 40px 20px; }
    .recibo { max-width: 500px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.12); }
    .header { background: linear-gradient(135deg,#111827 0%,#1F2937 60%,#33485C 100%); color: #fff; padding: 30px; text-align: center; }
    .header h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
    .header .sub { font-size: 11px; opacity: 0.7; }
    .body { padding: 25px 30px; }
    .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F3F4F6; font-size: 13px; }
    .row .label { color: #6B7280; }
    .row .value { font-weight: 700; color: #111827; }
    .monto-box { text-align: center; padding: 20px; background: linear-gradient(135deg,#EEF2F6,#E9EFF4); border-radius: 10px; border: 2px solid #A9C0D3; margin: 16px 0; }
    .monto-box .amount { font-size: 32px; font-weight: 800; color: #33485C; }
    .monto-box .label { font-size: 11px; color: #33485C; margin-bottom: 4px; text-transform: uppercase; }
    .comprobante-section { margin-top: 16px; padding: 16px; background: #E9EFF4; border-radius: 10px; border-left: 4px solid #3E5C76; }
    .comprobante-section h3 { font-size: 11px; color: #3E5C76; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
    .comprobante-section img { max-width: 100%; border-radius: 8px; border: 1px solid #CDDCE8; cursor: pointer; }
    .footer { text-align: center; padding: 16px 30px; font-size: 10px; color: #9CA3AF; border-top: 1px solid #E5E7EB; }
    .no-print { text-align: center; margin-bottom: 20px; }
    .btn { display: inline-block; padding: 10px 20px; background: #111827; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
    @media print {
      body { background: #fff !important; padding: 0 !important; }
      .recibo { box-shadow: none !important; border-radius: 0 !important; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button class="btn" onclick="window.print()">🖨️ Imprimir / PDF</button>
    <a href="javascript:window.close()" style="margin-left:10px; color:#6B7280; font-size:13px;">Cerrar</a>
  </div>

  <div class="recibo">
    <div class="header">
      <div class="sub">SISTEMA DE GESTIÓN DE RECICLAJE — EPSIC / EcoCusco</div>
      <h1>RECIBO DE PAGO</h1>
      <div style="font-size:12px; opacity:0.8; margin-top:4px;">N° <?= str_pad($cobro_id, 6, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="body">
      <div class="row">
        <span class="label">Propietario</span>
        <span class="value"><?= htmlspecialchars($cobro['v_prop']) ?></span>
      </div>
      <?php $identificacion = formato_identificacion($cobro['v_cedula'] ?? '', $cobro['v_cedula_tipo'] ?? 'V', $cobro['v_otro_identificador'] ?? ''); ?>
      <?php if ($identificacion !== ''): ?>
      <div class="row">
        <span class="label">Identificación</span>
        <span class="value"><?= htmlspecialchars($identificacion) ?></span>
      </div>
      <?php endif; ?>
      <div class="row">
        <span class="label">Casa #</span>
        <span class="value"><?= htmlspecialchars($cobro['v_casa']) ?></span>
      </div>
      <div class="row">
        <span class="label">Calle</span>
        <span class="value"><?= htmlspecialchars($cobro['calle_nombre'] ?? '—') ?></span>
      </div>
      <div class="row">
        <span class="label">Barrio</span>
        <span class="value"><?= htmlspecialchars($cobro['barrio_nombre']) ?></span>
      </div>
      <div class="row">
        <span class="label">Dirección</span>
        <span class="value"><?= htmlspecialchars($cobro['direccion'] ?? '—') ?></span>
      </div>
      <div class="row">
        <span class="label">Periodo</span>
        <span class="value"><?= $periodo ?></span>
      </div>
      <div class="row">
        <span class="label">Fecha de Pago</span>
        <span class="value"><?= date('d/m/Y', strtotime($fecha_pago)) ?></span>
      </div>
      <div class="row">
        <span class="label">Estado</span>
        <span class="value" style="color:#3E5C76;"><?= $cobro['estado'] ?></span>
      </div>

      <div class="monto-box">
        <div class="label">Monto Cancelado</div>
        <div class="amount">Bs. <?= number_format($cobro['monto'], 2) ?></div>
      </div>

      <?php if(!empty($cobro['comprobante_calle'])): ?>
      <div class="comprobante-section">
        <h3>📎 Comprobante Adjunto</h3>
        <a href="<?= htmlspecialchars($cobro['comprobante_calle']) ?>" target="_blank">
          <img src="<?= htmlspecialchars($cobro['comprobante_calle']) ?>" alt="Comprobante de pago"
               onerror="this.parentElement.innerHTML='<p style=color:#7A4F4F;font-size:12px;>Imagen no disponible. <a href='+this.src+' target=_blank>Abrir directamente</a></p>'">
        </a>
      </div>
      <?php endif; ?>

      <?php if(!empty($cobro['referencia_pago']) && $cobro['referencia_pago'] !== 'Pago directo'): ?>
      <div style="margin-top:12px; font-size:12px; color:#6B7280;">
        <strong>Referencia:</strong> <?= htmlspecialchars($cobro['referencia_pago']) ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="footer">
      Este documento es una constancia de pago registrada en el sistema EcoCusco.<br>
      Generado el <?= date('d/m/Y H:i') ?>
    </div>
  </div>
</body>
</html>
<?php
exit;
