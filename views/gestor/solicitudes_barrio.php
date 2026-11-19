<?php
$user = check_dashboard_access([1, 2]);
$tipoVista = ($_GET['tipo'] ?? 'NuevaCalle') === 'NuevoEncargadoCalle' ? 'NuevoEncargadoCalle' : 'NuevaCalle';
$solicitudesStmt = $pdo->prepare(
    "SELECT s.*, b.nombre AS barrio_nombre, c.nombre AS calle_nombre,
            CONCAT(u.nombre,' ',u.apellido) AS solicitante
     FROM solicitudes_gestion s
     JOIN barrios b ON b.id=s.barrio_id
     LEFT JOIN calles c ON c.id=s.calle_id
     JOIN usuarios u ON u.id=s.solicitado_por
     WHERE s.estado='Pendiente' AND s.tipo=? ORDER BY s.fecha_creacion ASC"
);
$solicitudesStmt->execute([$tipoVista]);
$solicitudes = $solicitudesStmt->fetchAll(PDO::FETCH_ASSOC);
$historialStmt = $pdo->prepare(
    "SELECT s.*, b.nombre AS barrio_nombre, c.nombre AS calle_nombre,
            CONCAT(u.nombre,' ',u.apellido) AS solicitante
     FROM solicitudes_gestion s
     JOIN barrios b ON b.id=s.barrio_id
     LEFT JOIN calles c ON c.id=s.calle_id
     JOIN usuarios u ON u.id=s.solicitado_por
     WHERE s.estado <> 'Pendiente' AND s.tipo=? ORDER BY s.fecha_revision DESC LIMIT 20"
);
$historialStmt->execute([$tipoVista]);
$historial = $historialStmt->fetchAll(PDO::FETCH_ASSOC);
$conteos = $pdo->query(
    "SELECT tipo, COUNT(*) AS cantidad FROM solicitudes_gestion
     WHERE estado='Pendiente' GROUP BY tipo"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$barrios = $pdo->query("SELECT id,nombre FROM barrios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$calles = $pdo->query("SELECT id,barrio_id,nombre FROM calles ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$title = 'Solicitudes del Barrio - EcoReciclaje';
$header_title = 'Solicitudes del Barrio';
$header_subtitle = 'Aprueba solicitudes o crea calles y encargados directamente.';
ob_start();
?>
<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>
<style>
.gestion-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}
.gestion-tab{display:flex;align-items:center;gap:8px;padding:12px 16px;border:1px solid #D1D5DB;border-radius:9px;background:#fff;color:#4B5563;text-decoration:none;font-weight:600;transition:.2s}
.gestion-tab:hover{border-color:#4C6E91;background:#F7FAFC}
.gestion-tab.active{background:#1F2937;border-color:#1F2937;color:#fff}
.gestion-tab-count{min-width:22px;padding:2px 6px;border-radius:10px;background:#E5E7EB;color:#374151;text-align:center;font-size:11px}
.gestion-tab.active .gestion-tab-count{background:#4C6E91;color:#fff}
.gestion-summary{margin-bottom:20px;color:#6B7280}
.gestion-request{border:1px solid #E5E7EB;border-radius:10px;padding:16px;margin-bottom:12px;background:#fff}
.gestion-request:hover{border-color:#B8C9D8;box-shadow:0 3px 12px rgba(31,41,55,.06)}
.gestion-request-meta{color:#6B7280;font-size:12px;line-height:1.7;margin:6px 0 12px}
.gestion-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.gestion-reject{display:flex;gap:8px;flex:1;min-width:260px}
.gestion-reject input{flex:1}
.gestion-empty{padding:16px;border:1px dashed #D1D5DB;border-radius:8px;color:#6B7280;background:#F9FAFB}
.gestion-direct input,.gestion-direct select,.gestion-direct textarea{width:100%;min-height:42px;padding:10px 12px;border:1px solid #D1D5DB;border-radius:7px;background:#fff;color:#1F2937;font:inherit;font-size:12px;outline:none;transition:border-color .2s,box-shadow .2s}
.gestion-direct textarea{min-height:96px;resize:vertical}
.gestion-direct input:focus,.gestion-direct select:focus,.gestion-direct textarea:focus{border-color:#4C6E91;box-shadow:0 0 0 3px rgba(76,110,145,.14)}
.gestion-direct label{display:flex;flex-direction:column;gap:7px;color:#374151;font-weight:600}
.direct-form-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.direct-form-tab{border:1px solid #D1D5DB;border-radius:7px;background:#fff;color:#4B5563;padding:10px 14px;font:inherit;font-weight:600;cursor:pointer}
.direct-form-tab.active{background:#1F2937;border-color:#1F2937;color:#fff}
.direct-form-panel{display:none}
.direct-form-panel.active{display:block}
</style>
<div class="gestion-tabs" aria-label="Tipo de solicitudes">
    <a class="gestion-tab <?= $tipoVista==='NuevaCalle'?'active':'' ?>" href="router.php?page=solicitudes_barrio&tipo=NuevaCalle">
        Solicitudes de calles <span class="gestion-tab-count"><?= (int)($conteos['NuevaCalle'] ?? 0) ?></span>
    </a>
    <a class="gestion-tab <?= $tipoVista==='NuevoEncargadoCalle'?'active':'' ?>" href="router.php?page=solicitudes_barrio&tipo=NuevoEncargadoCalle">
        Solicitudes de encargados <span class="gestion-tab-count"><?= (int)($conteos['NuevoEncargadoCalle'] ?? 0) ?></span>
    </a>
</div>
<section class="card">
    <h3><?= $tipoVista==='NuevaCalle' ? 'Solicitudes de nuevas calles' : 'Solicitudes de encargados de calle' ?></h3>
    <p class="gestion-summary"><?= $tipoVista==='NuevaCalle' ? 'Permite o rechaza el registro de una nueva calle solicitada por un encargado de barrio.' : 'Permite o rechaza el registro de una persona como encargado de una calle.' ?></p>
    <?php if (!$solicitudes): ?><div class="gestion-empty">No hay solicitudes pendientes en esta pestaña.</div><?php endif; ?>
    <?php foreach($solicitudes as $s): ?>
        <div class="gestion-request" id="solicitud-<?= (int)$s['id'] ?>">
            <strong><?= $s['tipo']==='NuevaCalle'?'Nueva calle':'Nuevo encargado de calle' ?></strong>
            <div class="gestion-request-meta">
                Barrio: <?= htmlspecialchars($s['barrio_nombre']) ?> · Solicitó: <?= htmlspecialchars($s['solicitante']) ?><br>
                <?php if($s['tipo']==='NuevaCalle'): ?>Calle: <?= htmlspecialchars($s['nombre_calle']) ?> · <?= htmlspecialchars($s['descripcion_calle'] ?? '') ?>
                <?php else: ?>Calle: <?= htmlspecialchars($s['calle_nombre']) ?> · <?= htmlspecialchars($s['nombre'].' '.$s['apellido']) ?> · <?= htmlspecialchars($s['email']) ?><?php endif; ?>
            </div>
            <div class="gestion-actions">
                <form method="POST"><input type="hidden" name="action" value="aprobar_solicitud_gestion"><input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>"><button class="btn-primary" type="submit">Aprobar</button></form>
                <form method="POST" class="gestion-reject"><input type="hidden" name="action" value="rechazar_solicitud_gestion"><input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>"><input name="motivo_rechazo" required placeholder="Motivo del rechazo"><button class="btn-edit" type="submit">Rechazar</button></form>
            </div>
        </div>
    <?php endforeach; ?>
</section>
<?php if ($historial): ?>
<section class="card">
    <h3>Historial de <?= $tipoVista==='NuevaCalle' ? 'solicitudes de calles' : 'solicitudes de encargados' ?></h3>
    <?php foreach ($historial as $s): ?>
        <div style="border-bottom:1px solid #E5E7EB;padding:10px 0;">
            <strong><?= $s['estado']==='Aprobada' ? 'Aprobada' : 'Rechazada' ?></strong>
            · <?= $s['tipo']==='NuevaCalle' ? 'Nueva calle: '.htmlspecialchars($s['nombre_calle']) : 'Encargado de calle: '.htmlspecialchars($s['nombre'].' '.$s['apellido']) ?>
            · <?= htmlspecialchars($s['barrio_nombre']) ?>
            <?php if ($s['estado']==='Rechazada' && $s['motivo_rechazo']): ?>
                <small style="color:#7A4F4F;"> — <?= htmlspecialchars($s['motivo_rechazo']) ?></small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<div class="direct-form-tabs" role="tablist">
    <button type="button" class="direct-form-tab active" data-direct-tab="calle">Crear calle directamente</button>
    <button type="button" class="direct-form-tab" data-direct-tab="encargado">Crear encargado directamente</button>
</div>
<section class="card gestion-direct direct-form-panel active" data-direct-panel="calle">
    <h3>Crear calle directamente</h3>
    <form method="POST" class="form-grid"><input type="hidden" name="action" value="crear_calle_gestor"><label>Barrio<select name="barrio_id" required><?php foreach($barrios as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?></select></label><label>Nombre<input name="nombre_calle" required></label><button class="btn-primary">Crear calle</button></form>
</section>
<section class="card gestion-direct direct-form-panel" data-direct-panel="encargado">
    <h3>Crear encargado directamente</h3>
    <form method="POST" class="form-grid"><input type="hidden" name="action" value="crear_encargado_calle_gestor"><label>Barrio<select id="gestor-barrio-encargado" name="barrio_id" required><?php foreach($barrios as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option><?php endforeach; ?></select></label><label>Calle<select id="gestor-calle-encargado" name="calle_id" required><?php foreach($calles as $c): ?><option value="<?= $c['id'] ?>" data-barrio="<?= (int)$c['barrio_id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?></select></label><label>Nombre<input name="nombre" required></label><label>Apellido<input name="apellido" required></label><label>CI<input type="text" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" title="Ingrese exactamente 8 números"></label><label>Teléfono<input type="text" name="telefono" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números"></label><label>Correo electrónico<input type="email" name="email" placeholder="encargado@ejemplo.com"></label><button class="btn-primary">Crear encargado</button><small style="grid-column:1/-1;color:#6B7280;">Contraseña inicial: 123456. Si no indica correo, el sistema generará uno.</small></form>
</section>
<script>
(() => {
    const barrio = document.getElementById('gestor-barrio-encargado');
    const calle = document.getElementById('gestor-calle-encargado');
    if (!barrio || !calle) return;
    const filtrarCalles = () => {
        let visible = 0;
        Array.from(calle.options).forEach(option => {
            const mostrar = option.dataset.barrio === barrio.value;
            option.hidden = !mostrar;
            if (mostrar) visible++;
        });
        calle.disabled = visible === 0;
        if (visible) calle.value = Array.from(calle.options).find(option => !option.hidden).value;
    };
    barrio.addEventListener('change', filtrarCalles);
    filtrarCalles();
})();
document.querySelectorAll('[data-direct-tab]').forEach(function(tab) {
    tab.addEventListener('click', function() {
        var target = tab.getAttribute('data-direct-tab');
        document.querySelectorAll('[data-direct-tab]').forEach(function(item) {
            item.classList.toggle('active', item === tab);
        });
        document.querySelectorAll('[data-direct-panel]').forEach(function(panel) {
            panel.classList.toggle('active', panel.getAttribute('data-direct-panel') === target);
        });
    });
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
