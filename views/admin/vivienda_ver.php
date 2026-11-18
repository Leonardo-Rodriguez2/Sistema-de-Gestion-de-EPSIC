<?php
// views/admin/vivienda_ver.php
$user = check_dashboard_access([1, 2]);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT v.*, b.nombre as barrio_nombre, c.nombre as calle_nombre,
            dec_calle.usuario_id as encargado_id, u.nombre as enc_nombre, u.apellido as enc_apellido
     FROM viviendas v
     LEFT JOIN barrios b ON v.barrio_id = b.id
     LEFT JOIN calles c ON v.calle_id = c.id
     LEFT JOIN detalles_encargado_calle dec_calle ON dec_calle.calle_id = v.calle_id
     LEFT JOIN usuarios u ON dec_calle.usuario_id = u.id
     WHERE v.id = ?"
);
$stmt->execute([$id]);
$vivienda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vivienda) {
    echo "<div class='alert alert-error'>Vivienda no encontrada.</div>";
    return;
}

$title = "Ver Vivienda - EPSIC";
$header_title = "Vivienda #" . $vivienda['id'];
$header_subtitle = "Detalle del predio registrado.";

ob_start();
?>
<style>
    .vv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; max-width: 900px; margin: 0 auto; }
    .vv-card { background: #fff; border: 1px solid #EEE; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .vv-card h3 { margin: 0 0 12px; font-size: 14px; color: #374151; }
    .vv-row { display: flex; justify-content: space-between; font-size: 13px; border-bottom: 1px solid #F9FAFB; padding: 4px 0; }
    .vv-row .lab { color: #6B7280; }
    .vv-row .val { color: #111827; font-weight: 600; text-align: right; }
</style>

<div style="max-width: 900px; margin: 0 auto;">
    <div class="vv-grid">
        <div class="vv-card">
            <h3>👤 Propietario</h3>
            <div class="vv-row"><span class="lab">Nombre</span><span class="val"><?= htmlspecialchars($vivienda['propietario']) ?></span></div>
            <div class="vv-row"><span class="lab">Cédula</span><span class="val"><?= !empty($vivienda['cedula']) ? htmlspecialchars(($vivienda['cedula_tipo'] ?? 'V') . '-' . $vivienda['cedula']) : '—' ?></span></div>
            <div class="vv-row"><span class="lab">Otro identificador</span><span class="val"><?= htmlspecialchars($vivienda['otro_identificador'] ?? '') ?: '—' ?></span></div>
            <div class="vv-row"><span class="lab">Teléfono</span><span class="val"><?= htmlspecialchars($vivienda['telefono'] ?? '—') ?: '—' ?></span></div>
        </div>

        <div class="vv-card">
            <h3>🏪 Tipo de predio</h3>
            <div class="vv-row"><span class="lab">Tipo</span><span class="val"><?= htmlspecialchars(tipo_predio_etiqueta($vivienda['tipo_predio'] ?? 'Vivienda')) ?></span></div>
            <div class="vv-row"><span class="lab">Cobro mensual</span><span class="val"><?= htmlspecialchars(etiqueta_monto_predio($vivienda['tipo_predio'] ?? 'Vivienda', $vivienda['cantidad_casas'] ?? 1, tarifas_barrio($pdo, $vivienda['barrio_id']))) ?></span></div>
            <?php if (($vivienda['tipo_predio'] ?? 'Vivienda') === 'Urbanizacion'): ?>
                <div class="vv-row"><span class="lab">Casas agrupadas</span><span class="val"><?= (int)($vivienda['cantidad_casas'] ?? 1) ?></span></div>
            <?php endif; ?>
        </div>

        <div class="vv-card">
            <h3>📍 Ubicación</h3>
            <div class="vv-row"><span class="lab">Barrio</span><span class="val"><?= htmlspecialchars($vivienda['barrio_nombre'] ?? '—') ?></span></div>
            <div class="vv-row"><span class="lab">Calle</span><span class="val"><?= htmlspecialchars($vivienda['calle_nombre'] ?? '—') ?></span></div>
            <div class="vv-row"><span class="lab">N° Casa</span><span class="val"><?= htmlspecialchars($vivienda['numero_casa'] ?? '—') ?: '—' ?></span></div>
            <div class="vv-row"><span class="lab">Dirección</span><span class="val"><?= htmlspecialchars($vivienda['direccion']) ?></span></div>
            <div class="vv-row"><span class="lab">Referencia</span><span class="val"><?= htmlspecialchars($vivienda['referencia'] ?? '—') ?: '—' ?></span></div>
        </div>

        <div class="vv-card">
            <h3>🧾 Estado del Servicio</h3>
            <div class="vv-row"><span class="lab">Estado</span><span class="val"><?= htmlspecialchars($vivienda['estado_servicio']) ?></span></div>
            <div class="vv-row"><span class="lab">Exento de cobro</span><span class="val"><?= $vivienda['exento_cobro'] ? 'Sí' : 'No' ?></span></div>
            <div class="vv-row"><span class="lab">Encargado de Calle</span><span class="val"><?= htmlspecialchars(trim(($vivienda['enc_nombre'] ?? '') . ' ' . ($vivienda['enc_apellido'] ?? '')) ?: 'Sin asignar') ?></span></div>
            <div class="vv-row"><span class="lab">Registro</span><span class="val"><?= !empty($vivienda['fecha_registro']) ? date('d/m/Y', strtotime($vivienda['fecha_registro'])) : '—' ?></span></div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <a href="router.php?page=vivienda_editar&id=<?= $vivienda['id'] ?>" class="btn-primary" style="text-decoration:none; padding: 8px 16px;">✏️ Editar</a>
        <a href="router.php?page=gestor_viviendas" class="btn-primary" style="text-decoration:none; background:#6B7280; padding: 8px 16px;">⬅ Volver</a>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
