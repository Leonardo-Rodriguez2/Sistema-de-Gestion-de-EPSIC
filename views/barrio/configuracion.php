<?php
// views/barrio/configuracion.php
$user = check_dashboard_access([5]);

// 1. Obtener el barrio del encargado
$barrioStmt = $pdo->prepare("SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ?");
$barrioStmt->execute([$user['id']]);
$barrio_id = $barrioStmt->fetchColumn();

// 2. Obtener la configuración actual
$configStmt = $pdo->prepare("SELECT * FROM configuraciones_barrio WHERE barrio_id = ?");
$configStmt->execute([$barrio_id]);
$config = $configStmt->fetch(PDO::FETCH_ASSOC);

// Si no existe, usar valores por defecto (aunque la migración debería haberlos creado)
if (!$config) {
    $config = ['cuota_mensual' => 10.00, 'multa_renovacion' => 5.00];
}

// Precio del bolívar (bolívares por 1 dólar). Todas las tarifas se capturan
// en dólares y se muestran también en bolívares usando esta tasa.
$precioBolivar = (float)($config['precio_bolivar'] ?? 0) > 0
    ? (float)$config['precio_bolivar']
    : TASA_BOLIVAR_PREDETERMINADA;

// La tasa se descarga automáticamente de una API pública y se guarda en la BD.
$tasaAutomatica = (int)($config['tasa_automatica'] ?? 1) === 1;
$ultimaTasa     = trim((string)($config['precio_bolivar_actualizado_en'] ?? ''));
$fuenteTasa     = trim((string)($config['precio_bolivar_fuente'] ?? ''));
$estadoTasa = $tasaAutomatica
    ? '🔄 Actualización automática activa'
        . ($fuenteTasa !== '' ? ' · Fuente: ' . htmlspecialchars($fuenteTasa, ENT_QUOTES, 'UTF-8') : ' · Sin descarga exitosa todavía')
        . ($ultimaTasa !== '' ? ' · Última actualización: ' . date('d/m/Y H:i', strtotime($ultimaTasa)) : '')
    : '✋ Modo manual: el valor no se actualizará solo.';

// Si la consulta automática falló, avisar al encargado (sin bloquear la página).
$resultadoTasa = \tasa_bolivar_actualizar($pdo);
if ($tasaAutomatica && empty($resultadoTasa['ok'])) {
    $mensaje_error = trim(($mensaje_error ?? '') . ' ' . ($resultadoTasa['mensaje'] ?? ''));
}

// Fecha límite de pago exigida a los encargados de calle. Si aún no se ha
// configurado, se propone el último día del mes en curso.
$fecha_limite_pago = trim((string)($config['fecha_limite_pago'] ?? ''));
if ($fecha_limite_pago === '') {
    $fecha_limite_pago = date('Y-m-t');
}

// Tarifa mensual por tipo de predio. Si un barrio nunca las ha guardado, se
// proponen con la cuota general de vivienda para que puedan editarse.
$valorTarifa = function ($campo) use ($config) {
    $valor = $config[$campo] ?? ($campo === 'precio_especial' ? 20.00 : ($config['cuota_mensual'] ?? 10.00));
    return number_format((float)($valor ?: ($config['cuota_mensual'] ?? 10.00)), 2, '.', '');
};
$tarifasPredio = [
    'precio_comercio'     => ['🏬 Comercio', 'Pago mensual fijo por establecimiento comercial.'],
    'precio_tienda'       => ['🛍️ Tienda', 'Pago mensual fijo por tienda.'],
    'precio_empresa'      => ['🏢 Empresa', 'Pago mensual fijo por empresa.'],
    'precio_urbanizacion' => ['🏘️ Urbanización (por casa)', 'Se multiplica por la cantidad de casas declarada. Ej.: 4 casas × US$ 3 = US$ 12 en un solo pago.'],
    'precio_otro'         => ['📦 Otro predio', 'Pago mensual fijo para cualquier otro tipo de predio.'],
    'precio_especial'     => ['🚛 Recojo especial', 'Pago único por evento, desmonte o retiro de electrónicos.'],
];

$title = "Configuración de Pagos - EcoReciclaje";
$header_title = "Configuración del Barrio";
$header_subtitle = "Define los montos en dólares, el precio del bolívar, las multas y la fecha límite de pago para tus encargados de calle.";

