<?php
// views/admin/usuario_editar.php - Versión Compacta
$user = check_dashboard_access([1, 2]);

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) { header("Location: router.php?page=usuarios"); exit; }

$checkRole = $pdo->prepare("SELECT rol_id FROM usuarios WHERE id = ?");
$checkRole->execute([$user_id]);
$target_rol_id = $checkRole->fetchColumn();

if ((int)$target_rol_id === 3) {
    header("Location: router.php?page=usuarios");
    exit;
}

$sql = "SELECT u.*, r.nombre as rol_nombre,
               db.dni as barrio_dni, db.telefono as barrio_telefono, db.direccion as barrio_direccion, db.barrio_id,
               dc.dni as calle_dni, dc.telefono as calle_telefono, dc.calle_id,
               dg.dni as gestor_dni, dg.telefono as gestor_telefono, dg.area as gestor_area,
               dp.cargo as personal_cargo, dp.dni as personal_dni, dp.telefono as personal_telefono, dp.turno as personal_turno
        FROM usuarios u 
        JOIN roles r ON u.rol_id = r.id 
        LEFT JOIN detalles_encargado_barrio db ON u.id = db.usuario_id
        LEFT JOIN detalles_encargado_calle dc ON u.id = dc.usuario_id
        LEFT JOIN detalles_gestor dg ON u.id = dg.usuario_id
        LEFT JOIN detalles_personal_obrero dp ON u.id = dp.usuario_id
        WHERE u.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) die("Usuario no encontrado.");

$barrios_lista = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$calle_barrio_actual = $u['calle_id']
    ? $pdo->prepare("SELECT barrio_id FROM calles WHERE id = ?")
    : null;
if ($calle_barrio_actual) {
    $calle_barrio_actual->execute([(int)$u['calle_id']]);
    $calle_barrio_actual = (string)($calle_barrio_actual->fetchColumn() ?: '');
} else {
    $calle_barrio_actual = '';
}

$title = "Editar - EcoCusco";
$header_title = "Editar: " . $u['nombre'];

