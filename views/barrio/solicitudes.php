<?php
// views/barrio/solicitudes.php
$user = check_dashboard_access([5]);

// Filtros
$f_estado = (string)($_GET['estado'] ?? 'Pendiente');
$estadosPermitidos = ['Pendiente', 'Aprobado', 'Rechazado', 'Todos'];
if (!in_array($f_estado, $estadosPermitidos, true)) $f_estado = 'Pendiente';
$f_buscar = trim((string)($_GET['search'] ?? ''));
$f_desde = trim((string)($_GET['desde'] ?? ''));
$f_hasta = trim((string)($_GET['hasta'] ?? ''));

// 1. Obtener solicitudes de registro (altas) de las calles de su barrio.
// Las bajas y renovaciones se gestionan en sus propias bandejas.
$sql = "SELECT s.*, c.nombre as calle_nombre, u.nombre as solicitante_nombre,
               u.apellido as solicitante_apellido, b.nombre as barrio_nombre,
               ec.nombre as encargado_nombre, ec.apellido as encargado_apellido,
               det_calle.telefono as encargado_telefono
        FROM solicitudes_vivienda s 
        JOIN calles c ON s.calle_id = c.id
        JOIN barrios b ON c.barrio_id = b.id
        JOIN usuarios u ON s.creado_por = u.id
        JOIN detalles_encargado_barrio deb ON c.barrio_id = deb.barrio_id
        LEFT JOIN detalles_encargado_calle det_calle ON det_calle.calle_id = s.calle_id
        LEFT JOIN usuarios ec ON det_calle.usuario_id = ec.id
        WHERE deb.usuario_id = :usuario AND s.tipo = 'Alta'";
$params = [':usuario' => $user['id']];
if ($f_estado !== 'Todos') {
    $sql .= " AND s.estado = :estado";
    $params[':estado'] = $f_estado;
}
if ($f_buscar !== '') {
    $sql .= " AND (s.propietario LIKE :q_propietario OR s.cedula LIKE :q_cedula OR s.otro_identificador LIKE :q_otro
                   OR s.numero_casa LIKE :q_casa OR c.nombre LIKE :q_calle OR u.nombre LIKE :q_solicitante OR u.apellido LIKE :q_apellido)";
    $params[':q_propietario'] = '%' . $f_buscar . '%';
    $params[':q_cedula'] = '%' . $f_buscar . '%';
    $params[':q_otro'] = '%' . $f_buscar . '%';
    $params[':q_casa'] = '%' . $f_buscar . '%';
    $params[':q_calle'] = '%' . $f_buscar . '%';
    $params[':q_solicitante'] = '%' . $f_buscar . '%';
    $params[':q_apellido'] = '%' . $f_buscar . '%';
}
if ($f_desde !== '') {
    $sql .= " AND DATE(s.fecha_creacion) >= :desde";
    $params[':desde'] = $f_desde;
}
if ($f_hasta !== '') {
    $sql .= " AND DATE(s.fecha_creacion) <= :hasta";
    $params[':hasta'] = $f_hasta;
}
$sql .= " ORDER BY s.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datos para exportación.
$barrioNombre = '';
if (!empty($solicitudes)) $barrioNombre = (string)($solicitudes[0]['barrio_nombre'] ?? '');
$metaSolicitudes = [
    'Barrio' => $barrioNombre !== '' ? $barrioNombre : null,
    'Estado' => $f_estado,
    'Buscar' => $f_buscar !== '' ? $f_buscar : null,
    'Desde' => $f_desde !== '' ? $f_desde : null,
    'Hasta' => $f_hasta !== '' ? $f_hasta : null,
];
$filasSolicitudes = [];
foreach ($solicitudes as $s) {
    $propietario = $s['propietario'] ?? '';
    $casa = $s['numero_casa'] ?? '';
    $identificacion = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? '');
    $filasSolicitudes[] = [
        date('d/m/Y H:i', strtotime($s['fecha_creacion'])),
        $s['tipo'],
        $s['estado'],
        $s['calle_nombre'] ?? '',
        '#' . $casa,
        $propietario,
        $identificacion,
        trim(($s['solicitante_nombre'] ?? '') . ' ' . ($s['solicitante_apellido'] ?? '')),
    ];
}
reporte_exportar_si_solicitado(
    'solicitudes_registro_barrio',
    'Solicitudes de registro del barrio',
    ['Fecha', 'Tipo', 'Estado', 'Calle', 'Casa', 'Propietario', 'Identificación', 'Solicitante'],
    $filasSolicitudes,
    array_merge($metaSolicitudes, ['Total solicitudes' => count($filasSolicitudes)]),
    'Altas de viviendas enviadas por los encargados de calle'
);