ob_start();
?>
    <?php render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null); ?>

    <style>
        .config-financiera-card { width: 100%; max-width: none; }
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            width: 100%;
        }
        .config-grid .form-group { width: 100%; margin-bottom: 0; }
        .config-financiera-card input[type="number"],
        .config-financiera-card input[type="date"] {
            width: 100%;
            box-sizing: border-box;
        }
        .config-equivalencia { display: block; margin-top: 6px; font-size: 11px; color: #3E5C76; font-weight: 600; }
        @media(max-width:768px) {
            .config-grid { grid-template-columns: 1fr; }
        }
    </style>
    <div class="config-flex" style="width: 100%; box-sizing: border-box; padding: 24px;">

    <div class="card config-financiera-card" style="width: 100%;">
        <form action="router.php?page=configuracion" method="POST" style="margin: 0 0 10px; text-align: right;">
            <input type="hidden" name="sid">
            <input type="hidden" name="action" value="actualizar_tasa_bolivar">
            <button type="submit" class="btn-primary" style="background:#176b87; color:white; padding:9px 14px; border-radius:8px; font-weight:700; border:none; cursor:pointer;">
                🔄 Actualizar tasa ahora
            </button>
        </form>

        <form action="router.php?page=configuracion" method="POST" style="display: flex; flex-direction: column; gap: 24px;">
            <input type="hidden" name="sid">
            <input type="hidden" name="action" value="actualizar_configuracion_barrio">

            <div style="background:#EEF2F6; border-left:4px solid #4C6E91; padding:14px 16px; border-radius:6px;">
                <label for="precio_bolivar" style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">💱 Precio del dólar en bolívares (Bs. por 1 US$)</label>
                <input type="number" step="0.01" min="0.01" id="precio_bolivar" name="precio_bolivar" value="<?= number_format($precioBolivar, 2, '.', '') ?>" class="form-control" required style="padding: 12px; border: 1px solid #A9C0D3; border-radius: 8px; max-width: 320px;">
                <input type="hidden" name="tasa_automatica" value="0">
                <label style="display:flex; align-items:center; gap:8px; margin-top:10px; font-size:12px; color:#374151; font-weight:600;">
                    <input type="checkbox" name="tasa_automatica" value="1" <?= $tasaAutomatica ? 'checked' : '' ?>>
                    Actualizar automáticamente desde internet
                </label>
                <small style="display: block; margin-top: 6px; font-size: 11px; color: #6B7280;">El sistema consulta el precio vigente del dólar en bolívares y lo aplica a todos los cobros, pagos y totales. Desmarca la casilla si prefieres fijar un valor manualmente.</small>
                <span class="config-equivalencia" id="tasaEquivalencia">Equivalencia actual: US$ 1.00 = <?= bs(1, $precioBolivar) ?></span>
                <span class="config-equivalencia" style="color:#6B7280;"><?= $estadoTasa ?></span>
            </div>

            <div class="config-grid">
                <div class="form-group">
                    <label style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">🏠 Cuota Mensual de Vivienda (US$)</label>
                    <input type="number" step="0.01" min="0.01" name="cuota_mensual" value="<?= number_format($config['cuota_mensual'], 2, '.', '') ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                    <span class="config-equivalencia" data-equivalencia="cuota_mensual">≈ <?= bs($config['cuota_mensual'], $precioBolivar) ?> al mes</span>
                </div>

                <div class="form-group">
                    <label style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">⚠️ Multa por Renovación de Servicio (US$)</label>
                    <input type="number" step="0.01" min="0" name="multa_renovacion" value="<?= number_format($config['multa_renovacion'], 2, '.', '') ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                    <span class="config-equivalencia" data-equivalencia="multa_renovacion">≈ <?= bs($config['multa_renovacion'], $precioBolivar) ?></span>
                </div>

                <div class="form-group">
                    <label for="fecha_limite_pago" style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">📅 Fecha límite de pago para encargados de calle</label>
                    <input type="date" id="fecha_limite_pago" name="fecha_limite_pago" value="<?= htmlspecialchars($fecha_limite_pago, ENT_QUOTES, 'UTF-8') ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                    <small style="display: block; margin-top: 6px; font-size: 11px; color: #6B7280;">Fecha en la que deben realizar el pago los encargados de calle de tu barrio. Puedes modificarla cuando lo necesites.</small>
                </div>
            </div>

            <div style="border-top: 1px solid #E5E7EB; padding-top: 18px;">
                <h4 style="margin: 0 0 6px 0; font-size: 14px; color: #111827;">Tarifas por tipo de predio (US$)</h4>
                <p style="margin: 0 0 16px 0; font-size: 11px; color: #6B7280;">Define cuánto paga cada tipo de predio al mes en dólares. Estos valores se usan automáticamente al generar los cobros y se muestran también en bolívares.</p>
                <div class="config-grid">
                    <?php foreach ($tarifasPredio as $campo => [$etiqueta, $ayuda]): ?>
                        <div class="form-group">
                            <label for="<?= $campo ?>" style="font-weight: 700; display: block; margin-bottom: 6px; color: #374151;"><?= $etiqueta ?> (US$)</label>
                            <input type="number" step="0.01" min="0.01" id="<?= $campo ?>" name="<?= $campo ?>" value="<?= $valorTarifa($campo) ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                            <small style="display: block; margin-top: 5px; font-size: 11px; color: #6B7280;"><?= $ayuda ?></small>
                            <span class="config-equivalencia" data-equivalencia="<?= $campo ?>">≈ <?= bs((float)$valorTarifa($campo), $precioBolivar) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="background: #111827; color: white; padding: 14px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; margin-top: 2px;">
                💾 Guardar Configuración
            </button>
        </form>
    </div>

    <script>
    (function() {
        const tasaInput = document.getElementById('precio_bolivar');
        const tasaTexto = document.getElementById('tasaEquivalencia');
        if (!tasaInput || !tasaTexto) return;

        function actualizar() {
            const tasa = Number(tasaInput.value) || 0;
            tasaTexto.textContent = 'Equivalencia actual: US$ 1.00 = ' + fmtBS(1 * tasa);
            document.querySelectorAll('[data-equivalencia]').forEach(function(span) {
                const campo = document.querySelector('[name="' + span.dataset.equivalencia + '"]');
                if (!campo) return;
                span.textContent = '≈ ' + fmtBS((Number(campo.value) || 0) * tasa);
            });
        }

        tasaInput.addEventListener('input', actualizar);
        document.querySelectorAll('[data-equivalencia]').forEach(function(span) {
            const campo = document.querySelector('[name="' + span.dataset.equivalencia + '"]');
            if (campo) campo.addEventListener('input', actualizar);
        });
    })();
    </script>

    </div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