ob_start();
?>
<style>
    .compact-form { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin: 0 auto; }
    .f-section { margin-bottom: 20px; border: 1px solid #F3F4F6; padding: 15px; border-radius: 6px; }
    .f-section h3 { margin: -15px -15px 15px -15px; background: #F9FAFB; padding: 8px 15px; font-size: 13px; color: #374151; border-bottom: 1px solid #F3F4F6; border-radius: 6px 6px 0 0; display: flex; align-items: center; gap: 8px; }
    .f-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .f-group { display: flex; flex-direction: column; gap: 4px; }
    .f-group label { font-size: 12px; font-weight: 600; color: #6B7280; }
    .f-group input, .f-group select { padding: 6px 10px; border: 1px solid #D1D5DB; border-radius: 5px; font-size: 13px; transition: 0.2s; }
    .f-group input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(76,110,145, 0.1); }
    .f-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; border-top: 1px solid #EEE; padding-top: 15px; }
    .btn { padding: 8px 16px; border-radius: 5px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; }
    .btn-save { background: var(--primary); color: white; }
    .btn-cancel { background: #F3F4F6; color: #4B5563; }
    .role-section { animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="compact-form">
    <form action="router.php" method="POST">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="page" value="usuarios">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        
        <div class="f-section">
            <h3>👤 Datos de Cuenta</h3>
            <div class="f-grid">
                <div class="f-group"><label>Nombre</label><input type="text" name="nombre" required value="<?= htmlspecialchars($u['nombre']) ?>"></div>
                <div class="f-group"><label>Apellido</label><input type="text" name="apellido" required value="<?= htmlspecialchars($u['apellido']) ?>"></div>
                <div class="f-group"><label>Email</label><input type="email" name="email" required value="<?= htmlspecialchars($u['email']) ?>"></div>
                <div class="f-group"><label>Password (Opcional)</label><input type="password" name="password" placeholder="Mín. 6 caracteres"></div>
            </div>
        </div>

        <div class="f-section">
            <h3>📅 Perfil Personal</h3>
            <div class="f-grid">
                <div class="f-group">
                    <label>Género</label>
                    <select name="genero">
                        <option value="">-</option>
                        <option value="M" <?= $u['genero'] == 'M' ? 'selected' : '' ?>>Masc</option>
                        <option value="F" <?= $u['genero'] == 'F' ? 'selected' : '' ?>>Fem</option>
                        <option value="Otro" <?= $u['genero'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div class="f-group"><label>Nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= htmlspecialchars($u['fecha_nacimiento'] ?? '') ?>" min="1900-01-01" max="<?= date('Y-m-d') ?>"></div>
                <div class="f-group">
                    <label>Rol</label>
                    <select name="rol_id" id="rol_id" required onchange="toggleRoleFields()">
                        <option value="5" <?= $u['rol_id'] == 5 ? 'selected' : '' ?>>Encargado de Barrio</option>
                        <option value="6" <?= $u['rol_id'] == 6 ? 'selected' : '' ?>>Encargado de Calle</option>
                        <option value="2" <?= $u['rol_id'] == 2 ? 'selected' : '' ?>>Gestor</option>
                        <option value="1" <?= $u['rol_id'] == 1 ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="role_fields_container">
            <div id="j_f" class="f-section role-section" style="display: <?= $u['rol_id'] == 5 ? 'block' : 'none' ?>;" <?= $u['rol_id'] != 5 ? 'disabled' : '' ?>>
                <h3>🏠 Encargado de Barrio</h3>
                <div class="f-grid">
                    <div class="f-group"><label>DNI</label><input type="text" name="dni" value="<?= htmlspecialchars($u['barrio_dni'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números"></div>
                    <div class="f-group"><label>Teléfono</label><input type="text" name="telefono" value="<?= htmlspecialchars($u['barrio_telefono'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números"></div>
                    <div class="f-group">
                        <label>Barrio</label>
                        <select name="barrio_id">
                            <?php foreach ($barrios_lista as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $u['barrio_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="f-group"><label>Dirección</label><input type="text" name="direccion" value="<?= htmlspecialchars($u['barrio_direccion'] ?? '') ?>"></div>
                </div>
            </div>

            <div id="c_f" class="f-section role-section" style="display: <?= $u['rol_id'] == 6 ? 'block' : 'none' ?>;" <?= $u['rol_id'] != 6 ? 'disabled' : '' ?>>
                <h3>📍 Encargado de Calle</h3>
                <div class="f-grid">
                    <div class="f-group"><label>DNI</label><input type="text" name="dni" value="<?= htmlspecialchars($u['calle_dni'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números"></div>
                    <div class="f-group"><label>Teléfono</label><input type="text" name="telefono" value="<?= htmlspecialchars($u['calle_telefono'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números"></div>
                    <div class="f-group">
                        <label>Barrio</label>
                        <select id="calle_barrio_id" name="calle_barrio_id" required>
                            <option value="">Seleccione un barrio...</option>
                            <?php foreach ($barrios_lista as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="f-group">
                        <label>Calle</label>
                        <select name="calle_id" id="calle_id" required disabled>
                            <option value="">Seleccione primero un barrio...</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="g_f" class="f-section role-section" style="display: <?= $u['rol_id'] == 2 ? 'block' : 'none' ?>;" <?= $u['rol_id'] != 2 ? 'disabled' : '' ?>>
                <h3>💼 Gestor</h3>
                <div class="f-grid">
                    <div class="f-group"><label>DNI</label><input type="text" name="dni" value="<?= htmlspecialchars($u['gestor_dni'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números"></div>
                    <div class="f-group"><label>Teléfono</label><input type="text" name="telefono" value="<?= htmlspecialchars($u['gestor_telefono'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números"></div>
                    <div class="f-group"><label>Área</label><input type="text" name="area" value="<?= htmlspecialchars($u['gestor_area'] ?? '') ?>"></div>
                </div>
            </div>

        </div>

        <div class="f-actions">
            <a href="router.php?page=usuario_ver&id=<?= $u['id'] ?>" class="btn btn-cancel">Cancelar</a>
            <button type="submit" class="btn btn-save">Actualizar</button>
        </div>
    </form>
</div>

<script>
function toggleRoleFields() {
    const rolEl = document.getElementById('rol_id');
    const r = rolEl ? rolEl.value : '';
    const map = {5:'j_f', 6:'c_f', 2:'g_f'};
    Object.keys(map).forEach(role => {
        const el = document.getElementById(map[role]);
        if (!el) return;
        const active = (role === r);
        el.style.display = active ? 'block' : 'none';
        // Deshabilitar los campos de las secciones ocultas para que NO se envíen
        el.querySelectorAll('input, select, textarea').forEach(f => { f.disabled = !active; });
    });
}

document.addEventListener('DOMContentLoaded', toggleRoleFields);

async function cargarCallesAsignables(barrioId, calleActual) {
    const select = document.getElementById('calle_id');
    if (!select) return;
    select.disabled = true;
    select.innerHTML = '<option value="">Cargando calles...</option>';
    if (!barrioId) {
        select.innerHTML = '<option value="">Seleccione primero un barrio...</option>';
        return;
    }
    const response = await fetch('router.php?page=ajax_get_calles&barrio_id=' + encodeURIComponent(barrioId) + '&available_only=1&current_calle_id=' + encodeURIComponent(calleActual || ''), { headers: { 'Accept': 'application/json' } });
    const responseText = await response.text();
    if (!response.ok) throw new Error(responseText || 'No se pudieron cargar las calles.');
    let calles;
    try {
        calles = JSON.parse(responseText);
    } catch (error) {
        throw new Error('La respuesta del servidor no es válida.');
    }
    if (!Array.isArray(calles)) throw new Error('La respuesta de calles no es válida.');
    select.innerHTML = '<option value="">Seleccione una calle...</option>';
    calles.forEach(calle => {
        const option = new Option(calle.nombre, calle.id);
        if (String(calle.id) === String(calleActual)) option.selected = true;
        select.add(option);
    });
    select.disabled = false;
}

document.addEventListener('DOMContentLoaded', function () {
    const barrio = document.getElementById('calle_barrio_id');
    if (!barrio) return;
    const calleActual = <?= json_encode((string)($u['calle_id'] ?? '')) ?>;
    const barrioActual = <?= json_encode($calle_barrio_actual) ?>;
    barrio.value = barrioActual;
    const mostrarErrorCalles = (error) => {
        const select = document.getElementById('calle_id');
        if (select) {
            select.innerHTML = '<option value="">No se pudieron cargar las calles</option>';
            select.disabled = true;
        }
        console.error(error);
    };
    barrio.addEventListener('change', () => {
        cargarCallesAsignables(barrio.value, barrio.value === barrioActual ? calleActual : '')
            .catch(mostrarErrorCalles);
    });
    cargarCallesAsignables(barrio.value, calleActual).catch(mostrarErrorCalles);
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>