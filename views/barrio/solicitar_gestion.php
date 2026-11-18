<?php
$user = check_dashboard_access([5]);
$barrioStmt = $pdo->prepare("SELECT b.id, b.nombre FROM barrios b JOIN detalles_encargado_barrio d ON d.barrio_id=b.id WHERE d.usuario_id=?");
$barrioStmt->execute([$user['id']]);
$barrio = $barrioStmt->fetch(PDO::FETCH_ASSOC);
$callesStmt = $pdo->prepare("SELECT c.id,c.nombre FROM calles c WHERE c.barrio_id=? ORDER BY c.nombre");
$callesStmt->execute([(int)$barrio['id']]);
$calles = $callesStmt->fetchAll(PDO::FETCH_ASSOC);
$pendientesStmt = $pdo->prepare(
    "SELECT s.*, c.nombre AS calle_nombre
     FROM solicitudes_gestion s LEFT JOIN calles c ON c.id=s.calle_id
     WHERE s.solicitado_por=? AND s.estado='Pendiente' ORDER BY s.fecha_creacion DESC"
);
$pendientesStmt->execute([$user['id']]);
$pendientes = $pendientesStmt->fetchAll(PDO::FETCH_ASSOC);
$historialStmt = $pdo->prepare(
    "SELECT s.*, c.nombre AS calle_nombre
     FROM solicitudes_gestion s LEFT JOIN calles c ON c.id=s.calle_id
     WHERE s.solicitado_por=? AND s.estado <> 'Pendiente'
     ORDER BY s.fecha_revision DESC"
);
$historialStmt->execute([$user['id']]);
$historial = $historialStmt->fetchAll(PDO::FETCH_ASSOC);
$tab = ($_GET['tab'] ?? 'nueva') === 'historial' ? 'historial' : 'nueva';
$title = 'Solicitar Calle o Encargado - EcoCusco';
$header_title = 'Solicitar gestión al Gestor';
$header_subtitle = 'Barrio: ' . htmlspecialchars($barrio['nombre'] ?? 'Sin asignar');
ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>
<div class="page-intro"><div class="page-intro-icon">✦</div><div><strong>Solicitar gestión al Gestor</strong><span>Envía una solicitud clara y consulta su estado. El Gestor revisará la información antes de aprobarla.</span></div></div>
<nav class="page-tabs" aria-label="Solicitudes de gestión">
    <a class="page-tab <?= $tab==='nueva'?'active':'' ?>" href="router.php?page=solicitar_gestion&tab=nueva">＋ Nueva solicitud</a>
    <a class="page-tab <?= $tab==='historial'?'active':'' ?>" href="router.php?page=solicitar_gestion&tab=historial">▤ Historial (<?= count($historial) ?>)</a>
</nav>
<?php if ($tab === 'nueva'): ?>
<div class="page-tabs" role="tablist">
    <button type="button" class="page-tab active" data-form-tab="calle">Nueva calle</button>
    <button type="button" class="page-tab" data-form-tab="encargado">Encargado de calle</button>
</div>
<section class="card gestion-card gestion-form-panel active" data-form-panel="calle">
        <div class="panel-heading"><div><span class="eyebrow">Paso 1</span><h3>Solicitar nueva calle</h3><p>El Gestor revisará los datos antes de crearla.</p></div><span>🛣️</span></div>
        <form method="POST" action="router.php?page=solicitar_gestion" class="form-grid">
            <input type="hidden" name="action" value="solicitar_nueva_calle">
            <div class="form-group"><label>Nombre de la calle</label><input name="nombre_calle" required placeholder="Ej. Calle Los Pinos"></div>
            <button class="btn-primary" type="submit">Enviar solicitud de calle</button>
        </form>
