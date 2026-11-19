<?php
// views/gestor/recibos.php
$user = check_dashboard_access([1, 2]);
$page = 'recibos';
$paginaFormulario = $_GET['page'] ?? $page;
$sid = $_GET['sid'] ?? '';
$search = $_GET['search'] ?? '';
$f_barrio = (int)($_GET['barrio_id'] ?? 0);
$params = [];

// Obtener cobros pagados para generar recibos
$sql = "SELECT c.*, v.propietario, v.numero_casa, v.cedula, v.cedula_tipo, v.otro_identificador, b.nombre as barrio_nombre 
        FROM cobros c 
        JOIN viviendas v ON c.vivienda_id = v.id 
        JOIN barrios b ON v.barrio_id = b.id
        WHERE c.estado = 'Pagado'";

if ($search) {
    $sql .= " AND (v.propietario LIKE :search_propietario OR v.numero_casa LIKE :search_casa OR b.nombre LIKE :search_barrio)";
    $params[':search_propietario'] = "%$search%";
    $params[':search_casa'] = "%$search%";
    $params[':search_barrio'] = "%$search%";
}
        if ($f_barrio > 0) {
            $sql .= " AND v.barrio_id = :barrio_id";
            $params[':barrio_id'] = $f_barrio;
        }
        $limiteRecibos = reporte_exportar_solicitado() ? 5000 : 50;
        $sql .= " ORDER BY c.id DESC LIMIT " . $limiteRecibos;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$barrioNombreFiltro = '';
foreach ($barrios as $b) {
    if ((int)$b['id'] === $f_barrio) { $barrioNombreFiltro = $b['nombre']; break; }
}
$metaRecibos = [
    'Buscar' => $search !== '' ? $search : null,
    'Barrio' => $barrioNombreFiltro !== '' ? $barrioNombreFiltro : null,
];
$filasRecibos = [];
foreach ($pagos as $p) {
    $filasRecibos[] = [
        '#' . ($p['numero_casa'] ?? ''),
        $p['propietario'] ?? '',
        formato_identificacion($p['cedula'] ?? '', $p['cedula_tipo'] ?? 'V', $p['otro_identificador'] ?? ''),
        $p['barrio_nombre'] ?? '',
        ($p['mes'] ?? '') . '/' . ($p['anio'] ?? ''),
        monto((float)($p['monto'] ?? 0)),
        $p['referencia_pago'] ?? '',
        $p['fecha_emision'] ?? '',
    ];
}
reporte_exportar_si_solicitado(
    'recibos_pagados',
    'Pagos realizados - recibos',
    ['Casa', 'Propietario', 'Identificación', 'Barrio', 'Periodo', 'Monto', 'Referencia de pago', 'Fecha de emisión'],
    $filasRecibos,
    array_merge($metaRecibos, ['Total recibos' => count($filasRecibos)]),
    'Centro de recibos y comprobantes de pago'
);

$title = "Centro de Recibos - EcoReciclaje";
$header_title = "Gestión de Comprobantes";
$header_subtitle = "Emisión de recibos y reportes para los ciudadanos.";

