<?php
// admin_dashboard_view.php - Versión Compacta
$user = check_dashboard_access([1, 2]);
$filter_rol_id = (int)($_GET['rol_id'] ?? 0);
$filter_search = trim((string)($_GET['search'] ?? ''));
$paginaActual = $_GET['page'] ?? 'usuarios';

// Mapeo dinámico para etiquetas y títulos
$roles_map = [
    1 => "Administradores",
    2 => "Gestores de Pago",
    5 => "Encargados de Barrio",
    6 => "Encargados de Calle",
];
$roles_excluidos = [3];
$role_name = $roles_map[$filter_rol_id] ?? "Usuarios";

$title = "$role_name - EcoCusco";
$header_title = "Gestión $role_name";

// Consulta SQL optimizada
$sql = "SELECT u.id, u.nombre, u.apellido, u.email, u.estado, u.rol_id, r.nombre as rol_nombre, u.creado_en,
               dp.cargo, COALESCE(b.nombre, bcalle.nombre) AS barrio_nombre
        FROM usuarios u 
        JOIN roles r ON u.rol_id = r.id 
        LEFT JOIN detalles_personal_obrero dp ON u.id = dp.usuario_id
        LEFT JOIN detalles_encargado_barrio db ON u.id = db.usuario_id
        LEFT JOIN barrios b ON db.barrio_id = b.id
        LEFT JOIN detalles_encargado_calle dc ON u.id = dc.usuario_id
        LEFT JOIN calles calle_asignada ON dc.calle_id = calle_asignada.id
        LEFT JOIN barrios bcalle ON calle_asignada.barrio_id = bcalle.id
        WHERE 1=1";
$params = [];
if ($filter_rol_id > 0) {
    $sql .= " AND u.rol_id = :rol_id";
    $params[':rol_id'] = $filter_rol_id;
}
if ($filter_search !== '') {
    $sql .= " AND (u.nombre LIKE :q_nombre OR u.apellido LIKE :q_apellido OR u.email LIKE :q_email)";
    $params[':q_nombre'] = '%' . $filter_search . '%';
    $params[':q_apellido'] = '%' . $filter_search . '%';
    $params[':q_email'] = '%' . $filter_search . '%';
}
$sql .= " AND u.rol_id NOT IN (" . implode(',', $roles_excluidos) . ") ORDER BY u.creado_en DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metadatos y exportación del listado filtrado.
$metaUsuarios = [
    'Rol' => $filter_rol_id > 0 ? ($roles_map[$filter_rol_id] ?? '') : 'Todos',
    'Buscar' => $filter_search !== '' ? $filter_search : null,
];
$filasUsuarios = [];
foreach ($usuarios_lista as $u) {
    $filasUsuarios[] = [
        trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')),
        $u['email'] ?? '',
        $u['rol_nombre'] ?? '',
        $u['cargo'] ?? '',
        $u['barrio_nombre'] ?? '',
        $u['estado'] ?? '',
        !empty($u['creado_en']) ? date('d/m/Y', strtotime($u['creado_en'])) : '',
    ];
}
reporte_exportar_si_solicitado(
    'usuarios',
    'Usuarios del sistema',
    ['Nombre', 'Correo', 'Rol', 'Cargo', 'Barrio asignado', 'Estado', 'Fecha de registro'],
    $filasUsuarios,
    array_merge($metaUsuarios, ['Total usuarios' => count($filasUsuarios)]),
    'Listado administrativo de usuarios'
);

