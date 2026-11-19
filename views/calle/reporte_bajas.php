<?php
// views/calle/reporte_bajas.php
$user = check_dashboard_access([6]);
$sid = $_SESSION['active_sid'] ?? '';

// 1. Obtener la calle asignada
$calleStmt = $pdo->prepare("SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ?");
$calleStmt->execute([$user['id']]);
$calle_id = $calleStmt->fetchColumn();

$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// 2. Obtener solicitudes de baja ya confirmadas (Estado 'Aprobado')
$sql = "SELECT s.*, v.propietario, v.cedula as v_cedula, v.cedula_tipo as v_cedula_tipo,
               v.otro_identificador as v_otro_identificador, v.numero_casa, v.direccion,
               COALESCE((SELECT SUM(cobro.monto)
                         FROM cobros cobro
                         WHERE cobro.vivienda_id = v.id AND cobro.estado NOT IN ('Pagado', 'Anulado')), 0) AS deuda_actual,
               COALESCE(cfg.multa_renovacion, 5.00) AS multa_renovacion
        FROM solicitudes_vivienda s
        JOIN viviendas v ON s.vivienda_id = v.id
        LEFT JOIN configuraciones_barrio cfg ON cfg.barrio_id = v.barrio_id
        WHERE s.calle_id = :calle AND s.tipo = 'Baja' AND s.estado = 'Aprobado' AND v.estado_servicio = 'Anulado'
        AND v.id NOT IN (SELECT vivienda_id FROM solicitudes_vivienda WHERE tipo = 'Renovacion' AND estado = 'Pendiente' AND vivienda_id IS NOT NULL)";
