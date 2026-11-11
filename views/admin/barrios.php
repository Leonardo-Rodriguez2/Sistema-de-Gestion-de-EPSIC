<?php
// views/admin/barrios.php
$user = check_dashboard_access([1]);

$f_buscar = trim((string)($_GET['search'] ?? ''));

// 1. Obtener lista de barrios con estadísticas
$sqlBarrios = "
    SELECT b.*, 
    (SELECT COUNT(*) FROM viviendas WHERE barrio_id = b.id) as total_viviendas,
    (SELECT SUM(monto_total) FROM recaudaciones WHERE barrio_id = b.id AND estado = 'Verificado') as total_recaudado
    FROM barrios b
    WHERE 1=1";
$paramsBarrios = [];
if ($f_buscar !== '') {
    $sqlBarrios .= " AND (b.nombre LIKE :q_nombre OR b.ciudad LIKE :q_ciudad OR b.parroquia_municipio LIKE :q_parroquia OR b.lider_comunidad LIKE :q_lider)";
    $paramsBarrios[':q_nombre'] = '%' . $f_buscar . '%';
    $paramsBarrios[':q_ciudad'] = '%' . $f_buscar . '%';
    $paramsBarrios[':q_parroquia'] = '%' . $f_buscar . '%';
    $paramsBarrios[':q_lider'] = '%' . $f_buscar . '%';
}
$sqlBarrios .= " ORDER BY b.nombre ASC";
$barriosStmt = $pdo->prepare($sqlBarrios);
$barriosStmt->execute($paramsBarrios);
$barrios = $barriosStmt->fetchAll(PDO::FETCH_ASSOC);

// Exportación del listado filtrado.
$filasBarrios = [];
foreach ($barrios as $b) {
    $filasBarrios[] = [
        $b['nombre'] ?? '',
        $b['ciudad'] ?? '',
        $b['parroquia_municipio'] ?? '',
        $b['lider_comunidad'] ?? '',
        $b['limite_referencia'] ?? '',
        (int)($b['total_viviendas'] ?? 0),
        number_format((float)($b['total_recaudado'] ?? 0), 2, '.', ','),
        'Activo',
    ];
}
reporte_exportar_si_solicitado(
    'barrios',
    'Barrios y comunidades',
    ['Barrio', 'Ciudad', 'Parroquia / Municipio', 'Líder / cobrador', 'Referencia', 'Viviendas', 'Recaudación histórica', 'Estado'],
    $filasBarrios,
    ['Buscar' => $f_buscar !== '' ? $f_buscar : null, 'Total barrios' => count($filasBarrios)],
    'Catálogo territorial y rendimiento de recaudación'
);

$title = "Gestión de Barrios - EcoCusco";
$header_title = "Barrios y Comunidades";
$header_subtitle = "Administra las zonas de recolección y monitorea su recaudación.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Barrios y comunidades', 'Catálogo territorial y rendimiento de recaudación', ['Buscar' => $f_buscar !== '' ? $f_buscar : null, 'Total barrios' => count($barrios)]) ?>
    <?= reporte_botonera() ?>

    <form method="GET" action="router.php" class="card no-print" style="margin-bottom:18px; padding:14px 16px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
        <input type="hidden" name="page" value="barrios">
        <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
        <div style="flex:1; min-width:220px;">
            <label style="display:block; font-size:9px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#4B5563; margin-bottom:6px;">Buscar barrio</label>
            <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>"
                   placeholder="Nombre, ciudad, parroquia o líder..."
                   style="width:100%; padding:9px 10px; border:1px solid #D1D5DB; font-size:11px;">
        </div>
        <button type="submit" class="btn-primary" style="padding:9px 14px;">Filtrar</button>
        <a href="router.php?page=barrios" class="btn-edit" style="padding:9px 12px; text-decoration:none;">Limpiar</a>
    </form>

    <div class="page-intro"><div class="page-intro-icon">▦</div><div><strong>Barrios y comunidades</strong><span>Administra las zonas, revisa sus viviendas y consulta el rendimiento de recaudación.</span></div></div>
    <div class="grid">
        <!-- LISTADO DE BARRIOS -->
        <div class="card" style="grid-column: span 2;">
            <div class="panel-heading">
                <div><span class="eyebrow">Catálogo territorial</span><h3>🗺️ Zonas del sistema</h3><p>Consulta y administra cada barrio registrado.</p></div>
                <a href="router.php?page=barrio_nuevo" class="btn-primary" style="text-decoration: none;">+ Añadir Barrio</a>
            </div>
            
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre del Barrio</th>
                            <th>Ciudad / Parroquia</th>
                            <th>Líder / Cobrador</th>
                            <th>Referencia</th>
                            <th>Viviendas Registradas</th>
                            <th>Recaudación Histórica</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($barrios as $b): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['nombre']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($b['ciudad'] ?? '') ?><br>
                                    <small style="color:#6B7280;"><?= htmlspecialchars($b['parroquia_municipio'] ?? 'Sin parroquia/municipio') ?></small>
                                </td>
                                <td><?= htmlspecialchars($b['lider_comunidad'] ?? 'Sin asignar') ?></td>
                                <td style="max-width:220px;"><?= nl2br(htmlspecialchars($b['limite_referencia'] ?? '')) ?: '<span style="color:#9CA3AF;">—</span>' ?></td>
                                <td><?= $b['total_viviendas'] ?> casas</td>
                                <td style="font-weight: 700; color: #4C6E91;">Bs. <?= number_format($b['total_recaudado'] ?? 0, 2) ?></td>
                                <td><span class="badge pagado">Activo</span></td>
                                <td><a href="router.php?page=barrio_editar&id=<?= $b['id'] ?>" class="btn-edit">✏️ Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RESUMEN RÁPIDO -->
        <div class="card">
            <h3>📈 Rendimiento Global</h3>
            <div style="padding: 10px 0;">
                <div style="margin-bottom: 15px;">
                    <small style="color: #6B7280; display: block;">Barrios Activos</small>
                    <span style="font-size: 24px; font-weight: 700;"><?= count($barrios) ?></span>
                </div>
                <div>
                    <small style="color: #6B7280; display: block;">Promedio por Barrio</small>
                    <?php 
                        $total = array_sum(array_column($barrios, 'total_recaudado'));
                        $promedio = count($barrios) > 0 ? $total / count($barrios) : 0;
                    ?>
                    <span style="font-size: 24px; font-weight: 700; color: #4C6E91;">Bs. <?= number_format($promedio, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
