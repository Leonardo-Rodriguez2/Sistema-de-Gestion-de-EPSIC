<?php
// views/components/recepcion_residuos.php
// Módulo compartido de recepción de residuos para todos los roles internos.
global $pdo;

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

$user = check_dashboard_access([1, 2, 3, 5, 6]);
$page = 'recepcion_residuos';

$recepcionModel = new \app\models\recepcionModel();
$recepcionModel->asegurarTabla();
if (empty($pdo)) {
    $pdo = (new \app\models\mainModel())->conectar();
}

$rol = (int)($user['rol_id'] ?? 0);
$alcance = $recepcionModel->alcanceUsuario($user);
$barrios = $recepcionModel->barrios();

// El encargado de barrio solo ve sus calles; el de calle, únicamente la suya.
$barrioFiltroCalles = null;
if ($rol === 5 && !empty($alcance['barrio_id'])) {
    $barrioFiltroCalles = (int)$alcance['barrio_id'];
} elseif ($rol === 6 && !empty($alcance['barrio_id'])) {
    $barrioFiltroCalles = (int)$alcance['barrio_id'];
}
$calles = $recepcionModel->calles($barrioFiltroCalles);

$sinZona = ($rol === 5 && empty($alcance['barrio_id']))
        || ($rol === 6 && (empty($alcance['calle_id']) || empty($alcance['barrio_id'])));
$puedeRegistrar = !$sinZona;

$filtros = [
    'tipo_material' => trim((string)($_GET['tipo_material'] ?? '')),
    'origen_tipo'   => trim((string)($_GET['origen_tipo'] ?? '')),
    'estado'        => trim((string)($_GET['estado'] ?? '')),
    'barrio_id'     => (int)($_GET['barrio_id'] ?? 0),
    'calle_id'      => (int)($_GET['calle_id'] ?? 0),
    'desde'         => trim((string)($_GET['desde'] ?? '')),
    'hasta'         => trim((string)($_GET['hasta'] ?? '')),
    'q'             => trim((string)($_GET['q'] ?? '')),
];

$recepciones = $recepcionModel->listar($user, $filtros);
$resumen = $recepcionModel->resumen($user, $filtros);
$porMaterial = $recepcionModel->resumenPorMaterial($user, $filtros);

// ─── Metadatos para reporte / exportación ─────────────────────────────────
$barrioNombreFiltro = '';
foreach ($barrios as $b) {
    if ((int)$filtros['barrio_id'] === (int)$b['id']) {
        $barrioNombreFiltro = $b['nombre'];
        break;
    }
}
$calleNombreFiltro = '';
foreach ($calles as $c) {
    if ((int)$filtros['calle_id'] === (int)$c['id']) {
        $calleNombreFiltro = $c['nombre'];
        break;
    }
}
$filtrosMetaRecepcion = [
    'Buscar' => $filtros['q'] !== '' ? $filtros['q'] : null,
    'Material' => $filtros['tipo_material'] !== '' ? $filtros['tipo_material'] : null,
    'Origen' => $filtros['origen_tipo'] !== '' ? $filtros['origen_tipo'] : null,
    'Estado' => $filtros['estado'] !== '' ? $filtros['estado'] : null,
    'Desde' => $filtros['desde'] !== '' ? $filtros['desde'] : null,
    'Hasta' => $filtros['hasta'] !== '' ? $filtros['hasta'] : null,
    'Barrio' => $barrioNombreFiltro !== '' ? $barrioNombreFiltro : null,
    'Calle' => $calleNombreFiltro !== '' ? $calleNombreFiltro : null,
];

