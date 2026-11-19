<?php
// views/gestor/certificado_calle.php — Historial y envío de facturas por calle
global $pdo, $user;
use app\models\mainModel;

if (empty($pdo)) $pdo = (new mainModel())->conectar();

$rolActual = (int)($user['rol_id'] ?? 0);
$pageCertificado = in_array($rolActual, [5, 6], true) ? 'certificado_calle' : 'gestor_certificado_calle';
$puedeEnviarFacturas = !in_array($rolActual, [5, 6], true);

$lote_calle_id = (int)($_GET['lote_calle_id'] ?? 0);
if (!$lote_calle_id) {
    $vistaFacturacion = ($_GET['vista'] ?? 'pendientes') === 'emitidas' ? 'emitidas' : 'pendientes';
    $f_buscar = trim((string)($_GET['search'] ?? ''));
    $f_barrio = (int)($_GET['barrio_id'] ?? 0);
    // Compatibilidad con lotes aprobados antes de habilitar la generación de certificados.
    // Primero repara lotes antiguos que no quedaron vinculados al lote de barrio
    // aunque pertenecen al mismo barrio, mes y año.
    $pdo->exec(
        "UPDATE lotes_calle lc
         INNER JOIN lotes_barrio lb
                 ON lb.barrio_id = lc.barrio_id
                AND lb.periodo_mes = lc.periodo_mes
                AND lb.periodo_anio = lc.periodo_anio
         SET lc.lote_barrio_id = lb.id
         WHERE lb.estado = 'Aprobado'
           AND (lc.lote_barrio_id IS NULL OR lc.lote_barrio_id = 0)
           AND lc.estado = 'Aprobado'"
    );

    // Solo sincroniza lotes de calle pertenecientes a lotes de barrio aprobados.
    $pdo->exec(
        "UPDATE lotes_calle lc
         INNER JOIN lotes_barrio lb ON lb.id = lc.lote_barrio_id
         SET lc.certificado_generado = 1,
             lc.fecha_certificado = COALESCE(lc.fecha_certificado, NOW())
         WHERE lb.estado = 'Aprobado' AND lc.certificado_generado = 0"
    );

    $certificadosSql = "SELECT lc.id, lc.lote_barrio_id, lc.periodo_mes, lc.periodo_anio,
                               lc.casas_pagadas, lc.total_casas, lc.monto_recolectado,
                               lb.estado AS barrio_estado, lb.facturas_enviadas_barrio,
                               c.nombre AS calle_nombre, b.nombre AS barrio_nombre
                        FROM lotes_calle lc
                        JOIN calles c ON c.id = lc.calle_id
                        JOIN barrios b ON b.id = lc.barrio_id
                        LEFT JOIN lotes_barrio lb ON lb.id = lc.lote_barrio_id
                        WHERE lc.certificado_generado = 1
                          AND lb.estado = 'Aprobado'";
    $certificadosParams = [];
    if ($vistaFacturacion === 'pendientes') {
        $certificadosSql .= " AND COALESCE(lb.facturas_enviadas_barrio, 0) = 0";
    } else {
        $certificadosSql .= " AND lb.facturas_enviadas_barrio = 1";
    }
    if ($f_barrio > 0) {
        $certificadosSql .= " AND b.id = ?";
        $certificadosParams[] = $f_barrio;
    }
    if ($f_buscar !== '') {
        $certificadosSql .= " AND (b.nombre LIKE ? OR c.nombre LIKE ?)";
        $certificadosParams[] = '%' . $f_buscar . '%';
        $certificadosParams[] = '%' . $f_buscar . '%';
    }

    if ($rolActual === 5) {
        // El encargado de barrio solo puede consultar las facturas de su barrio.
        $certificadosSql .= " AND lc.barrio_id = (
            SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1
        )";
        $certificadosParams[] = $user['id'];
    }

    if ($rolActual === 6) {
        $certificadosSql .= " AND lc.calle_id = (
            SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ? LIMIT 1
        )";
        $certificadosParams[] = $user['id'];
    }

    $certificadosSql .= " ORDER BY lc.periodo_anio DESC, lc.periodo_mes DESC, b.nombre, c.nombre";
    $certificadosStmt = $pdo->prepare($certificadosSql);
    $certificadosStmt->execute($certificadosParams);
    $certificados = $certificadosStmt->fetchAll(PDO::FETCH_ASSOC);
    $certificadosPorLote = [];
    foreach ($certificados as $certificado) {
        $clave = (int)($certificado['lote_barrio_id'] ?: $certificado['id']);
        $certificadosPorLote[$clave][] = $certificado;
    }
    $filasFacturacion = [];
    foreach ($certificadosPorLote as $loteCertificados) {
        $primero = $loteCertificados[0];
        $filasFacturacion[] = [
            $primero['barrio_nombre'] ?? '',
            (int)$primero['periodo_mes'] . '/' . (int)$primero['periodo_anio'],
            count($loteCertificados),
            array_sum(array_map(static function ($fila) { return (int)$fila['total_casas']; }, $loteCertificados)),
            monto(array_sum(array_map(static function ($fila) { return (float)$fila['monto_recolectado']; }, $loteCertificados))),
            $vistaFacturacion === 'pendientes' ? 'Pendiente' : 'Emitida',
        ];
    }
    reporte_exportar_si_solicitado(
        'facturacion_' . $vistaFacturacion,
        $vistaFacturacion === 'pendientes' ? 'Lotes pendientes de facturación' : 'Facturas emitidas',
        ['Barrio', 'Periodo', 'Calles', 'Casas', 'Recaudado', 'Estado'],
        $filasFacturacion,
        ['Buscar' => $f_buscar ?: null, 'Total lotes' => count($filasFacturacion)],
        'Facturación por barrio'
    );

    if ($rolActual === 5) {
        $barriosStmt = $pdo->prepare(
            "SELECT b.id, b.nombre FROM barrios b
             JOIN detalles_encargado_barrio d ON d.barrio_id = b.id
             WHERE d.usuario_id = ? ORDER BY b.nombre"
        );
        $barriosStmt->execute([$user['id']]);
        $barriosFiltro = $barriosStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $barriosFiltro = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
    $title = $vistaFacturacion === 'pendientes' ? 'Lotes Pendientes de Facturación' : 'Facturas Emitidas';
    $header_title = $vistaFacturacion === 'pendientes' ? 'Lotes Pendientes de Facturación' : 'Facturas Emitidas';
    $header_subtitle = 'Consulta y gestiona la facturación por barrio.';
    ob_start();
    ?>
    <?= reporte_encabezado_impresion($header_title, $header_subtitle, ['Total lotes' => count($filasFacturacion)]) ?>
    <?= reporte_botonera() ?>
    <div class="card" style="padding:20px;">
        <div class="page-tabs no-print" style="margin-bottom:18px;">
            <a class="page-tab <?= $vistaFacturacion === 'pendientes' ? 'active' : '' ?>" href="router.php?page=<?= $pageCertificado ?>&vista=pendientes">⏳ Pendientes</a>
            <a class="page-tab <?= $vistaFacturacion === 'emitidas' ? 'active' : '' ?>" href="router.php?page=<?= $pageCertificado ?>&vista=emitidas">📄 Emitidas</a>
        </div>
        <form method="GET" action="router.php" class="unified-filter no-print">
            <input type="hidden" name="page" value="<?= $pageCertificado ?>">
            <input type="hidden" name="vista" value="<?= htmlspecialchars($vistaFacturacion) ?>">
            <div><label>Buscar barrio o calle</label><input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Nombre del barrio o calle"></div>
            <div><label>Barrio</label><select name="barrio_id"><option value="0">Todos</option><?php foreach ($barriosFiltro as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $f_barrio === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="router.php?page=<?= $pageCertificado ?>&vista=<?= urlencode($vistaFacturacion) ?>" class="btn-edit" style="text-decoration:none;">Limpiar</a>
        </form>
        <?php if (!$certificados): ?>
            <div style="padding:30px; text-align:center; color:#6B7280;">
                <div style="font-size:36px; margin-bottom:10px;">📄</div>
                <strong><?= $vistaFacturacion === 'pendientes' ? 'No hay lotes pendientes de facturación.' : 'No hay facturas emitidas.' ?></strong>
                <?php if ($vistaFacturacion === 'pendientes'): ?>
                    <div style="font-size:13px; margin-top:6px;">Los lotes aparecerán aquí cuando estén aprobados y tengan certificados generados.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
            <table data-page-size="10" data-no-tools="true">
                <thead><tr><th>Barrio</th><th>Periodo</th><th>Calles</th><th>Casas</th><th>Recaudado</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($certificadosPorLote as $loteCertificados):
                    $primero = $loteCertificados[0];
                    $enviadoBarrio = !empty($primero['facturas_enviadas_barrio']);
                    $estadoBarrio = $primero['barrio_estado'] ?: 'Aprobado';
                    $totalPagado = 0;
                    $totalCasas = 0;
                    foreach ($loteCertificados as $certificado) {
                        $totalPagado += (float)$certificado['monto_recolectado'];
                        $totalCasas += (int)$certificado['total_casas'];
                    }
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($primero['barrio_nombre']) ?></strong></td>
                        <td><?= (int)$primero['periodo_mes'] ?>/<?= (int)$primero['periodo_anio'] ?></td>
                        <td><?= count($loteCertificados) ?></td>
                        <td><?= $totalCasas ?></td>
                        <td><?= monto($totalPagado) ?></td>
                        <td>
                            <?php if ($puedeEnviarFacturas && $enviadoBarrio): ?>
                                <form method="POST" action="router.php?page=gestor_certificado_calle">
                                    <input type="hidden" name="page" value="gestor_certificado_calle">
                                    <input type="hidden" name="action" value="enviar_facturas_barrio">
                                    <input type="hidden" name="lote_id" value="<?= (int)$primero['lote_barrio_id'] ?>">
                                    <button type="submit" class="btn-primary" style="font-size:11px;" onclick="return confirm('¿Reenviar las facturas al encargado de barrio?')">🔁 Reenviar al encargado de barrio</button>
                                </form>
                            <?php elseif ($puedeEnviarFacturas && $estadoBarrio === 'Aprobado' && !empty($primero['lote_barrio_id'])): ?>
                                <form method="POST" action="router.php?page=gestor_certificado_calle">
                                    <input type="hidden" name="page" value="gestor_certificado_calle">
                                    <input type="hidden" name="action" value="enviar_facturas_barrio">
                                    <input type="hidden" name="lote_id" value="<?= (int)$primero['lote_barrio_id'] ?>">
                                    <button type="submit" class="btn-primary" style="font-size:11px;">📤 Enviar al encargado de barrio</button>
                                </form>
                            <?php endif; ?>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                <?php foreach ($loteCertificados as $certificado): ?>
                                    <a href="router.php?page=<?= $pageCertificado ?>&lote_calle_id=<?= (int)$certificado['id'] ?>" class="btn-edit" style="text-decoration:none;">Ver <?= htmlspecialchars($certificado['calle_nombre']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    return;
}

// Datos del lote de calle
$stmt = $pdo->prepare(
    "SELECT lc.*, c.nombre as calle_nombre, b.nombre as barrio_nombre, b.ciudad,
            ue.nombre as enc_nombre, ue.apellido as enc_apellido,
            (SELECT d.dni FROM detalles_encargado_calle d WHERE d.calle_id=c.id LIMIT 1) as enc_dni,
            ug.nombre as gestor_nombre, ug.apellido as gestor_apellido,
            lb.estado as barrio_estado, lb.gestor_id, lb.facturas_enviadas_barrio,
            rf.numero_recibo
     FROM lotes_calle lc
     JOIN calles c ON lc.calle_id = c.id
     JOIN barrios b ON lc.barrio_id = b.id
     LEFT JOIN usuarios ue ON lc.encargado_calle_id = ue.id
     LEFT JOIN lotes_barrio lb ON lc.lote_barrio_id = lb.id
     LEFT JOIN usuarios ug ON lb.gestor_id = ug.id
     LEFT JOIN recibos_finiquito rf ON rf.lote_barrio_id = lc.lote_barrio_id
     WHERE lc.id = ?"
);
$stmt->execute([$lote_calle_id]);
$lote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lote) { echo "<div class='alert alert-error'>Factura no encontrada.</div>"; return; }

if ($rolActual === 5) {
    $barrioAsignado = $pdo->prepare(
        "SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1"
    );
    $barrioAsignado->execute([$user['id']]);
    if ((int)$barrioAsignado->fetchColumn() !== (int)$lote['barrio_id']) {
        echo "<div class='alert alert-error'>No tienes permiso para consultar facturas de otro barrio.</div>";
        return;
    }
}

if ($rolActual === 6) {
    $calleAsignada = $pdo->prepare(
        "SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ? LIMIT 1"
    );
    $calleAsignada->execute([$user['id']]);
    if ((int)$calleAsignada->fetchColumn() !== (int)$lote['calle_id']) {
        echo "<div class='alert alert-error'>No tienes permiso para consultar facturas de otra calle.</div>";
        return;
    }
}

if (!$lote['certificado_generado']) {
    echo "<div class='alert alert-warning'>Esta factura aún no está disponible. Debe ser aprobado por el gestor primero.</div>";
    return;
}

// Viviendas del lote
$viviendasStmt = $pdo->prepare(
    "SELECT v.id, v.numero_casa as vivienda_nombre, v.direccion, v.estado_servicio,
            v.propietario, v.cedula, v.cedula_tipo, v.otro_identificador,
            c.id as cobro_id, c.monto, c.estado as cobro_estado,
            c.estado_verificacion, c.referencia_pago, c.comprobante_calle,
            c.fecha_emision
     FROM viviendas v
     LEFT JOIN cobros c ON c.vivienda_id = v.id
        AND c.mes = ? AND c.anio = ? AND c.lote_calle_id = ?
     WHERE v.calle_id = ? AND v.estado_servicio != 'Anulado'
     ORDER BY v.direccion ASC"
);
$viviendasStmt->execute([$lote['periodo_mes'], $lote['periodo_anio'], $lote_calle_id, $lote['calle_id']]);
$viviendas = $viviendasStmt->fetchAll(PDO::FETCH_ASSOC);

$num_certificado = sprintf(
    'CERT-%04d%02d-%03d-%04d',
    $lote['periodo_anio'], $lote['periodo_mes'],
    $lote['calle_id'], rand(1000, 9999)
);

$meses_nombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$title = 'Factura — ' . htmlspecialchars($lote['calle_nombre']);
$extra_css = '
body { background:#f3f4f6 !important; }
.invoice-document { border:1px solid #cbd5e1 !important; border-radius:0 !important; box-shadow:0 2px 8px rgba(15,23,42,.08) !important; color:#1f2937; }
.invoice-document .invoice-header { background:#24313d !important; border-radius:0 !important; padding:28px 34px !important; }
.invoice-document .invoice-header h1 { font-size:22px !important; letter-spacing:.5px !important; }
.invoice-document .invoice-header .numero { right:34px !important; top:28px !important; }
.invoice-document .invoice-header .stamp { display:none !important; }
.invoice-document .section-title { color:#24313d !important; border-bottom:1px solid #94a3b8 !important; letter-spacing:.7px !important; }
.invoice-document .data-box { background:#fff !important; border:1px solid #dbe2e8 !important; border-radius:0 !important; padding:10px 12px !important; }
.invoice-document .data-box label { color:#64748b !important; }
.invoice-document .data-box span { color:#1f2937 !important; }
.invoice-document .summary-box { background:#f8fafc !important; border:1px solid #dbe2e8 !important; border-radius:0 !important; }
.invoice-document h3 { color:#24313d !important; border-bottom:1px solid #94a3b8 !important; letter-spacing:.7px !important; }
.invoice-document [style*="background:#F9FAFB"] { background:#fff !important; border:1px solid #dbe2e8 !important; border-radius:0 !important; }
.invoice-document [style*="background:#EEF2F6"] { background:#f8fafc !important; border:1px solid #cbd5e1 !important; border-radius:0 !important; }
.invoice-document [style*="border:2px solid #A9C0D3"] { border:1px solid #94a3b8 !important; border-radius:0 !important; background:#f8fafc !important; }
.invoice-document [style*="color:#3E5C76"], .invoice-document [style*="color:#8A5A5A"], .invoice-document [style*="color:#7A6A4F"] { color:#334155 !important; }
.invoice-document table.viviendas { border:1px solid #cbd5e1; }
.invoice-document table.viviendas th { background:#e2e8f0 !important; color:#24313d !important; border-bottom:1px solid #94a3b8; }
.invoice-document table.viviendas td { border-bottom:1px solid #e5e7eb; }
.invoice-document .total-row { background:#f1f5f9 !important; color:#1f2937 !important; }
.invoice-document .invoice-footer { background:#f8fafc !important; border-top:1px solid #cbd5e1 !important; }
@media print {
  body { background:#fff !important; }
  .sidebar, .header, .notif-wrap, .menu-toggle, .sidebar-overlay, .no-print { display:none !important; }
  .main { margin-left:0 !important; padding:0 !important; }
  .cert { box-shadow:none !important; border-radius:0 !important; }
  .cert .header { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  table.viviendas th { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
';
ob_start();
?>
<div class="no-print" style="text-align:center; margin-bottom:20px;">
    <button class="btn-primary" onclick="window.print()">🖨️ Descargar PDF / Imprimir</button>
    <?php if ($puedeEnviarFacturas && !empty($lote['lote_barrio_id']) && !$lote['facturas_enviadas_barrio'] && $lote['barrio_estado'] === 'Aprobado'): ?>
        <form method="POST" action="router.php?page=gestor_certificado_calle" style="display:inline-block; margin-left:10px;">
            <input type="hidden" name="page" value="gestor_certificado_calle">
            <input type="hidden" name="action" value="enviar_facturas_barrio">
            <input type="hidden" name="lote_id" value="<?= (int)$lote['lote_barrio_id'] ?>">
            <button type="submit" class="btn-primary">📤 Enviar resumen al Barrio</button>
        </form>
    <?php elseif ($puedeEnviarFacturas && !empty($lote['facturas_enviadas_barrio'])): ?>
        <form method="POST" action="router.php?page=gestor_certificado_calle" style="display:inline-block; margin-left:10px;">
            <input type="hidden" name="page" value="gestor_certificado_calle">
            <input type="hidden" name="action" value="enviar_facturas_barrio">
            <input type="hidden" name="lote_id" value="<?= (int)$lote['lote_barrio_id'] ?>">
            <button type="submit" class="btn-primary" onclick="return confirm('¿Reenviar el resumen al encargado de barrio?')">🔁 Reenviar resumen al Barrio</button>
        </form>
    <?php endif; ?>
    <a href="javascript:history.back()" style="margin-left:15px; color:#6B7280; font-size:13px;">← Volver</a>
</div>

<div class="cert invoice-document" style="max-width:850px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.12); font-family:'Poppins',sans-serif;">
    <!-- HEADER -->
    <div class="header invoice-header" style="background:linear-gradient(135deg,#33485C 0%,#33485C 50%,#111827 100%); color:#fff; padding:35px 40px; position:relative;">
        <div style="font-size:11px; opacity:0.7; margin-bottom:8px;">SISTEMA DE GESTIÓN DE RECICLAJE — EPSIC / EcoReciclaje</div>
        <h1 style="margin:0; font-size:26px; font-weight:800; letter-spacing:1px;">FACTURA DE RECAUDACIÓN</h1>
        <div style="font-size:12px; opacity:0.8; margin-top:6px;">Documento que certifica la correcta recaudación del servicio de reciclaje</div>
        <div class="numero" style="position:absolute; right:30px; top:30px; text-align:right;">
            <div style="font-size:10px; opacity:0.6;">N° FACTURA</div>
            <div style="font-size:16px; font-weight:700;"><?= htmlspecialchars($num_certificado) ?></div>
        </div>
        <div class="stamp" style="position:absolute; right:30px; top:70px; width:70px; height:70px; border:3px solid rgba(255,255,255,0.3); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px;">✅</div>
    </div>

    <div style="padding:35px 40px;">
        <!-- DATOS GENERALES -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0; border-bottom:2px solid #33485C; padding-bottom:6px;">Datos del Periodo y Ubicación</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Barrio</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars($lote['barrio_nombre']) ?></span>
                </div>
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Calle</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars($lote['calle_nombre']) ?></span>
                </div>
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Ciudad</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars($lote['ciudad'] ?? '') ?></span>
                </div>
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Periodo de Facturación</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= $meses_nombres[$lote['periodo_mes']] ?> <?= $lote['periodo_anio'] ?></span>
                </div>
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Fecha de Emisión</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= $lote['fecha_certificado'] ? date('d/m/Y H:i', strtotime($lote['fecha_certificado'])) : date('d/m/Y H:i') ?></span>
                </div>
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Recibo de Finiquito</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars($lote['numero_recibo'] ?? '—') ?></span>
                </div>
            </div>
        </div>

        <!-- RESPONSABLES -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0; border-bottom:2px solid #33485C; padding-bottom:6px;">Responsables</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Encargado de Calle</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars(trim(($lote['enc_nombre'] ?? '') . ' ' . ($lote['enc_apellido'] ?? ''))) ?></span>
                    <div style="font-size:11px; color:#9CA3AF;">CI: <?= htmlspecialchars($lote['enc_dni'] ?? '—') ?></div>
                </div>
                <div style="background:#F9FAFB; padding:12px 14px; border-radius:8px;">
                    <label style="font-size:10px; color:#9CA3AF; font-weight:600; display:block; margin-bottom:3px; text-transform:uppercase;">Verificado por (Gestor/Admin)</label>
                    <span style="font-size:14px; font-weight:700; color:#111827;"><?= htmlspecialchars(trim(($lote['gestor_nombre'] ?? '') . ' ' . ($lote['gestor_apellido'] ?? ''))) ?></span>
                </div>
            </div>
        </div>

        <!-- RESUMEN DE RECAUDACIÓN -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0; border-bottom:2px solid #33485C; padding-bottom:6px;">Resumen de Recaudación</h3>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:16px 0;">
                <div style="text-align:center; padding:16px 8px; border-radius:10px; background:#F9FAFB; border:1px solid #E5E7EB;">
                    <div style="font-size:28px; font-weight:800; color:#6B7280;"><?= $lote['total_casas'] ?></div>
                    <div style="font-size:10px; color:#6B7280; margin-top:4px; text-transform:uppercase;">Casas Activas</div>
                </div>
                <div style="text-align:center; padding:16px 8px; border-radius:10px; background:#F9FAFB; border:1px solid #E5E7EB;">
                    <div style="font-size:28px; font-weight:800; color:#3E5C76;"><?= $lote['casas_pagadas'] ?></div>
                    <div style="font-size:10px; color:#6B7280; margin-top:4px; text-transform:uppercase;">Pagadas</div>
                </div>
                <div style="text-align:center; padding:16px 8px; border-radius:10px; background:#F9FAFB; border:1px solid #E5E7EB;">
                    <div style="font-size:28px; font-weight:800; color:<?= $lote['casas_morosas']>0?'#8A5A5A':'#9CA3AF' ?>;"><?= $lote['casas_morosas'] ?></div>
                    <div style="font-size:10px; color:#6B7280; margin-top:4px; text-transform:uppercase;">Morosas</div>
                </div>
                <div style="text-align:center; padding:16px 8px; border-radius:10px; background:#F9FAFB; border:1px solid #E5E7EB;">
                    <div style="font-size:28px; font-weight:800; color:#33485C;"><?= monto($lote['monto_recolectado']) ?></div>
                    <div style="font-size:10px; color:#6B7280; margin-top:4px; text-transform:uppercase;">Recolectado</div>
                </div>
            </div>
            <div style="border:2px solid #A9C0D3; border-radius:10px; padding:16px; background:#EEF2F6; margin:16px 0; text-align:center;">
                <div style="font-size:12px; color:#33485C;">MONTO ESPERADO</div>
                <div style="font-size:24px; font-weight:800; color:#33485C;"><?= monto($lote['monto_esperado']) ?></div>
                <div style="font-size:12px; color:#33485C; margin-top:4px;">
                    Diferencia: <?= monto($lote['monto_esperado'] - $lote['monto_recolectado']) ?>
                    <?php if ($lote['alerta_deuda']): ?>
                        <span style="color:#7A6A4F;"> ⚠️ Alerta de deuda</span>
                    <?php else: ?>
                        <span style="color:#3E5C76;"> ✅ Sin novedad</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DETALLE POR VIVIENDA -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0; border-bottom:2px solid #33485C; padding-bottom:6px;">Detalle de Viviendas (<?= count($viviendas) ?> registros)</h3>
            <div class="table-wrap">
            <table class="viviendas" style="width:100%; border-collapse:collapse; font-size:11px; margin-top:10px;">
                <thead>
                    <tr>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">#</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Dirección</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Propietario</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Servicio</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Estado Pago</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Verificación</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Referencia</th>
                        <th style="padding:8px 10px; background:#33485C; color:#fff; text-align:right; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total_monto = 0; $i = 0; foreach ($viviendas as $v): $i++; ?>
                    <tr style="<?= $v['cobro_estado']==='Pagado'?'background:#EEF2F6;':'' ?>">
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6;"><?= $i ?></td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6;"><strong><?= htmlspecialchars($v['direccion']) ?></strong></td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6;">
                            <?= htmlspecialchars($v['propietario'] ?? '—') ?>
                            <?php $identificacion = formato_identificacion($v['cedula'] ?? '', $v['cedula_tipo'] ?? 'V', $v['otro_identificador'] ?? ''); ?>
                            <?php if ($identificacion !== ''): ?><div style="font-size:9px; color:#6B7280;"><?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                        </td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6;">
                            <?php if ($v['estado_servicio']==='Activo'): ?>
                                <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; background:#E9EFF4; color:#33485C;">Activo</span>
                            <?php elseif ($v['estado_servicio']==='Suspendido'): ?>
                                <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; background:#F2EEE7; color:#6E5F45;">Suspendido</span>
                            <?php else: ?>
                                <span><?= $v['estado_servicio'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6;">
                            <?php if ($v['cobro_estado']==='Pagado'): ?>
                                <span style="color:#3E5C76; font-weight:700;">✅ Pagado</span>
                                <?php if ($v['fecha_emision']): ?>
                                    <div style="font-size:9px; color:#9CA3AF;"><?= date('d/m/Y', strtotime($v['fecha_emision'])) ?></div>
                                <?php endif; ?>
                            <?php elseif ($v['cobro_estado']==='Pendiente'): ?>
                                <span style="color:#7A6A4F; font-weight:700;">⏳ Pendiente</span>
                            <?php else: ?>
                                <span style="color:#7A6A4F; font-weight:700;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6;">
                            <?php if ($v['estado_verificacion']==='Verificado'): ?>
                                <span style="color:#3E5C76;">✅ Verif.</span>
                            <?php elseif ($v['estado_verificacion']==='Rechazado'): ?>
                                <span style="color:#8A5A5A;">❌ Rechaz.</span>
                            <?php else: ?>
                                <span style="color:#9CA3AF;">⏳ Pend.</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6; font-size:10px; color:#6B7280;"><?= htmlspecialchars($v['referencia_pago'] ?? '—') ?></td>
                        <td style="padding:7px 10px; border-bottom:1px solid #F3F4F6; text-align:right; font-weight:700;">
                            <?php if ($v['monto']): ?><?= monto($v['monto']) ?><?php endif; ?>
                        </td>
                    </tr>
                    <?php $total_monto += (float)($v['monto'] ?? 0); endforeach; ?>
                    <tr style="border-top:2px solid #33485C; background:#EEF2F6;">
                        <td colspan="7" style="padding:10px; font-weight:800; text-align:right;">TOTAL RECAUDADO</td>
                        <td style="padding:10px; font-weight:800; color:#33485C; font-size:15px; text-align:right;"><?= monto($total_monto) ?></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <!-- DECLARACIÓN -->
        <div style="margin-bottom:28px;">
            <h3 style="font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0; border-bottom:2px solid #33485C; padding-bottom:6px;">Declaración de Verificación</h3>
            <div style="background:#F9FAFB; padding:16px 20px; border-radius:10px; border-left:4px solid #33485C; font-size:12px; color:#4B5563; line-height:1.8;">
                El gestor de pagos, en virtud de las facultades conferidas por el Sistema de Gestión de Reciclaje
                EPSIC / EcoReciclaje, <strong>CERTIFICA</strong> que la recaudación del servicio de reciclaje
                correspondiente a la calle <strong><?= htmlspecialchars($lote['calle_nombre']) ?></strong> del barrio
                <strong><?= htmlspecialchars($lote['barrio_nombre']) ?></strong>, durante el periodo de
                <strong><?= $meses_nombres[$lote['periodo_mes']] ?> de <?= $lote['periodo_anio'] ?></strong>,
                ha sido debidamente verificada y aprobada, encontrándose conforme a los registros del sistema.
                <br><br>
                Se deja constancia que <strong><?= $lote['casas_pagadas'] ?> de <?= $lote['total_casas'] ?></strong>
                viviendas cumplieron con el pago oportuno, y
                <?php if ($lote['casas_morosas'] > 0): ?>
                    <strong><?= $lote['casas_morosas'] ?></strong> viviendas fueron registradas como morosas,
                    procediéndose a la suspensión temporal del servicio según el reglamento interno.
                <?php else: ?>
                    todas las viviendas cumplieron con el pago en el periodo establecido.
                <?php endif; ?>
            </div>
        </div>

        <!-- FIRMAS -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:50px;">
            <div style="text-align:center; padding-top:40px; border-top:2px solid #E5E7EB;">
                <div style="font-size:11px; color:#6B7280;">Encargado de Calle</div>
                <div style="font-size:13px; font-weight:700; margin-top:4px;"><?= htmlspecialchars(trim(($lote['enc_nombre'] ?? '') . ' ' . ($lote['enc_apellido'] ?? ''))) ?></div>
                <div style="font-size:10px; color:#9CA3AF;">DNI: <?= htmlspecialchars($lote['enc_dni'] ?? '—') ?></div>
            </div>
            <div style="text-align:center; padding-top:40px; border-top:2px solid #E5E7EB;">
                <div style="font-size:11px; color:#6B7280;">Gestor / Administrador</div>
                <div style="font-size:13px; font-weight:700; margin-top:4px;"><?= htmlspecialchars(trim(($lote['gestor_nombre'] ?? '') . ' ' . ($lote['gestor_apellido'] ?? ''))) ?></div>
            </div>
        </div>
    </div>

    <div class="invoice-footer" style="background:#F9FAFB; padding:20px 40px; text-align:center; font-size:10px; color:#9CA3AF; border-top:1px solid #E5E7EB;">
        Esta factura contiene el detalle oficial de la recaudación por calle.<br>
        Generado automáticamente por el Sistema EcoReciclaje — Emitido el <?= date('d/m/Y H:i') ?>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
