<?php
// views/admin/calles.php
$user = check_dashboard_access([1]); // Solo Admin

// Obtener barrios para el filtro/formulario
$barriosStmt = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre");
$barrios = $barriosStmt->fetchAll(PDO::FETCH_ASSOC);

$barrio_filtro = isset($_GET['barrio_id']) ? (int)$_GET['barrio_id'] : null;
$f_buscar = trim((string)($_GET['search'] ?? ''));

$sqlCalles = "SELECT c.*, b.nombre as barrio_nombre, 
                    (SELECT COUNT(*) FROM viviendas WHERE calle_id = c.id) as total_viviendas
              FROM calles c 
              JOIN barrios b ON c.barrio_id = b.id 
              WHERE 1=1";
$paramsCalles = [];
if ($barrio_filtro) {
    $sqlCalles .= " AND c.barrio_id = :barrio";
    $paramsCalles[':barrio'] = $barrio_filtro;
}
if ($f_buscar !== '') {
    $sqlCalles .= " AND (c.nombre LIKE :q_nombre OR c.descripcion LIKE :q_descripcion OR b.nombre LIKE :q_barrio)";
    $paramsCalles[':q_nombre'] = '%' . $f_buscar . '%';
    $paramsCalles[':q_descripcion'] = '%' . $f_buscar . '%';
    $paramsCalles[':q_barrio'] = '%' . $f_buscar . '%';
}
$sqlCalles .= " ORDER BY b.nombre, c.nombre";
$callesStmt = $pdo->prepare($sqlCalles);
$callesStmt->execute($paramsCalles);
$calles = $callesStmt->fetchAll(PDO::FETCH_ASSOC);

$barrioNombreFiltro = '';
foreach ($barrios as $b) {
    if ((int)$b['id'] === (int)$barrio_filtro) { $barrioNombreFiltro = $b['nombre']; break; }
}
$filasCalles = [];
foreach ($calles as $c) {
    $filasCalles[] = [
        trim(($c['tipo_via'] ?? 'Calle') . ' ' . ($c['nombre'] ?? '')),
        $c['barrio_nombre'] ?? '',
        $c['descripcion'] ?? '',
        (int)($c['total_viviendas'] ?? 0),
    ];
}
reporte_exportar_si_solicitado(
    'calles',
    'Listado de calles',
    ['Vía', 'Barrio', 'Descripción', 'Viviendas'],
    $filasCalles,
    [
        'Barrio' => $barrioNombreFiltro !== '' ? $barrioNombreFiltro : null,
        'Buscar' => $f_buscar !== '' ? $f_buscar : null,
        'Total calles' => count($filasCalles),
    ],
    'Catálogo de vías por barrio'
);

$title = "Administrar Calles - EcoReciclaje";
$header_title = "Gestión de Calles";
$header_subtitle = "Configura las calles de cada barrio para una mejor organización.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <?= reporte_encabezado_impresion('Listado de calles', 'Catálogo de vías por barrio', ['Barrio' => $barrioNombreFiltro !== '' ? $barrioNombreFiltro : null, 'Buscar' => $f_buscar !== '' ? $f_buscar : null, 'Total calles' => count($calles)]) ?>
    <?= reporte_botonera() ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap:12px; flex-wrap:wrap;">
            <div class="card-title" style="margin-bottom: 0; border: none;">🏠 Listado de Calles</div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap:wrap;">
                <form method="GET" class="unified-filter no-print">
                    <input type="hidden" name="page" value="calles">
                    <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
                    <input type="search" name="search" value="<?= htmlspecialchars($f_buscar) ?>"
                           placeholder="Buscar calle..." style="padding:7px 9px; border:1px solid #E5E7EB; font-size:12px;">
                    <select name="barrio_id" onchange="this.form.submit()" style="padding: 7px 9px; font-size: 12px; border: 1px solid #E5E7EB;">
                        <option value="">Todos los barrios</option>
                        <?php foreach($barrios as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= (int)$barrio_filtro === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary" style="padding:7px 11px;">Filtrar</button>
                </form>
                <a href="router.php?page=calle_nueva" class="btn-primary" style="text-decoration: none;">+ Nueva Calle</a>
            </div>
        </div>
        
        <table class="table-mini" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #F3F4F6;">
                    <th style="padding: 12px; text-align: left;">Vía</th>
                    <th style="padding: 12px; text-align: left;">Barrio</th>
                    <th style="padding: 12px; text-align: center;">Viviendas</th>
                    <th style="padding: 12px; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($calles)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:20px; color:#9CA3AF;">No se encontraron calles.</td></tr>
                <?php endif; ?>
                <?php foreach($calles as $c): ?>
                    <tr style="border-bottom: 1px solid #F9FAFB;">
                        <td style="padding: 12px;">
                            <strong><?= htmlspecialchars(($c['tipo_via'] ?? 'Calle') . ' ' . $c['nombre']) ?></strong>
                            <?php if (!empty($c['descripcion'])): ?>
                                <div style="font-size:11px;color:#6B7280;margin-top:3px;">
                                    <?= htmlspecialchars($c['descripcion']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;"><span class="badge" style="background:#E5E7EB; border:none;"><?= htmlspecialchars($c['barrio_nombre']) ?></span></td>
                        <td style="padding: 12px; text-align: center;"><?= $c['total_viviendas'] ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="router.php?page=calle_editar&id=<?= $c['id'] ?>" class="btn-edit">✏️ Editar</a>
                            <a href="router.php?page=gestor_viviendas&calle_id=<?= $c['id'] ?>" style="color:#4C6E91; text-decoration:none; font-size:11px; font-weight:600; margin-left:8px;">Ver Viviendas</a>
                            <a href="router.php?page=reporte_calle&calle_id=<?= $c['id'] ?>" style="color:#33485C; text-decoration:none; font-size:11px; font-weight:600; margin-left:8px;">🖨️ Reporte</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
