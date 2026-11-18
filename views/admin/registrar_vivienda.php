<?php
// views/admin/registrar_vivienda.php
$user = check_dashboard_access([1, 2]);

// Get all neighborhoods for selection
$barrios = $pdo->query("SELECT id, nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$tipoSolicitado = trim((string)($_GET['tipo'] ?? ''));
$tipoPredioInicial = in_array($tipoSolicitado, TIPOS_PREDIO, true) ? $tipoSolicitado : 'Vivienda';
$esEstablecimiento = $tipoPredioInicial !== 'Vivienda';
$etiquetaPredio = tipo_predio_etiqueta($tipoPredioInicial);

$title = $esEstablecimiento ? "Registrar {$etiquetaPredio} - EcoCusco" : "Registrar Vivienda (Admin) - EcoCusco";
$header_title = $esEstablecimiento ? "Nuevo {$etiquetaPredio}" : "Nueva Vivienda";
$header_subtitle = $esEstablecimiento
    ? "Registro de establecimientos. La tarifa se define en Configuración de Pagos."
    : "Registro administrativo directo de predios en el sistema.";

ob_start();
?>
<div class="form-container" style="margin: 0 auto;">
    <form action="router.php" method="POST" class="premium-form">
        <input type="hidden" name="action" value="nuevo_vecino_admin">
        <input type="hidden" name="page" value="<?= $page === 'registrar_vivienda' && ($user['rol_id'] ?? 0) === 2 ? 'gestor_viviendas' : 'viviendas' ?>">
        
        <div class="form-section">
            <h3><span class="icon">👤</span> Propietario</h3>
            <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Nombre Completo / Familia</label>
                    <input type="text" name="propietario" required placeholder="Ej. Familia Rodríguez">
                </div>
                <div class="form-group">
                    <label>Cédula</label>
                    <div style="display:flex; gap:6px;">
                        <select name="cedula_tipo" style="width:58px; flex:0 0 58px;">
                            <option value="V">V</option>
                            <option value="E">E</option>
                        </select>
                        <input type="text" name="cedula" placeholder="Ej. 70654321" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                    <small style="font-size:10px; color:#9CA3AF; font-weight:600;">V = Venezolano · E = Extranjero. Solo números.</small>
                </div>
            </div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 15px;">
                <div class="form-group">
                    <label>Otro identificador (opcional)</label>
                    <input type="text" name="otro_identificador" placeholder="Pasaporte, cédula extranjera, RIF...">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><span class="icon">🏪</span> Tipo de predio</h3>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Tipo de predio</label>
                    <select name="tipo_predio" id="tipo_predio_select" required>
                        <?php foreach (TIPOS_PREDIO as $tipo): ?>
                            <option value="<?= $tipo ?>" <?= $tipo === $tipoPredioInicial ? 'selected' : '' ?>><?= tipo_predio_etiqueta($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="campo_cantidad_casas" style="<?= $tipoPredioInicial === 'Urbanizacion' ? '' : 'display:none;' ?>">
                    <label>Cantidad de casas</label>
                    <input type="number" name="cantidad_casas" id="cantidad_casas_field" step="1" min="1" max="100000" placeholder="Ej. 4" value="1" <?= $tipoPredioInicial === 'Urbanizacion' ? '' : 'disabled' ?>>
                    <small style="font-size:10px; color:#9CA3AF; font-weight:600;">Se cobra una tarifa por casa, pero en un solo pago mensual. Ej.: 4 casas × Bs. 3 = Bs. 12.</small>
                </div>
            </div>
            <small style="font-size:10px; color:#6B7280;">El precio de cada tipo de predio se define en <strong>Configuración de Pagos</strong> del barrio.</small>
        </div>

        <div class="form-section">
            <h3><span class="icon">📍</span> Ubicación</h3>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Barrio</label>
                    <select name="barrio_id" id="barrio_select" required onchange="updateStreets(this.value)">
                        <option value="">Seleccione barrio...</option>
                        <?php foreach($barrios as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Calle</label>
                    <select name="calle_id" id="calle_select" required disabled>
                        <option value="">Primero elija barrio...</option>
                    </select>
                </div>
            </div>
            <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-top: 15px;">
                <div class="form-group">
                    <label>Dirección Específica</label>
                    <input type="text" name="direccion" required placeholder="Ej. Av. Principal con Calle 4">
                </div>
                <div class="form-group">
                    <label>Número de Casa</label>
                    <input type="text" name="numero_casa" placeholder="Ej. A-12">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="router.php?page=gestor_viviendas" class="btn-cancel">Volver al listado</a>
            <button type="submit" class="btn-submit">Registrar <?= $esEstablecimiento ? $etiquetaPredio : 'Vivienda' ?></button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipo = document.getElementById('tipo_predio_select');
    const bloque = document.getElementById('campo_cantidad_casas');
    const casas = document.getElementById('cantidad_casas_field');
    if (!tipo || !bloque || !casas) return;
    const actualizar = function () {
        const esUrbanizacion = tipo.value === 'Urbanizacion';
        bloque.style.display = esUrbanizacion ? '' : 'none';
        casas.disabled = !esUrbanizacion;
        casas.required = esUrbanizacion;
    };
    tipo.addEventListener('change', actualizar);
    actualizar();
});

async function updateStreets(barrioId) {
    const calleSelect = document.getElementById('calle_select');
    calleSelect.innerHTML = '<option value="">Cargando...</option>';
    calleSelect.disabled = true;

    if (!barrioId) return;

    try {
        const response = await fetch('router.php?page=ajax_get_calles&barrio_id=' + barrioId);
        const calles = await response.json();
        
        calleSelect.innerHTML = '<option value="">Seleccione calle...</option>';
        calles.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.nombre;
            calleSelect.appendChild(opt);
        });
        calleSelect.disabled = false;
    } catch (e) {
        calleSelect.innerHTML = '<option value="">Error al cargar</option>';
        console.error(e);
    }
}
</script>

<style>
.form-container { padding-bottom: 30px; }
.premium-form { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.form-section { margin-bottom: 20px; border-bottom: 1px solid #F3F4F6; padding-bottom: 20px; }
.form-section h3 { margin: 0 0 15px 0; font-size: 15px; color: #111827; display: flex; align-items: center; gap: 8px; }
.form-section h3 .icon { width: 22px; height: 22px; background: #F3F4F6; color: #374151; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 11px; font-weight: 700; color: #6B7280; text-transform: uppercase; }
.form-group input, .form-group select { padding: 10px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 13px; transition: 0.3s; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 15px; }
.btn-submit { background: #111827; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
.btn-cancel { background: #F3F4F6; color: #4B5563; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; }
@media(max-width:600px){.form-grid{grid-template-columns:1fr!important}}
</style>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