$params = [':calle' => $calle_id];
if ($f_buscar !== '') {
    $sql .= " AND (v.propietario LIKE :q_propietario OR v.cedula LIKE :q_cedula OR v.otro_identificador LIKE :q_otro
                   OR v.numero_casa LIKE :q_casa OR v.direccion LIKE :q_direccion)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
    $params[':q_direccion'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sql .= " AND DATE(s.fecha_revision) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(s.fecha_revision) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= " GROUP BY v.id ORDER BY s.fecha_revision DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bajas_confirmadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$calleNombreStmt = $pdo->prepare("SELECT nombre FROM calles WHERE id = ?");
$calleNombreStmt->execute([$calle_id]);
$calleNombre = (string)($calleNombreStmt->fetchColumn() ?: '');

// Exportación del listado filtrado.
$metaBajas = [
    'Calle' => $calleNombre !== '' ? $calleNombre : null,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasBajas = [];
$totalDeudaBajas = 0.0;
foreach ($bajas_confirmadas as $b) {
    $totalDeudaBajas += (float)$b['monto_deuda'];
    $filasBajas[] = [
        !empty($b['fecha_revision']) ? date('d/m/Y H:i', strtotime($b['fecha_revision'])) : '',
        '#' . ($b['numero_casa'] ?? ''),
        $b['propietario'] ?? '',
        formato_identificacion($b['v_cedula'] ?? '', $b['v_cedula_tipo'] ?? 'V', $b['v_otro_identificador'] ?? ''),
        $b['direccion'] ?? '',
        monto((float)$b['monto_deuda']),
        $b['detalles_deuda'] ?? '',
        'Solicitud #' . $b['id'],
    ];
}
reporte_exportar_si_solicitado(
    'reporte_bajas_calle',
    'Reporte de bajas de la calle',
    ['Fecha de baja', 'Casa', 'Propietario', 'Identificación', 'Dirección', 'Deuda final', 'Conceptos pendientes', 'Solicitud'],
    $filasBajas,
    array_merge($metaBajas, ['Total bajas' => count($filasBajas), 'Deuda total' => monto($totalDeudaBajas)]),
    'Viviendas retiradas del servicio y estado de cuenta final'
);

$title = "Reporte de Bajas - EcoReciclaje";
$header_title = "Historial de Retiro";
$header_subtitle = "Registro de viviendas que ya no cuentan con el servicio.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Reporte de bajas de la calle', 'Viviendas retiradas del servicio y estado de cuenta final', array_merge($metaBajas, ['Total bajas' => count($bajas_confirmadas), 'Deuda total' => monto($totalDeudaBajas)])) ?>
    <?= reporte_botonera() ?>

    <style>
    .renovar-action { display:inline-flex; min-height:42px; align-items:center; justify-content:center; gap:7px; padding:9px 15px; border:0; border-radius:8px; color:#fff; background:#176b87; font-size:12px; font-weight:700; cursor:pointer; box-shadow:0 2px 5px rgba(14,79,104,.16); transition:background .15s, transform .15s; }
    .renovar-action:hover { background:#0e4f68; transform:translateY(-1px); }
    .renovar-modal[hidden] { display:none; }
    .renovar-modal { position:fixed; z-index:10000; inset:0; display:grid; place-items:center; overflow-y:auto; padding:20px; background:rgba(17,33,43,.62); }
    .renovar-modal-panel { width:min(100%,560px); overflow:hidden; border-radius:14px; background:#fff; box-shadow:0 24px 70px rgba(0,0,0,.24); }
    .renovar-modal-header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; padding:20px 22px; color:#fff; background:linear-gradient(120deg,#176b87,#0e4f68); }
    .renovar-modal-header h2 { margin:0 0 5px; font-size:19px; }
    .renovar-modal-header p { margin:0; color:rgba(255,255,255,.82); font-size:12px; }
    .renovar-close { border:0; padding:3px 7px; color:#fff; background:transparent; font-size:24px; cursor:pointer; }
    .renovar-modal-body { padding:22px; }
    .renovar-amounts { display:grid; gap:8px; margin-bottom:18px; padding:14px; border:1px solid #d9e7ec; border-radius:9px; background:#f3f8fa; }
    .renovar-amount-line { display:flex; justify-content:space-between; gap:14px; color:#455765; font-size:13px; }
    .renovar-amount-total { margin-top:4px; padding-top:10px; border-top:1px solid #d9e1e7; color:#0e4f68; font-size:16px; font-weight:800; }
    .renovar-field { margin-bottom:15px; }
    .renovar-field label { display:block; margin-bottom:6px; color:#263746; font-size:13px; font-weight:700; }
    .renovar-field input { width:100%; min-height:44px; padding:10px 12px; border:1px solid #cbd6de; border-radius:8px; }
    .renovar-file { padding:9px; background:#f8fafb; }
    .renovar-hint { display:block; margin-top:5px; color:#657384; font-size:11px; }
    .renovar-preview { display:none; max-width:100%; max-height:190px; margin-top:10px; border:1px solid #d9e1e7; border-radius:8px; object-fit:contain; }
    .renovar-modal-actions { display:flex; justify-content:flex-end; gap:9px; padding-top:6px; }
    .renovar-cancel { min-height:42px; padding:9px 15px; border:1px solid #cbd6de; border-radius:8px; color:#455765; background:#fff; font-weight:600; cursor:pointer; }
    .renovar-submit { min-height:42px; padding:9px 16px; border:0; border-radius:8px; color:#fff; background:#176b87; font-weight:700; cursor:pointer; }
    .renovar-submit:hover { background:#0e4f68; }
    @media(max-width:600px){.renovar-modal{padding:10px}.renovar-modal-body{padding:17px}.renovar-modal-actions{flex-direction:column-reverse}.renovar-modal-actions button{width:100%}}
    </style>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(200px,1.5fr) repeat(2,minmax(140px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="reporte_bajas">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, casa..."
                   style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div style="display:flex; gap:6px; align-items:center;">
            <button type="submit" class="btn-primary" style="padding:9px 12px;">Filtrar</button>
            <a href="router.php?page=reporte_bajas" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">📉 Bajas Confirmadas</h3>
            <span class="badge no-print" style="background:#F3F4F6; color:#374151;"><?= count($bajas_confirmadas) ?> baja(s)</span>
        </div>
        
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha Ejecución</th>
                        <th style="padding: 12px;">Vivienda</th>
                        <th style="padding: 12px; text-align: right;">Deuda al Retiro</th>
                        <th style="padding: 12px;">Estado Sistema</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($bajas_confirmadas)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #9CA3AF; padding: 40px;">No hay registros de bajas confirmadas que coincidan con los filtros.</td></tr>
                    <?php endif; ?>
                    <?php foreach($bajas_confirmadas as $b): ?>
                        <tr style="border-bottom: 1px solid #F3F4F6;">
                            <td style="padding: 12px;">
                                <div style="font-weight: 600;"><?= date('d/m/Y', strtotime($b['fecha_revision'])) ?></div>
                                <div style="font-size: 10px; color: #9CA3AF;"><?= date('H:i', strtotime($b['fecha_revision'])) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($b['propietario']) ?></div>
                                <?php $identificacion = formato_identificacion($b['v_cedula'] ?? '', $b['v_cedula_tipo'] ?? 'V', $b['v_otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 10px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: #6B7280;">Solicitud #<?= $b['id'] ?></div>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <div style="font-weight: 800; color: #7A4F4F;"><?= monto($b['monto_deuda']) ?></div>
                                <div style="font-size: 9px; color: #6B7280;"><?= htmlspecialchars($b['detalles_deuda']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                    $deudaRenovacion = max(0, (float)$b['deuda_actual']);
                                    $multaRenovacion = max(0, (float)$b['multa_renovacion']);
                                ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge" style="background: #F4EDED; color: #7A4F4F; border:none; font-size: 10px;">ANULADO</span>
                                    <button type="button" class="renovar-action"
                                            data-vivienda="<?= (int)$b['vivienda_id'] ?>"
                                            data-propietario="<?= htmlspecialchars($b['propietario'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-casa="<?= htmlspecialchars($b['numero_casa'] ?: '?', ENT_QUOTES, 'UTF-8') ?>"
                                            data-deuda="<?= htmlspecialchars(number_format($deudaRenovacion, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-multa="<?= htmlspecialchars(number_format($multaRenovacion, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                                        🔄 Iniciar renovación
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="renovar-modal" id="renovacionModal" role="dialog" aria-modal="true" aria-labelledby="renovacionModalTitle" hidden>
        <div class="renovar-modal-panel">
            <div class="renovar-modal-header">
                <div>
                    <h2 id="renovacionModalTitle">🔄 Renovar servicio</h2>
                    <p id="renovacionCliente"></p>
                </div>
                <button type="button" class="renovar-close" aria-label="Cerrar" data-renovacion-cerrar>&times;</button>
            </div>
            <form method="POST" action="router.php?page=reporte_bajas" enctype="multipart/form-data" id="renovacionForm">
                <input type="hidden" name="sid" value="<?= htmlspecialchars($sid) ?>">
                <input type="hidden" name="form_type" value="solicitar_renovacion">
                <input type="hidden" name="vivienda_id" id="renovacionViviendaId">
                <div class="renovar-modal-body">
                    <div class="renovar-amounts" aria-live="polite">
                        <div class="renovar-amount-line"><span>Deuda pendiente</span><strong id="renovacionDeuda"><?= monto(0) ?></strong></div>
                        <div class="renovar-amount-line"><span>Multa de renovación</span><strong id="renovacionMulta"><?= monto(0) ?></strong></div>
                        <div class="renovar-amount-line renovar-amount-total"><span>Total a pagar</span><strong id="renovacionTotal"><?= monto(0) ?></strong></div>
                    </div>
                    <p style="margin:0 0 17px; color:#657384; font-size:12px; line-height:1.5;">
                        Confirma el pago del monto indicado y adjunta su referencia y comprobante. El encargado de barrio revisará la solicitud antes de reactivar el servicio.
                    </p>
                    <div class="renovar-field">
                        <label for="renovacionReferencia">Número de referencia del pago</label>
                        <input type="text" name="referencia_pago" id="renovacionReferencia" maxlength="255" required placeholder="Ej.: número de operación o voucher">
                    </div>
                    <div class="renovar-field">
                        <label for="renovacionComprobante">Imagen del comprobante</label>
                        <input class="renovar-file" type="file" name="comprobante_pago" id="renovacionComprobante" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <span class="renovar-hint">Formatos JPG, PNG, GIF o WEBP. Máximo 5 MB.</span>
                        <img id="renovacionPreview" class="renovar-preview" alt="Vista previa del comprobante">
                    </div>
                    <div class="renovar-modal-actions">
                        <button type="button" class="renovar-cancel" data-renovacion-cerrar>Cancelar</button>
                        <button type="submit" class="renovar-submit">Enviar pago y solicitar renovación</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    (() => {
        const modal = document.getElementById('renovacionModal');
        const form = document.getElementById('renovacionForm');
        const referencia = document.getElementById('renovacionReferencia');
        const comprobante = document.getElementById('renovacionComprobante');
        const preview = document.getElementById('renovacionPreview');
        if (!modal || !form || !referencia || !comprobante || !preview) return;

        const cerrar = () => {
            modal.hidden = true;
            document.body.style.overflow = '';
        };
        document.querySelectorAll('[data-renovacion-cerrar]').forEach((boton) => boton.addEventListener('click', cerrar));
        modal.addEventListener('click', (event) => {
            if (event.target === modal) cerrar();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) cerrar();
        });
        document.querySelectorAll('.renovar-action').forEach((boton) => boton.addEventListener('click', () => {
            const deuda = Number(boton.dataset.deuda || 0);
            const multa = Number(boton.dataset.multa || 0);
            document.getElementById('renovacionViviendaId').value = boton.dataset.vivienda || '';
            document.getElementById('renovacionCliente').textContent =
                `${boton.dataset.propietario || 'Cliente'} · Casa #${boton.dataset.casa || '?'}`;
            document.getElementById('renovacionDeuda').textContent = fmtMonto(deuda);
            document.getElementById('renovacionMulta').textContent = fmtMonto(multa);
            document.getElementById('renovacionTotal').textContent = fmtMonto(deuda + multa);
            form.reset();
            document.getElementById('renovacionViviendaId').value = boton.dataset.vivienda || '';
            comprobante.setCustomValidity('');
            preview.removeAttribute('src');
            preview.style.display = 'none';
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            referencia.focus();
        }));
        comprobante.addEventListener('change', () => {
            const archivo = comprobante.files && comprobante.files[0];
            if (!archivo) {
                preview.removeAttribute('src');
                preview.style.display = 'none';
                return;
            }
            if (!archivo.type.startsWith('image/') || archivo.size > 5 * 1024 * 1024) {
                comprobante.setCustomValidity('Selecciona una imagen válida de hasta 5 MB.');
                comprobante.reportValidity();
                comprobante.value = '';
                preview.removeAttribute('src');
                preview.style.display = 'none';
                return;
            }
            comprobante.setCustomValidity('');
            preview.src = URL.createObjectURL(archivo);
            preview.style.display = 'block';
        });
    })();
    </script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