$title = "Solicitudes de Registro - EcoReciclaje";
$header_title = "Solicitudes de Registro";
$header_subtitle = "Revisa y aprueba las solicitudes de alta de viviendas enviadas por los encargados de calle.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <style>
    .sol-actions { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
    .sol-btn { display:inline-flex; align-items:center; justify-content:center; gap:5px; min-height:32px; padding:6px 12px; border:1px solid transparent; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; text-decoration:none; transition:background .15s, border-color .15s; }
    .sol-btn-detalle { color:#4B5563; background:#F3F4F6; border-color:#E5E7EB; }
    .sol-btn-detalle:hover { background:#E5E7EB; }
    .sol-btn-ok { color:#fff; background:#111827; }
    .sol-btn-ok:hover { background:#000; }
    .sol-btn-no { color:#374151; background:#F3F4F6; border-color:#E5E7EB; }
    .sol-btn-no:hover { background:#E5E7EB; }
    .sol-modal[hidden] { display:none; }
    .sol-modal { position:fixed; z-index:10000; inset:0; display:grid; place-items:center; overflow-y:auto; padding:20px; background:rgba(0,0,0,.55); }
    .sol-modal-panel { width:min(100%,840px); max-height:calc(100vh - 40px); display:flex; flex-direction:column; overflow:hidden; border-top:4px solid #D98B35; border-radius:14px; background:#fff; box-shadow:0 24px 70px rgba(0,0,0,.28); }
    .sol-modal-header { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; padding:20px 24px; color:#1F2937; background:#FFFDFA; border-bottom:1px solid #EFE8DE; }
    .sol-modal-header h2 { margin:0; color:#1F2937; font-size:18px; line-height:1.3; }
    .sol-modal-sub { margin:4px 0 0; color:#8A8177; font-size:11px; }
    .sol-close { border:0; padding:2px 9px; border-radius:6px; color:#6B7280; background:transparent; font-size:26px; line-height:1; cursor:pointer; }
    .sol-close:hover { color:#111827; background:#F3F4F6; }
    .sol-modal-body { padding:22px; overflow-y:auto; }
    .sol-section { margin-bottom:18px; }
    .sol-section:last-child { margin-bottom:0; }
    .sol-section h3 { margin:0 0 10px; padding-bottom:7px; border-bottom:1px solid #EEF1F4; color:#1F2937; font-size:13px; font-weight:800; }
    .sol-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px 20px; }
    .sol-field { min-width:0; }
    .sol-label { display:block; margin-bottom:3px; color:#9CA3AF; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .sol-value { display:block; color:#1F2937; font-size:12px; line-height:1.45; word-break:break-word; }
    .sol-value.sol-muted { color:#9CA3AF; }
    .sol-modal-footer { display:flex; justify-content:flex-end; gap:8px; padding:14px 24px; border-top:1px solid #EEF1F4; background:#FBFCFD; }
    .sol-modal-footer button { padding:8px 16px; border:1px solid #CBD5E1; border-radius:7px; color:#334155; background:#fff; font-size:12px; font-weight:700; cursor:pointer; }
    .sol-modal-footer button:hover { background:#F1F5F9; }
    @media(max-width:760px) {
        .sol-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:620px) {
        .sol-modal { padding:10px; }
        .sol-modal-panel { max-height:calc(100vh - 20px); }
        .sol-modal-header { padding:17px; }
        .sol-modal-body { padding:17px; }
        .sol-modal-footer { padding:12px 17px; }
        .sol-grid { grid-template-columns:1fr; }
        .sol-actions { flex-direction:column; }
        .sol-btn { width:100%; }
    }
    </style>

    <?= reporte_encabezado_impresion('Solicitudes de registro', 'Altas de viviendas enviadas por los encargados de calle', array_merge($metaSolicitudes, ['Total solicitudes' => count($solicitudes)])) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:grid; grid-template-columns:minmax(180px,1.4fr) repeat(3,minmax(115px,1fr)) auto; gap:10px; align-items:end;">
        <input type="hidden" name="page" value="solicitudes">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Propietario, cédula, calle..."
                   style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <div>
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Estado</label>
            <select name="estado" style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
                <?php foreach ($estadosPermitidos as $estadoOpcion): ?>
                    <option value="<?= $estadoOpcion ?>" <?= $f_estado === $estadoOpcion ? 'selected' : '' ?>><?= $estadoOpcion ?></option>
                <?php endforeach; ?>
            </select>
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
            <a href="router.php?page=solicitudes" style="font-size:11px; color:#6B7280; text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Vivienda / Propietario</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($solicitudes)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #9CA3AF; padding: 40px;">No tienes solicitudes de registro pendientes por procesar.</td></tr>
                    <?php endif; ?>
                    <?php foreach($solicitudes as $s): ?>
                        <tr id="solicitud-<?= (int)$s['id'] ?>" style="border-bottom: 1px solid #F3F4F6;<?= (int)($_GET['solicitud_id'] ?? 0) === (int)$s['id'] ? 'background:#FFF7ED;' : '' ?>">
                            <td style="padding: 12px;">
                                <div style="font-size: 11px; color: #6B7280;"><?= date('d/m/Y', strtotime($s['fecha_creacion'])) ?></div>
                                <div style="font-size:10px; color:#6B7280; margin-top:3px;"><?= htmlspecialchars($s['estado']) ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($s['propietario']) ?></div>
                                <div style="font-size: 11px; color: #6B7280;">Calle: <?= htmlspecialchars($s['calle_nombre']) ?> | Casa: <?= htmlspecialchars($s['numero_casa']) ?></div>
                                <?php $identificacion = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? ''); ?>
                                <?php if ($identificacion !== ''): ?>
                                    <div style="font-size: 11px; color: #6B7280;">ID: <?= htmlspecialchars($identificacion) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div class="sol-actions">
                                    <button type="button" class="sol-btn sol-btn-detalle" data-sol-open="solModal-<?= (int)$s['id'] ?>">
                                        🔍 Detalles
                                    </button>
                                    <?php if ($s['estado'] === 'Pendiente'): ?>
                                        <form method="POST" onsubmit="return confirm('¿Aprobar esta solicitud?')">
                                            <input type="hidden" name="form_type" value="procesar_solicitud">
                                            <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="estado" value="Aprobado">
                                            <button type="submit" class="sol-btn sol-btn-ok">Aprobar</button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('¿Rechazar esta solicitud?')">
                                            <input type="hidden" name="form_type" value="procesar_solicitud">
                                            <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="estado" value="Rechazado">
                                            <button type="submit" class="sol-btn sol-btn-no">Rechazar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($solicitudes as $s): ?>
        <?php
            $idSolicitud = (int)($s['id'] ?? 0);
            $propietarioDet = trim((string)($s['propietario'] ?? ''));
            $propietarioDet = $propietarioDet !== '' ? $propietarioDet : 'No registrado';
            $casaDet = trim((string)($s['numero_casa'] ?? ''));
            $casaDet = $casaDet !== '' ? $casaDet : 'S/N';
            $identificacionDet = formato_identificacion($s['cedula'] ?? '', $s['cedula_tipo'] ?? 'V', $s['otro_identificador'] ?? '');
            $referenciaDet = trim((string)($s['referencia'] ?? ''));
            $encargadoDet = trim((string)($s['encargado_nombre'] ?? '') . ' ' . (string)($s['encargado_apellido'] ?? ''));
            $encargadoTelDet = trim((string)($s['encargado_telefono'] ?? ''));
            $encargadoValorDet = $encargadoDet !== '' ? $encargadoDet : 'Sin encargado asignado';
            if ($encargadoDet !== '' && $encargadoTelDet !== '') {
                $encargadoValorDet .= ' · Tel. ' . $encargadoTelDet;
            }
        ?>
        <div class="sol-modal" id="solModal-<?= $idSolicitud ?>" role="dialog" aria-modal="true" aria-labelledby="solModalTitle-<?= $idSolicitud ?>" hidden>
            <div class="sol-modal-panel">
                <div class="sol-modal-header">
                    <div>
                        <h2 id="solModalTitle-<?= $idSolicitud ?>">🏠 Casa #<?= htmlspecialchars($casaDet) ?> — <?= htmlspecialchars($propietarioDet) ?></h2>
                        <p class="sol-modal-sub">Revisa los datos antes de aprobar la vivienda.</p>
                    </div>
                    <button type="button" class="sol-close" data-sol-close aria-label="Cerrar">&times;</button>
                </div>

                <div class="sol-modal-body">
                    <section class="sol-section">
                        <h3>Datos de la vivienda</h3>
                        <div class="sol-grid">
                            <div class="sol-field">
                                <span class="sol-label">Propietario</span>
                                <span class="sol-value"><?= htmlspecialchars($propietarioDet) ?></span>
                            </div>
                            <div class="sol-field">
                                <span class="sol-label">Identificación</span>
                                <span class="sol-value <?= $identificacionDet === '' ? 'sol-muted' : '' ?>"><?= $identificacionDet !== '' ? htmlspecialchars($identificacionDet) : 'No registrada' ?></span>
                            </div>
                            <div class="sol-field">
                                <span class="sol-label">N° de casa</span>
                                <span class="sol-value">#<?= htmlspecialchars($casaDet) ?></span>
                            </div>
                            <div class="sol-field">
                                <span class="sol-label">Calle</span>
                                <span class="sol-value"><?= htmlspecialchars($s['calle_nombre'] ?? '—') ?></span>
                            </div>
                            <div class="sol-field">
                                <span class="sol-label">Barrio</span>
                                <span class="sol-value"><?= htmlspecialchars($s['barrio_nombre'] ?? '—') ?></span>
                            </div>
                            <div class="sol-field">
                                <span class="sol-label">Encargado de la calle</span>
                                <span class="sol-value <?= $encargadoDet === '' ? 'sol-muted' : '' ?>"><?= htmlspecialchars($encargadoValorDet) ?></span>
                            </div>
                            <div class="sol-field" style="grid-column:1 / -1;">
                                <span class="sol-label">Referencia / dirección</span>
                                <span class="sol-value <?= $referenciaDet === '' ? 'sol-muted' : '' ?>"><?= $referenciaDet !== '' ? htmlspecialchars($referenciaDet) : 'Sin referencia registrada' ?></span>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="sol-modal-footer">
                    <button type="button" data-sol-close>Cerrar</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
    (() => {
        const openModal = (id) => {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            const closeBtn = modal.querySelector('.sol-close');
            if (closeBtn) closeBtn.focus();
        };

        const closeModal = (modal) => {
            if (!modal) return;
            modal.hidden = true;
            if (!document.querySelector('.sol-modal:not([hidden])')) {
                document.body.style.overflow = '';
            }
        };

        document.querySelectorAll('[data-sol-open]').forEach((btn) => {
            btn.addEventListener('click', () => openModal(btn.dataset.solOpen));
        });

        document.querySelectorAll('[data-sol-close]').forEach((btn) => {
            btn.addEventListener('click', () => closeModal(btn.closest('.sol-modal')));
        });

        document.querySelectorAll('.sol-modal').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal(modal);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            const modal = document.querySelector('.sol-modal:not([hidden])');
            if (modal) closeModal(modal);
        });
    })();
    </script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