// ─── Exportación a Excel (todas las filas del filtro) ─────────────────────
if (reporte_exportar_solicitado()) {
    $exportRecepciones = $recepcionModel->listar($user, $filtros, 5000);
    $filasRecepcion = [];
    foreach ($exportRecepciones as $r) {
        $lugar = trim(($r['barrio_nombre'] ?? '') . ' / ' . ($r['calle_nombre'] ?? ''), ' /');
        $filasRecepcion[] = [
            date('d/m/Y', strtotime($r['fecha_recepcion'])),
            $r['tipo_material'],
            number_format((float)$r['peso_kg'], 2, '.', ','),
            $r['origen_tipo'],
            $r['barrio_nombre'] ?? '',
            $r['calle_nombre'] ?? '',
            $r['referencia_origen'] ?? '',
            $r['observaciones_recepcion'] ?? '',
            trim(($r['registrado_nombre'] ?? '') . ' ' . ($r['registrado_apellido'] ?? '')),
            $r['estado'],
            $r['motivo_anulacion'] ?? '',
        ];
    }

    reporte_exportar_excel(
        'recepciones_residuos',
        'Recepción de residuos',
        ['Fecha', 'Material', 'Peso (kg)', 'Origen', 'Barrio', 'Calle', 'Referencia', 'Observaciones', 'Registró', 'Estado', 'Motivo de anulación'],
        $filasRecepcion,
        array_merge($filtrosMetaRecepcion, [
            'Registros' => (int)($resumen['total'] ?? 0),
            'Kg recibidos' => number_format((float)($resumen['kg'] ?? 0), 2, '.', ',') . ' kg',
            'Kg de hoy' => number_format((float)($resumen['kg_hoy'] ?? 0), 2, '.', ',') . ' kg',
        ]),
        'Historial filtrado de material recibido'
    );
}

$materialIconos = [
    'Plástico'    => '🧴',
    'Papel'       => '📄',
    'Cartón'      => '📦',
    'Vidrio'      => '🍾',
    'Metal'       => '🔩',
    'Orgánico'    => '🌱',
    'Electrónico' => '🔌',
    'Textil'      => '👕',
    'Otro'        => '♻️',
];

$barrioFijoNombre = '';
foreach ($barrios as $b) {
    if (!empty($alcance['barrio_id']) && (int)$b['id'] === (int)$alcance['barrio_id']) {
        $barrioFijoNombre = $b['nombre'];
        break;
    }
}
$calleFijaNombre = '';
foreach ($calles as $c) {
    if (!empty($alcance['calle_id']) && (int)$c['id'] === (int)$alcance['calle_id']) {
        $calleFijaNombre = $c['nombre'];
        break;
    }
}

$hoy = date('Y-m-d');

$title = 'Recepción de residuos - EPSIC';
$header_title = 'Recepción de residuos';
$header_subtitle = 'Registra el material que ingresa y consulta el historial por material, fecha y procedencia.';

ob_start();
?>

<?= reporte_encabezado_impresion(
    'Recepción de residuos',
    'Historial filtrado de material recibido',
    array_merge($filtrosMetaRecepcion, [
        'Registros' => (int)($resumen['total'] ?? 0),
        'Kg recibidos' => number_format((float)($resumen['kg'] ?? 0), 2, '.', ',') . ' kg',
    ])
) ?>
<div class="seg-toolbar" style="margin-bottom:14px;"><?= reporte_botonera() ?></div>

