<?php
// views/admin/usuario_nuevo.php
$user = check_dashboard_access([1, 2]);

// El Gestor no administra usuarios desde este formulario.
if ($user['rol_id'] == 2) {
    header("Location: router.php?page=gestor_dashboard");
    exit;
}

if ((int)($_GET['rol_id'] ?? 0) === 3) {
    header("Location: router.php?page=usuarios");
    exit;
}

$locked_rol_id = (int)($_GET['rol_id'] ?? 0);
$es_gestor_pago = $locked_rol_id === 2;
$es_encargado_barrio = $locked_rol_id === 5;
$es_encargado_calle = $locked_rol_id === 6;

// Para encargados de barrio solo mostrar barrios disponibles.
$barriosStmt = $pdo->query(
    "SELECT b.id, b.nombre
     FROM barrios b
     WHERE NOT EXISTS (
         SELECT 1 FROM detalles_encargado_barrio deb
         WHERE deb.barrio_id = b.id
     )
     ORDER BY b.nombre ASC"
);
$barrios_lista = $barriosStmt->fetchAll(PDO::FETCH_ASSOC);
$barriosTodosStmt = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
$barrios_todos = $barriosTodosStmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Nuevo Usuario - EcoReciclaje";
$header_title = "";

ob_start();
?>
<div class="form-container">
    <form action="router.php" method="POST" class="premium-form">
        <input type="hidden" name="action" value="add_user">
        <input type="hidden" name="page" value="usuarios">
        
        <div class="form-section">
            <h3><span class="icon">👤</span> Información Básica</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre">Nombre(s)</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej. Juan">
                </div>
                <div class="form-group">
                    <label for="apellido">Apellido(s)</label>
                    <input type="text" id="apellido" name="apellido" required placeholder="Ej. Pérez">
                </div>
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" required placeholder="juan.perez@ejemplo.com">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña (por defecto: 123456)</label>
                    <input type="password" id="password" name="password" placeholder="Mín. 6 caracteres">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><span class="icon">📅</span> Detalles Personales</h3>
            <div class="form-grid">
                <?php if (!$es_gestor_pago && !$es_encargado_barrio && !$es_encargado_calle): ?>
                    <div class="form-group">
                        <label for="genero">Género</label>
                        <select id="genero" name="genero">
                            <option value="">Seleccione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" min="1900-01-01" max="<?= date('Y-m-d') ?>">
                </div>
                <?php if ($es_gestor_pago || $es_encargado_barrio || $es_encargado_calle): ?>
                    <input type="hidden" name="rol_id" value="<?= $es_gestor_pago ? '2' : ($es_encargado_barrio ? '5' : '6') ?>">
                <?php else: ?>
                    <div class="form-group">
                        <label for="rol_id">Rol en el Sistema</label>
                        <select id="rol_id" name="rol_id" required onchange="toggleRoleFields()">
                            <option value="">Seleccione un rol...</option>
                            <option value="5" <?= ($locked_rol_id == 5 ? 'selected' : '') ?> <?= ($user['rol_id'] == 2) ? 'disabled' : '' ?>>Encargado de Barrio</option>
                            <option value="6" <?= ($locked_rol_id == 6 ? 'selected' : '') ?> <?= ($user['rol_id'] == 2) ? 'disabled' : '' ?>>Encargado de Calle</option>
                            <option value="2" <?= ($locked_rol_id == 2 ? 'selected' : '') ?> <?= ($user['rol_id'] == 2) ? 'disabled' : '' ?>>Gestor de Pagos</option>
                            <option value="1" <?= ($locked_rol_id == 1 ? 'selected' : '') ?> <?= ($user['rol_id'] == 2) ? 'disabled' : '' ?>>Administrador</option>
                        </select>
                        <?php if ($locked_rol_id > 0): ?>
                            <div style="font-size: 11px; color: #4C6E91; margin-top: 4px; font-weight: 600;">✨ Rol sugerido según la sección de origen</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Role Specific Sections -->
        <div id="role_fields_container">
            <!-- Encargado de Barrio Section -->
            <div id="jefe_fields" class="form-section role-section" style="display: none;" disabled>
                <h3><span class="icon">🏠</span> Detalles: Encargado de Barrio</h3>
                <div class="form-grid">
                    <div class="form-group">
                    <label>CI</label>
                        <input type="text" name="dni" placeholder="Ej. 70654321" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" placeholder="Ej. 987654321" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números">
                    </div>
                    <div class="form-group">
                        <label>Barrio Asignado</label>
                        <select name="barrio_id">
                            <?php foreach ($barrios_lista as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="direccion" placeholder="Ej. Calle Saphy 123">
                    </div>
                </div>
            </div>
            
            <!-- Encargado de Calle Section -->
            <div id="calle_fields" class="form-section role-section" style="display: none;" disabled>
                <h3><span class="icon">📍</span> Detalles: Encargado de Calle</h3>
                <div class="form-grid">
                    <div class="form-group">
                    <label>CI</label>
                        <input type="text" name="dni" placeholder="Ej. 70654321" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" placeholder="Ej. 987654321" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números">
                    </div>
                    <div class="form-group">
                        <label for="calle_barrio_id">Barrio</label>
                        <select id="calle_barrio_id" name="calle_barrio_id" required>
                            <option value="">Seleccione un barrio...</option>
                            <?php foreach ($barrios_todos as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="calle_id">Calle Asignada</label>
                        <select id="calle_id" name="calle_id" required disabled>
                            <option value="">Seleccione primero un barrio...</option>
                        </select>
                        <small id="calles_status" style="color:#6B7280;"></small>
                    </div>
                </div>
            </div>

            <!-- Gestor Section -->
            <div id="gestor_fields" class="form-section role-section" style="display: none;" disabled>
                <h3><span class="icon">💼</span> Detalles: Gestor de Pagos</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>CI</label>
                        <input type="text" name="dni" placeholder="Ej. 70654321" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" placeholder="Ej. 987654321" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números">
                    </div>
                    <div class="form-group">
                        <label>Área Responsable</label>
                        <input type="text" name="area" placeholder="Ej. Cobranza Norte">
                    </div>
                </div>
            </div>

        </div>

        <div class="form-actions">
            <a href="router.php?page=usuarios" class="btn-cancel">Ver el listado</a>
            <button  type="submit" class="btn-submit">Crear Usuario</button>
        </div>
    </form>
</div>

<style>
.form-container { margin: 0 auto; padding-bottom: 30px; }
.premium-form { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.form-section { margin-bottom: 25px; border-bottom: 1px solid #F3F4F6; padding-bottom: 15px; animation: fadeIn 0.4s ease; }
.form-section:last-of-type { border-bottom: none; }
.form-section h3 { margin: 0 0 15px 0; font-size: 16px; color: #111827; display: flex; align-items: center; gap: 8px; }
.form-section h3 .icon { width: 24px; height: 24px; background: #EEF2F6; color: #4C6E91; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 12px; font-weight: 500; color: #4B5563; }
.form-group input, .form-group select { padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 13px; transition: 0.3s; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #4C6E91; box-shadow: 0 0 0 3px rgba(76,110,145, 0.1); }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #F3F4F6; }
.btn-submit { background: #4C6E91; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; font-size: 13px; }
.btn-submit:hover { background: #3E5C76; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(76,110,145, 0.2); }
.btn-cancel { background: #F3F4F6; color: #4B5563; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; font-size: 13px; }
.btn-cancel:hover { background: #E5E7EB; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
function toggleRoleFields() {
    const rolSelect = document.getElementById('rol_id');
    const rolId = rolSelect ? rolSelect.value : <?= json_encode((string)$locked_rol_id) ?>;
    const map = {5:'jefe_fields', 6:'calle_fields', 2:'gestor_fields'};
    document.querySelectorAll('.role-section').forEach(s => {
        const active = (s.id === map[rolId]);
        s.style.display = active ? 'block' : 'none';
        // Deshabilitar los campos de las secciones ocultas para que NO se envíen
        s.querySelectorAll('input, select, textarea').forEach(f => { f.disabled = !active; });
    });
}

async function cargarCallesPorBarrio(barrioId) {
    const callesSelect = document.getElementById('calle_id');
    const status = document.getElementById('calles_status');
    if (!callesSelect || !status) return;

    callesSelect.innerHTML = '';
    callesSelect.disabled = true;
    status.textContent = '';

    if (!barrioId) {
        callesSelect.add(new Option('Seleccione primero un barrio...', ''));
        return;
    }

    callesSelect.add(new Option('Cargando calles...', ''));
    try {
        const response = await fetch(
            `router.php?page=ajax_get_calles&barrio_id=${encodeURIComponent(barrioId)}&available_only=1`,
            { headers: { 'Accept': 'application/json' } }
        );
        const responseText = await response.text();
        if (!response.ok) throw new Error(responseText || 'No se pudo consultar las calles.');
        let calles;
        try {
            calles = JSON.parse(responseText);
        } catch (error) {
            throw new Error('La respuesta del servidor no es válida.');
        }
        if (!Array.isArray(calles)) throw new Error('La respuesta de calles no es válida.');
        callesSelect.innerHTML = '';
        if (!calles.length) {
            callesSelect.add(new Option('Este barrio no tiene calles registradas', ''));
            status.textContent = 'Registre una calle antes de asignarla.';
            return;
        }

        callesSelect.add(new Option('Seleccione una calle...', ''));
        calles.forEach(calle => callesSelect.add(new Option(calle.nombre, calle.id)));
        callesSelect.disabled = false;
    } catch (error) {
        callesSelect.innerHTML = '';
        callesSelect.add(new Option('No se pudieron cargar las calles', ''));
        status.textContent = error.message || 'Intente nuevamente.';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('rol_id');
    const barrioCalle = document.getElementById('calle_barrio_id');
    if (barrioCalle) {
        barrioCalle.addEventListener('change', () => cargarCallesPorBarrio(barrioCalle.value));
    }
    const urlRol = new URLSearchParams(window.location.search).get('rol_id')
                 || <?= json_encode($locked_rol_id > 0 ? (string)$locked_rol_id : '') ?>;
    if (urlRol && sel) { sel.value = urlRol; }
    toggleRoleFields();
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