</section>
<section class="card gestion-card gestion-form-panel" data-form-panel="encargado">
        <div class="panel-heading"><div><span class="eyebrow">Paso 1</span><h3>Solicitar encargado de calle</h3><p>Indica la calle y los datos. El Gestor creará el usuario al aprobar.</p></div><span>👤</span></div>
        <form method="POST" action="router.php?page=solicitar_gestion" class="form-grid">
            <input type="hidden" name="action" value="solicitar_encargado_calle">
            <div class="form-group" style="grid-column:1/-1"><label>Calle</label><select name="calle_id" required><option value="">Seleccione...</option><?php foreach($calles as $calle): ?><option value="<?= (int)$calle['id'] ?>"><?= htmlspecialchars($calle['nombre']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Nombre</label><input name="nombre" required></div>
            <div class="form-group"><label>Apellido</label><input name="apellido" required></div>
            <div class="form-group"><label>CI</label><input type="text" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números"></div>
            <div class="form-group"><label>Teléfono</label><input type="text" name="telefono" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números"></div>
            <div class="form-group" style="grid-column:1/-1"><label>Correo electrónico</label><input type="email" name="email" placeholder="encargado@ejemplo.com"><small style="color:#6B7280;">Opcional. Si lo omites, el sistema generará uno al aprobar la solicitud.</small></div>
            <button class="btn-primary" type="submit">Enviar solicitud de encargado</button>
        </form>
</section>
<section class="card">
    <div class="panel-heading"><div><span class="eyebrow">Seguimiento</span><h3>Mis solicitudes pendientes</h3><p>Consulta lo que aún está en revisión.</p></div><strong><?= count($pendientes) ?></strong></div>
    <?php if (!$pendientes): ?><div class="empty-state"><strong>Todo está al día</strong>No tienes solicitudes pendientes.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Detalle</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
        <?php foreach($pendientes as $s): ?><tr><td><?= $s['tipo']==='NuevaCalle'?'Nueva calle':'Nuevo encargado' ?></td><td><?= htmlspecialchars($s['nombre_calle'] ?: ($s['calle_nombre'].' — '.$s['nombre'].' '.$s['apellido'])) ?></td><td><?= date('d/m/Y', strtotime($s['fecha_creacion'])) ?></td><td>Pendiente</td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="card">
    <div class="panel-heading"><div><span class="eyebrow">Seguimiento</span><h3>Historial de mis solicitudes</h3><p>Revisa las solicitudes que ya fueron procesadas.</p></div><strong><?= count($historial) ?></strong></div>
    <?php if (!$historial): ?>
        <div class="empty-state"><strong>Aún no hay historial</strong>Todavía no tienes solicitudes procesadas.</div>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Detalle</th><th>Fecha de respuesta</th><th>Estado</th><th>Motivo</th></tr></thead><tbody>
        <?php foreach ($historial as $s): ?>
            <tr>
                <td><?= $s['tipo']==='NuevaCalle'?'Nueva calle':'Nuevo encargado' ?></td>
                <td><?= htmlspecialchars($s['tipo']==='NuevaCalle' ? $s['nombre_calle'] : ($s['calle_nombre'].' — '.$s['nombre'].' '.$s['apellido'])) ?></td>
                <td><?= $s['fecha_revision'] ? date('d/m/Y H:i', strtotime($s['fecha_revision'])) : '—' ?></td>
                <td><span class="gestion-status <?= strtolower($s['estado']) ?>"><?= htmlspecialchars($s['estado']) ?></span></td>
                <td><?= htmlspecialchars($s['motivo_rechazo'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<script>
document.querySelectorAll('[data-form-tab]').forEach(function(tab) {
    tab.addEventListener('click', function() {
        var target = tab.getAttribute('data-form-tab');
        document.querySelectorAll('[data-form-tab]').forEach(function(item) {
            item.classList.toggle('active', item === tab);
        });
        document.querySelectorAll('[data-form-panel]').forEach(function(panel) {
            panel.classList.toggle('active', panel.getAttribute('data-form-panel') === target);
        });
    });
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
