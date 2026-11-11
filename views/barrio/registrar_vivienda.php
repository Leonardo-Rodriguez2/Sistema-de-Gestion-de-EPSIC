<?php
// views/barrio/registrar_vivienda.php
$user = check_dashboard_access([5]);

// 1. Obtener el barrio asignado y su nombre
$bStmt = $pdo->prepare("SELECT b.id, b.nombre FROM detalles_encargado_barrio d JOIN barrios b ON d.barrio_id = b.id WHERE d.usuario_id = ?");
$bStmt->execute([$user['id']]);
$barrio_info = $bStmt->fetch(PDO::FETCH_ASSOC);

if (!$barrio_info) {
    die("No tienes un barrio asignado.");
}

$current_barrio = $barrio_info['id'];

// 2. Datos de solicitud si existe
$solicitud_data = null;
if (isset($_GET['solicitud_id'])) {
    $sStmt = $pdo->prepare("SELECT s.*, c.barrio_id FROM solicitudes_vivienda s JOIN calles c ON s.calle_id = c.id WHERE s.id = ?");
    $sStmt->execute([$_GET['solicitud_id']]);
    $solicitud_data = $sStmt->fetch(PDO::FETCH_ASSOC);
}

// 3. Calles del barrio y su encargado asignado
$calles = $pdo->prepare(
    "SELECT c.id, c.nombre,
            CONCAT(u.nombre, ' ', u.apellido) AS encargado_nombre
     FROM calles c
     LEFT JOIN detalles_encargado_calle d ON d.calle_id = c.id
     LEFT JOIN usuarios u ON u.id = d.usuario_id
     WHERE c.barrio_id = ?
     ORDER BY c.nombre"
);
$calles->execute([$current_barrio]);
$calles = $calles->fetchAll(PDO::FETCH_ASSOC);

// Calle pre-seleccionada
$pre_calle = $_GET['calle_id'] ?? ($solicitud_data['calle_id'] ?? 0);

$title = "Registrar Vivienda - EcoCusco";
$header_title = "Nueva Vivienda";
$header_subtitle = "Registro administrativo de predios en el sector " . htmlspecialchars($barrio_info['nombre']);

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <div class="form-container" style="margin: 0 auto; padding-bottom: 30px;">
        <form method="POST" action="router.php?page=viviendas" class="premium-form">
            <input type="hidden" name="form_type" value="nuevo_vecino">
            <input type="hidden" name="barrio_id" value="<?= $current_barrio ?>">
            <?php if ($solicitud_data): ?>
                <input type="hidden" name="solicitud_id" value="<?= $solicitud_data['id'] ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h3><span class="icon">👤</span> Propietario</h3>
                <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Nombre Completo / Familia</label>
                        <input type="text" name="propietario" value="<?= htmlspecialchars($solicitud_data['propietario'] ?? '') ?>" 
                               required placeholder="Ej. Familia Rodríguez">
                    </div>
                    <div class="form-group">
                        <label>Cédula</label>
                        <div style="display:flex; gap:6px;">
                            <select name="cedula_tipo" style="width:58px; flex:0 0 58px;">
                                <option value="V" <?= ($solicitud_data['cedula_tipo'] ?? 'V') === 'V' ? 'selected' : '' ?>>V</option>
                                <option value="E" <?= ($solicitud_data['cedula_tipo'] ?? 'V') === 'E' ? 'selected' : '' ?>>E</option>
                            </select>
                            <input type="text" name="cedula" value="<?= htmlspecialchars($solicitud_data['cedula'] ?? '') ?>" 
                                   placeholder="Ej. 70654321" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números" oninput="this.value=this.value.replace(/\D/g,'')">
                        </div>
                        <small style="font-size:10px; color:#9CA3AF; font-weight:600;">V = Venezolano · E = Extranjero. Solo números.</small>
                    </div>
                </div>
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 15px;">
                    <div class="form-group">
                        <label>Otro identificador (opcional)</label>
                        <input type="text" name="otro_identificador" value="<?= htmlspecialchars($solicitud_data['otro_identificador'] ?? '') ?>" placeholder="Pasaporte, cédula extranjera, RIF...">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><span class="icon">📍</span> Ubicación</h3>
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Barrio</label>
                        <input type="text" value="<?= htmlspecialchars($barrio_info['nombre']) ?>" disabled 
                               style="background: #F9FAFB; color: #6B7280; font-weight: 600;">
                    </div>
                    <div class="form-group">
                        <label>Calle</label>
                        <select name="calle_id" required>
                            <option value="">Seleccione calle...</option>
                            <?php foreach($calles as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                        data-encargado="<?= htmlspecialchars($c['encargado_nombre'] ?? 'Sin encargado asignado', ENT_QUOTES, 'UTF-8') ?>"
                                        <?= ($pre_calle == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div class="form-group">
                        <label>Encargado de Calle</label>
                        <input type="text" id="encargado_calle_nombre" value="Seleccione una calle..." disabled
                               style="background: #F9FAFB; color: #6B7280; font-weight: 600;">
                        <small>Se asigna automáticamente según la calle seleccionada.</small>
                    </div>
                    <div class="form-group">
                        <label>Número de Casa</label>
                        <input type="text" name="numero" value="<?= htmlspecialchars($solicitud_data['numero_casa'] ?? '') ?>" 
                               placeholder="Ej. A-12">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Dirección Específica / Referencia</label>
                    <input type="text" name="direccion" value="<?= htmlspecialchars(($solicitud_data['referencia'] ?? '') ?: ($solicitud_data['direccion'] ?? '')) ?>" 
                           required placeholder="Ej. Av. Principal con Calle 4 o fachada color verde">
                </div>
            </div>

            <div class="form-actions">
                <a href="router.php?page=dashboard" class="btn-cancel">Volver al panel</a>
                <button type="submit" class="btn-submit">
                    <?= $solicitud_data ? "Validar y Registrar" : "Registrar Vivienda" ?>
                </button>
            </div>
        </form>
    </div>

    <style>
    .form-container { padding-bottom: 30px; }
    .premium-form { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .form-section { margin-bottom: 20px; border-bottom: 1px solid #F3F4F6; padding-bottom: 20px; }
    .form-section h3 { margin: 0 0 15px 0; font-size: 15px; color: #111827; display: flex; align-items: center; gap: 8px; }
    .form-section h3 .icon { width: 22px; height: 22px; background: #F3F4F6; color: #374151; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 11px; font-weight: 700; color: #6B7280; text-transform: uppercase; }
    .form-group input, .form-group select { padding: 10px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 13px; transition: 0.3s; }
    .form-group input:focus, .form-group select:focus { border-color: #111827; outline: none; box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.05); }
    .form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 15px; }
    .btn-submit { background: #111827; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.3s; }
    .btn-submit:hover { background: #1F2937; transform: translateY(-1px); }
    .btn-cancel { background: #F3F4F6; color: #4B5563; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
    .btn-cancel:hover { background: #E5E7EB; }
    @media(max-width:600px){.form-grid{grid-template-columns:1fr!important}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const calleSelect = document.querySelector('select[name="calle_id"]');
        const encargadoInput = document.getElementById('encargado_calle_nombre');
        if (!calleSelect || !encargadoInput) return;

        function actualizarEncargado() {
            const opcion = calleSelect.options[calleSelect.selectedIndex];
            encargadoInput.value = opcion && calleSelect.value
                ? (opcion.dataset.encargado || 'Sin encargado asignado')
                : 'Seleccione una calle...';
        }

        calleSelect.addEventListener('change', actualizarEncargado);
        actualizarEncargado();
    });
    </script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