<style>
.recep-stats { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:14px; margin-bottom:20px; }
.recep-stat { padding:16px 18px; }
.recep-stat-label { color:#6B7280; font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; margin-bottom:6px; }
.recep-stat-value { color:#111827; font-size:24px; line-height:1.1; font-weight:800; }
.recep-stat-note { color:#9CA3AF; font-size:10px; margin-top:5px; }
.recep-grid { display:grid; grid-template-columns:minmax(320px, 390px) minmax(0,1fr); gap:20px; align-items:start; }
.recep-card { padding:20px; }
.recep-card h3 { margin:0 0 4px; font-size:16px; color:#111827; }
.recep-card .recep-help { margin:0 0 16px; color:#6B7280; font-size:11px; line-height:1.5; }
.recep-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.recep-form-grid .recep-full { grid-column:1 / -1; }
.recep-field { display:flex; flex-direction:column; gap:6px; }
.recep-field label { color:#4B5563; font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
.recep-field input, .recep-field select, .recep-field textarea {
    width:100%; box-sizing:border-box; padding:10px 11px; border:1px solid #D1D5DB;
    border-radius:4px; background:#fff; color:#111827; font:inherit; font-size:12px;
}
.recep-field textarea { resize:vertical; min-height:78px; }
.recep-field input:focus, .recep-field select:focus, .recep-field textarea:focus {
    border-color:#4C6E91; outline:none; box-shadow:0 0 0 3px rgba(76,110,145,.12);
}
.recep-zone { grid-column:1 / -1; background:#F4F6F8; border:1px solid #D6E1EA; border-left:4px solid #4C6E91; border-radius:4px; padding:10px 12px; color:#33485C; font-size:11px; line-height:1.5; }
.recep-actions { grid-column:1 / -1; display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:4px; }
.recep-actions button { background:#176b87; color:#fff; border:none; border-radius:4px; padding:10px 16px; font-size:12px; font-weight:700; cursor:pointer; }
.recep-actions button:hover { background:#0e4f68; }
.recep-actions button:disabled { background:#9CA3AF; cursor:not-allowed; }
.recep-filters { display:grid; grid-template-columns:repeat(6, minmax(110px,1fr)); gap:10px; align-items:end; padding:14px 16px; margin-bottom:16px; }
.recep-filters .recep-field label { font-size:9px; }
.recep-filters .recep-field input, .recep-filters .recep-field select { padding:8px 9px; font-size:11px; }
.recep-filter-actions { display:flex; gap:6px; align-items:center; }
.recep-filter-actions button { background:#4C6E91; color:#fff; border:none; border-radius:4px; padding:9px 12px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap; }
.recep-filter-actions a { color:#6B7280; font-size:11px; text-decoration:none; white-space:nowrap; }
.recep-filter-actions a:hover { color:#111827; text-decoration:underline; }
.recep-materials { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px; }
.recep-material-chip { display:inline-flex; align-items:center; gap:6px; border:1px solid #E5E7EB; background:#fff; border-radius:20px; padding:6px 10px; color:#374151; font-size:11px; }
.recep-material-chip strong { color:#111827; }
.recep-table-card { padding:0; overflow:hidden; }
.recep-table-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:16px 18px; border-bottom:1px solid #E5E7EB; }
.recep-table-head h3 { margin:0; }
.recep-table-head span { color:#9CA3AF; font-size:10px; }
.recep-table { margin:0; }
.recep-table th { background:#F9FAFB; }
.recep-table td { vertical-align:top; }
.recep-material { display:flex; align-items:center; gap:7px; font-weight:700; color:#111827; white-space:nowrap; }
.recep-origin { font-size:11px; color:#4B5563; line-height:1.45; }
.recep-origin small { color:#9CA3AF; }
.recep-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:10px; font-weight:700; white-space:nowrap; }
.recep-badge-registrada { background:#E9EFF4; color:#33485C; }
.recep-badge-anulada { background:#F4EDED; color:#7A4F4F; }
.recep-anular { display:flex; gap:5px; align-items:center; min-width:170px; }
.recep-anular input { width:90px; min-width:70px; padding:6px 7px; border:1px solid #D1D5DB; border-radius:3px; font-size:10px; }
.recep-anular button { background:#F4EDED; color:#7A4F4F; border:1px solid #E8D8D8; border-radius:3px; padding:6px 8px; font-size:10px; font-weight:700; cursor:pointer; white-space:nowrap; }
.recep-anular button:hover { background:#EADDDD; }
.recep-empty { text-align:center; padding:46px 20px; color:#9CA3AF; }
.recep-empty .big { font-size:42px; margin-bottom:8px; }
.recep-empty h3 { margin:0 0 5px; color:#374151; }
.recep-empty p { margin:0; font-size:12px; }
@media (max-width:1200px) {
    .recep-grid { grid-template-columns:1fr; }
    .recep-filters { grid-template-columns:repeat(3, minmax(120px,1fr)); }
}
@media (max-width:800px) {
    .recep-stats { grid-template-columns:1fr 1fr; }
    .recep-filters { grid-template-columns:1fr 1fr; }
    .recep-form-grid { grid-template-columns:1fr; }
    .recep-form-grid .recep-full, .recep-zone, .recep-actions { grid-column:1; }
}
@media (max-width:480px) {
    .recep-stats, .recep-filters { grid-template-columns:1fr; }
}
</style>

<?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

<?php if ($sinZona): ?>
    <div class="alert alert-warning">
        No tienes una zona asignada. Un administrador o gestor debe asignarte un
        <?= $rol === 5 ? 'barrio' : 'calle' ?> para que puedas registrar recepciones.
    </div>
<?php endif; ?>

<div class="recep-stats">
    <div class="card recep-stat">
        <div class="recep-stat-label">Recepciones</div>
        <div class="recep-stat-value"><?= number_format((int)($resumen['total'] ?? 0), 0, '.', ',') ?></div>
        <div class="recep-stat-note"><?= number_format((int)($resumen['activas'] ?? 0), 0, '.', ',') ?> activa(s)</div>
    </div>
    <div class="card recep-stat">
        <div class="recep-stat-label">Kg recibidos</div>
        <div class="recep-stat-value"><?= number_format((float)($resumen['kg'] ?? 0), 2, '.', ',') ?></div>
        <div class="recep-stat-note">Sin contar anuladas</div>
    </div>
    <div class="card recep-stat">
        <div class="recep-stat-label">Kg de hoy</div>
        <div class="recep-stat-value"><?= number_format((float)($resumen['kg_hoy'] ?? 0), 2, '.', ',') ?></div>
        <div class="recep-stat-note"><?= date('d/m/Y') ?></div>
    </div>
    <div class="card recep-stat">
        <div class="recep-stat-label">Materiales</div>
        <div class="recep-stat-value"><?= count($porMaterial) ?></div>
        <div class="recep-stat-note">Con recepciones registradas</div>
    </div>
</div>

<?php if (!empty($porMaterial)): ?>
<div class="recep-materials">
    <?php foreach ($porMaterial as $m): ?>
        <span class="recep-material-chip">
            <span><?= $materialIconos[$m['tipo_material']] ?? '♻️' ?></span>
            <span><?= htmlspecialchars($m['tipo_material']) ?></span>
            <strong><?= number_format((float)$m['kg'], 2, '.', ',') ?> kg</strong>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="recep-grid">
    <div class="card recep-card">
        <h3>Registrar ingreso</h3>
        <p class="recep-help">
            Selecciona el material, el peso y la procedencia. Los campos se validan
            antes de guardar y también en el servidor.
        </p>

        <form id="recepcionForm"
              action="router.php?page=recepcion_residuos"
              method="POST"
              data-rol="<?= $rol ?>"
              data-fixed-barrio="<?= (int)($alcance['barrio_id'] ?? 0) ?>"
              data-fixed-calle="<?= (int)($alcance['calle_id'] ?? 0) ?>"
              onsubmit="return <?= $puedeRegistrar ? 'true' : 'false' ?>;">
            <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
            <input type="hidden" name="action" value="registrar_recepcion_residuo">
            <input type="hidden" name="page" value="recepcion_residuos">

            <?php if ($rol === 5): ?>
                <input type="hidden" name="barrio_id" value="<?= (int)($alcance['barrio_id'] ?? 0) ?>">
                <div class="recep-zone">
                    <strong>Zona asignada:</strong> <?= htmlspecialchars($barrioFijoNombre ?: 'Sin barrio asignado') ?>
                </div>
            <?php elseif ($rol === 6): ?>
                <input type="hidden" name="barrio_id" value="<?= (int)($alcance['barrio_id'] ?? 0) ?>">
                <input type="hidden" name="calle_id" value="<?= (int)($alcance['calle_id'] ?? 0) ?>">
                <div class="recep-zone">
                    <strong>Zona asignada:</strong>
                    <?= htmlspecialchars($barrioFijoNombre ?: 'Sin barrio') ?>
                    <?php if ($calleFijaNombre !== ''): ?> · <?= htmlspecialchars($calleFijaNombre) ?><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="recep-form-grid">
                <div class="recep-field recep-full">
                    <label for="tipo_material">Tipo de material</label>
                    <select id="tipo_material" name="tipo_material" required>
                        <option value="">Seleccione un material...</option>
                        <?php foreach (RECEPCION_TIPOS_MATERIAL as $material): ?>
                            <option value="<?= htmlspecialchars($material) ?>">
                                <?= ($materialIconos[$material] ?? '♻️') . ' ' . htmlspecialchars($material) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="recep-field">
                    <label for="peso_kg">Peso (kg)</label>
                    <input id="peso_kg" type="number" name="peso_kg" step="0.01" min="0.01"
                           max="999999.99" inputmode="decimal" placeholder="Ej. 12.50" required>
                </div>

                <div class="recep-field">
                    <label for="fecha_recepcion">Fecha de recepción</label>
                    <input id="fecha_recepcion" type="date" name="fecha_recepcion"
                           min="2000-01-01" max="<?= $hoy ?>" value="<?= $hoy ?>" required>
                </div>

                <div class="recep-field recep-full">
                    <label for="origen_tipo">Origen</label>
                    <select id="origen_tipo" name="origen_tipo" required>
                        <?php foreach (RECEPCION_ORIGENES as $origen): ?>
                            <option value="<?= htmlspecialchars($origen) ?>" <?= $origen === 'Barrio' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($origen) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($rol !== 5 && $rol !== 6): ?>
                    <div class="recep-field recep-full" id="recepcionBarrioWrap">
                        <label for="recepcionBarrio">Barrio de origen</label>
                        <select id="recepcionBarrio" name="barrio_id">
                            <option value="">Seleccione un barrio...</option>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($rol !== 6): ?>
                    <div class="recep-field recep-full" id="recepcionCalleWrap">
                        <label for="recepcionCalle">Calle de origen</label>
                        <select id="recepcionCalle" name="calle_id">
                            <option value="">Seleccione una calle...</option>
                            <?php foreach ($calles as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" data-barrio="<?= (int)$c['barrio_id'] ?>">
                                    <?= htmlspecialchars($c['nombre']) ?>
                                    <?php if (!empty($c['barrio_nombre'])): ?> · <?= htmlspecialchars($c['barrio_nombre']) ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="recep-field recep-full" id="recepcionRefWrap">
                    <label for="referencia_origen">Referencia / procedencia</label>
                    <input id="referencia_origen" type="text" name="referencia_origen"
                           maxlength="255" minlength="3"
                           placeholder="Vivienda, persona, empresa o lugar de procedencia">
                </div>

                <div class="recep-field recep-full">
                    <label for="observaciones_recepcion">Observaciones</label>
                    <textarea id="observaciones_recepcion" name="observaciones_recepcion"
                              maxlength="1000" placeholder="Detalles del material, estado, transporte, etc. (opcional)"></textarea>
                </div>
            </div>

            <div class="recep-actions">
                <button type="submit" <?= $puedeRegistrar ? '' : 'disabled' ?>>♻️ Registrar recepción</button>
                <?php if ($sinZona): ?>
                    <span style="font-size:11px;color:#8A5A00;">Solicita la asignación de tu zona para continuar.</span>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div>
        <form method="GET" action="router.php" class="card recep-filters">
            <input type="hidden" name="page" value="recepcion_residuos">
            <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">

            <div class="recep-field">
                <label for="f_q">Buscar</label>
                <input id="f_q" type="search" name="q" value="<?= htmlspecialchars($filtros['q']) ?>" placeholder="Material, persona, lugar...">
            </div>

            <div class="recep-field">
                <label for="f_material">Material</label>
                <select id="f_material" name="tipo_material">
                    <option value="">Todos</option>
                    <?php foreach (RECEPCION_TIPOS_MATERIAL as $material): ?>
                        <option value="<?= htmlspecialchars($material) ?>" <?= $filtros['tipo_material'] === $material ? 'selected' : '' ?>>
                            <?= htmlspecialchars($material) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="recep-field">
                <label for="f_origen">Origen</label>
                <select id="f_origen" name="origen_tipo">
                    <option value="">Todos</option>
                    <?php foreach (RECEPCION_ORIGENES as $origen): ?>
                        <option value="<?= htmlspecialchars($origen) ?>" <?= $filtros['origen_tipo'] === $origen ? 'selected' : '' ?>>
                            <?= htmlspecialchars($origen) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="recep-field">
                <label for="f_estado">Estado</label>
                <select id="f_estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (RECEPCION_ESTADOS as $estado): ?>
                        <option value="<?= htmlspecialchars($estado) ?>" <?= $filtros['estado'] === $estado ? 'selected' : '' ?>>
                            <?= htmlspecialchars($estado) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="recep-field">
                <label for="f_desde">Desde</label>
                <input id="f_desde" type="date" name="desde" value="<?= htmlspecialchars($filtros['desde']) ?>">
            </div>

            <div class="recep-field">
                <label for="f_hasta">Hasta</label>
                <input id="f_hasta" type="date" name="hasta" value="<?= htmlspecialchars($filtros['hasta']) ?>">
            </div>

            <?php if ($rol !== 5 && $rol !== 6): ?>
                <div class="recep-field">
                    <label for="f_barrio">Barrio</label>
                    <select id="f_barrio" name="barrio_id">
                        <option value="">Todos</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= (int)$filtros['barrio_id'] === (int)$b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($rol !== 6): ?>
                <div class="recep-field">
                    <label for="f_calle">Calle</label>
                    <select id="f_calle" name="calle_id">
                        <option value="">Todas</option>
                        <?php foreach ($calles as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-barrio="<?= (int)$c['barrio_id'] ?>" <?= (int)$filtros['calle_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?><?php if (!empty($c['barrio_nombre'])): ?> · <?= htmlspecialchars($c['barrio_nombre']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="recep-filter-actions">
                <button type="submit">Filtrar</button>
                <a href="router.php?page=recepcion_residuos">Limpiar</a>
            </div>
        </form>

        <div class="card recep-table-card">
            <div class="recep-table-head">
                <h3>Historial de recepciones</h3>
                <span><?= count($recepciones) ?> registro(s) mostrado(s) · máximo 200</span>
            </div>

            <?php if (empty($recepciones)): ?>
                <div class="recep-empty">
                    <div class="big">♻️</div>
                    <h3>Sin recepciones registradas</h3>
                    <p>Cuando se registre material, aparecerá aquí con su peso y procedencia.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="recep-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Material</th>
                                <th>Peso</th>
                                <th>Procedencia</th>
                                <th>Registró</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recepciones as $r): ?>
                                <?php
                                $icono = $materialIconos[$r['tipo_material']] ?? '♻️';
                                $origenTexto = $r['origen_tipo'];
                                $lugar = trim(($r['barrio_nombre'] ?? '') . ' / ' . ($r['calle_nombre'] ?? ''), ' /');
                                if ($r['origen_tipo'] === 'Externo') {
                                    $origenTexto = 'Externo';
                                } elseif ($lugar !== '') {
                                    $origenTexto .= ': ' . $lugar;
                                }
                                $puedeAnular = $recepcionModel->puedeAnular($user, $r);
                                ?>
                                <tr>
                                    <td style="white-space:nowrap;">
                                        <?= date('d/m/Y', strtotime($r['fecha_recepcion'])) ?>
                                        <div style="color:#9CA3AF;font-size:10px;"><?= date('H:i', strtotime($r['fecha_creacion'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="recep-material"><?= $icono ?> <?= htmlspecialchars($r['tipo_material']) ?></span>
                                    </td>
                                    <td style="white-space:nowrap;font-weight:700;">
                                        <?= number_format((float)$r['peso_kg'], 2, '.', ',') ?> kg
                                    </td>
                                    <td>
                                        <div class="recep-origin">
                                            <?= htmlspecialchars($origenTexto) ?>
                                            <?php if (!empty($r['referencia_origen'])): ?>
                                                <div><small>Ref.: <?= htmlspecialchars($r['referencia_origen']) ?></small></div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['observaciones_recepcion'])): ?>
                                                <div><small><?= htmlspecialchars($r['observaciones_recepcion']) ?></small></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="font-size:11px;color:#4B5563;">
                                        <?= htmlspecialchars(trim(($r['registrado_nombre'] ?? '') . ' ' . ($r['registrado_apellido'] ?? ''))) ?>
                                    </td>
                                    <td>
                                        <span class="recep-badge <?= $r['estado'] === 'Registrada' ? 'recep-badge-registrada' : 'recep-badge-anulada' ?>">
                                            <?= htmlspecialchars($r['estado']) ?>
                                        </span>
                                        <?php if ($r['estado'] === 'Anulada' && !empty($r['motivo_anulacion'])): ?>
                                            <div style="color:#9CA3AF;font-size:10px;margin-top:4px;">
                                                <?= htmlspecialchars($r['motivo_anulacion']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($puedeAnular): ?>
                                            <form method="POST" action="router.php?page=recepcion_residuos"
                                                  class="recep-anular"
                                                  onsubmit="return confirm('¿Anular esta recepción? Esta acción quedará registrada.');">
                                                <input type="hidden" name="sid" value="<?= htmlspecialchars($_SESSION['active_sid'] ?? 'main') ?>">
                                                <input type="hidden" name="action" value="anular_recepcion_residuo">
                                                <input type="hidden" name="page" value="recepcion_residuos">
                                                <input type="hidden" name="recepcion_id" value="<?= (int)$r['id'] ?>">
                                                <input type="text" name="motivo_anulacion" maxlength="500" minlength="3"
                                                       placeholder="Motivo" required>
                                                <button type="submit">Anular</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#9CA3AF;font-size:11px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('recepcionForm');
    if (!form) return;

    var rol = parseInt(form.getAttribute('data-rol') || '0', 10);
    var origen = document.getElementById('origen_tipo');
    var barrioWrap = document.getElementById('recepcionBarrioWrap');
    var calleWrap = document.getElementById('recepcionCalleWrap');
    var refWrap = document.getElementById('recepcionRefWrap');
    var barrio = document.getElementById('recepcionBarrio');
    var calle = document.getElementById('recepcionCalle');
    var referencia = document.getElementById('referencia_origen');

    function barrioSeleccionado() {
        if (barrio) return barrio.value;
        return String(form.getAttribute('data-fixed-barrio') || '');
    }

    function filtrarCalles() {
        if (!calle) return;
        var barrioId = barrioSeleccionado();

        Array.prototype.forEach.call(calle.options, function (opt) {
            if (!opt.value) return;
            var coincide = !barrioId || String(opt.getAttribute('data-barrio') || '') === String(barrioId);
            opt.hidden = !coincide;
            opt.disabled = !coincide;
        });

        if (calle.value) {
            var elegida = calle.options[calle.selectedIndex];
            if (elegida && elegida.disabled) calle.value = '';
        }
    }

    function sincronizarOrigen() {
        if (!origen) return;
        var externo = origen.value === 'Externo';

        if (barrioWrap) barrioWrap.style.display = externo ? 'none' : '';
        if (calleWrap) calleWrap.style.display = externo ? 'none' : '';
        if (refWrap) refWrap.style.display = '';

        if (referencia) {
            referencia.required = externo;
            if (!externo) referencia.setCustomValidity('');
        }

        if (externo && calle && rol !== 6) {
            calle.value = '';
        }

        filtrarCalles();
    }

    if (origen) origen.addEventListener('change', sincronizarOrigen);
    if (barrio) barrio.addEventListener('change', filtrarCalles);

    sincronizarOrigen();
})();

(function () {
    var barrio = document.getElementById('f_barrio');
    var calle = document.getElementById('f_calle');
    if (!barrio || !calle) return;

    function filtrar() {
        var barrioId = barrio.value;
        Array.prototype.forEach.call(calle.options, function (opt) {
            if (!opt.value) return;
            var coincide = !barrioId || String(opt.getAttribute('data-barrio') || '') === String(barrioId);
            opt.hidden = !coincide;
            opt.disabled = !coincide;
        });
        if (calle.value) {
            var elegida = calle.options[calle.selectedIndex];
            if (elegida && elegida.disabled) calle.value = '';
        }
    }

    barrio.addEventListener('change', filtrar);
    filtrar();
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