ob_start();
?>
<style>
    /* Estilos Reducidos */
    .actions-bar { display: flex; justify-content: space-between; margin-bottom: 10px; gap: 10px; flex-wrap: wrap; }
    .search-container { position: relative; flex-grow: 1; max-width: 280px; }
    @media (max-width: 768px) { .search-container { max-width: 100%; } }
    .search-input { width: 100%; padding: 5px 10px 5px 30px; border: 1px solid #E5E7EB; border-radius: 6px; font-size: 12px; }
    .search-icon { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .btn-new-user { background: var(--primary); color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; }
    .table-container { background: white; padding: 10px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #F3F4F6; }
    th { background: #F9FAFB; color: #4B5563; text-transform: uppercase; font-size: 11px; }
    .user-avatar { width: 24px; height: 24px; background: #EEE; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; margin-right: 8px; flex-shrink: 0; }
    .name-col { display: flex; align-items: center; }
    .table-actions { display: flex; gap: 4px; }
    .action-icon { width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #F3F4F6; color: #4B5563; text-decoration: none; }
    .badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; }
</style>

<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<?= reporte_encabezado_impresion('Usuarios del sistema', $header_title, array_merge($metaUsuarios, ['Total usuarios' => count($usuarios_lista)])) ?>
<?= reporte_botonera() ?>

<?php render_dashboard_stats([
    ['title' => 'Total', 'value' => count($usuarios_lista), 'color' => '#4C6E91', 'icon' => '👥'],
    ['title' => 'Admins', 'value' => count(array_filter($usuarios_lista, fn($u) => $u['rol_id'] == 1)), 'color' => '#8A5A5A', 'icon' => '🛡️'],
]); ?>

<?php
// Determinar enlace de creación según el filtro
$new_link = $filter_rol_id > 0 ? "usuario_nuevo&rol_id=$filter_rol_id" : 'usuario_nuevo';
$sid = $_SESSION['active_sid'] ?? 'main';
?>

<form method="GET" action="router.php" class="actions-bar no-print">
    <input type="hidden" name="page" value="<?= htmlspecialchars($paginaActual) ?>">
    <input type="hidden" name="sid" value="<?= htmlspecialchars($sid) ?>">
    <div class="search-container">
        <span class="search-icon">🔍</span>
        <input type="text" id="userSearch" name="search" class="search-input"
               value="<?= htmlspecialchars($filter_search) ?>"
               placeholder="Buscar <?= strtolower($role_name) ?>...">
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        <select name="rol_id" onchange="this.form.submit()"
                style="padding: 7px 9px; border: 1px solid #E5E7EB; font-size: 12px;">
            <option value="0">Todos los roles</option>
            <?php foreach ($roles_map as $rolId => $rolNombre): ?>
                <option value="<?= (int)$rolId ?>" <?= $filter_rol_id === (int)$rolId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($rolNombre) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary" style="padding: 7px 12px;">Buscar</button>
        <a href="router.php?page=<?= htmlspecialchars($paginaActual) ?>&sid=<?= urlencode($sid) ?>"
           class="btn-edit" style="padding: 7px 10px; text-decoration:none;">Limpiar</a>
        <?php if ($filter_rol_id == 0): ?>
            <!-- Accesos Rápidos si no hay filtro -->
            <a href="router.php?page=usuario_nuevo&rol_id=5" class="btn-new-user" style="background:#3E5C76;" title="Nuevo Encargado de Barrio">+ Barrio</a>
        <?php else: ?>
            <a href="router.php?page=<?= $new_link ?>" class="btn-new-user">
                <span>+</span> Registrar <?= rtrim($role_name, 's') ?>
            </a>
        <?php endif; ?>
    </div>
</form>

<section class="table-container">
    <table id="userTable">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Rol / Cargo</th>
                <th>Barrio asignado</th>
                <th>Registro</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios_lista as $u): ?>
            <tr>
                <td>
                    <div class="name-col">
                        <div class="user-avatar"><?= strtoupper(substr($u['nombre'],0,1).substr($u['apellido'],0,1)) ?></div>
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($u['nombre'].' '.$u['apellido']) ?></div>
                            <div style="color: #6B7280; font-size: 11px;"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php 
                        $roles_css = [1=>'admin', 2=>'gestor', 3=>'recolector', 5=>'jefe', 6=>'calle'];
                        $class = $roles_css[$u['rol_id']] ?? 'usuario';
                    ?>
                    <span class="badge <?= $class ?>">
                        <?= htmlspecialchars($u['rol_nombre']) ?>
                        <?= ($u['rol_id'] == 3 && $u['cargo']) ? ' - '.htmlspecialchars($u['cargo']) : '' ?>
                    </span>
                </td>
                <td>
                    <?php if (in_array((int)$u['rol_id'], [5, 6], true)): ?>
                        <span style="color:#374151;"><?= htmlspecialchars($u['barrio_nombre'] ?: 'Sin barrio asignado') ?></span>
                    <?php else: ?>
                        <span style="color:#9CA3AF;">—</span>
                    <?php endif; ?>
                </td>
                <td style="color: #6B7280;"><?= date('d/m/y', strtotime($u['creado_en'])) ?></td>
                <td>
                    <div class="table-actions">
                        <a href="router.php?page=usuario_ver&id=<?= $u['id'] ?>" class="action-icon" title="Ver">👁</a>
                        <a href="router.php?page=usuario_editar&id=<?= $u['id'] ?>" class="action-icon" title="Editar">✏️</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<script>
function filterUsers() {
    let filter = document.getElementById("userSearch").value.toLowerCase();
    let rows = document.querySelectorAll("#userTable tbody tr");
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? "" : "none";
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>