ob_start();
?>
    <?= reporte_encabezado_impresion('Pagos realizados - recibos', 'Centro de recibos y comprobantes de pago', array_merge($metaRecibos, ['Total recibos' => count($pagos)])) ?>
    <?= reporte_botonera() ?>

    <div class="recibos-grid" style="display: grid; grid-template-columns: 350px 1fr; gap: 25px; align-items: start;">
    <style>
        @media(max-width:768px){.recibos-grid{grid-template-columns:1fr!important}}
        @media print {
            .sidebar, .header, .notif-wrap, .menu-toggle, .sidebar-overlay,
            .recibos-grid > .card:first-child, .recibos-grid form,
            .recibos-grid .btn-primary, .recibos-grid .badge { display:none !important; }
            .main { margin-left:0 !important; padding:0 !important; }
            .recibos-grid { display:block !important; }
            .recibos-grid > .card:last-child { border:0 !important; box-shadow:none !important; }
            .recibos-grid .table-wrap { overflow:visible !important; }
        }
    </style>
        
        <!-- LATERAL: REPORTES MENSAL -->
        <div class="card" style="border-top: 4px solid #4C6E91;">
            <div style="text-align: center; padding: 10px;">
                <div style="font-size: 40px; margin-bottom: 10px;">📊</div>
                <h3 style="margin-bottom: 10px;">Reportes de Recaudación</h3>
                <p style="color: #6B7280; font-size: 13px; margin-bottom: 14px;">Genera un consolidado de todos los barrios del mes actual para control interno.</p>
                <a href="router.php?page=reportes&tab=general" class="btn-primary" style="display: block; text-decoration: none; margin-bottom: 10px;">Ver Monitor Global</a>
                <button class="btn-primary" style="background:#4B5563; width: 100%;" onclick="window.print()">Imprimir Vista Actual</button>
            </div>
        </div>

        <!-- PRINCIPAL: BUSCAR Y GENERAR RECIBO -->
        <div class="card">
            <h3 style="margin-top: 0; color: #111827;">🧾 Generar Recibo de Vivienda</h3>
            <p style="color: #6B7280; font-size: 14px;">Busca por nombre del propietario, número de casa o barrio.</p>
            
            <form method="GET" action="router.php" class="unified-filter no-print">
                <input type="hidden" name="page" value="<?= htmlspecialchars($paginaFormulario) ?>">
                <input type="hidden" name="sid" value="<?= htmlspecialchars($sid) ?>">
                <input type="text" name="search" placeholder="Ej: Juan Perez o Barrio Sol..." value="<?= htmlspecialchars($search) ?>" 
                       style="flex: 1; padding: 10px; border: 1px solid #E5E7EB; border-radius: 8px;">
                <select name="barrio_id" style="padding: 10px; border: 1px solid #E5E7EB; border-radius: 8px;">
                    <option value="0">Todos los barrios</option>
                    <?php foreach ($barrios as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $f_barrio === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Buscar</button>
            </form>

            <div class="table-wrap">
                <table class="table-mini" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #F3F4F6;">
                            <th style="padding: 12px;">Propietario</th>
                            <th style="padding: 12px;">Barrio</th>
                            <th style="padding: 12px;">Periodo</th>
                            <th style="padding: 12px; text-align: center;">Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 30px; color: #9CA3AF;">No se encontraron pagos realizados con ese criterio.</td></tr>
                        <?php endif; ?>
                        <?php foreach($pagos as $p): ?>
                            <tr style="border-bottom: 1px solid #F3F4F6;">
                                <td style="padding: 12px;">
                                    <div style="font-weight: 700;"><?= htmlspecialchars($p['propietario']) ?></div>
                                    <?php $identificacion = formato_identificacion($p['cedula'] ?? '', $p['cedula_tipo'] ?? 'V', $p['otro_identificador'] ?? ''); ?>
                                    <?php if ($identificacion !== ''): ?><div style="font-size: 11px; color: #6B7280;">Identificación: <?= htmlspecialchars($identificacion) ?></div><?php endif; ?>
                                    <div style="font-size: 11px; color: #6B7280;">Casa #<?= htmlspecialchars($p['numero_casa']) ?></div>
                                </td>
                                <td style="padding: 12px; font-size: 13px;"><?= htmlspecialchars($p['barrio_nombre']) ?></td>
                                <td style="padding: 12px; font-size: 13px; font-weight: 600;"><?= $p['mes'] ?>/<?= $p['anio'] ?></td>
                                <td style="padding: 12px; text-align: center;">
                                    <a href="views/gestor/ver_recibo.php?cobro_id=<?= $p['id'] ?>" target="_blank" class="badge" style="background: #E9EFF4; color: #33485C; text-decoration: none; padding: 8px 12px;">
                                        📄 Ver / Imprimir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
