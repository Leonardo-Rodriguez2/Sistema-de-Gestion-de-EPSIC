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

// Fecha límite de pago exigida a los encargados de calle. Si aún no se ha
// configurado, se propone el último día del mes en curso.
$fecha_limite_pago = trim((string)($config['fecha_limite_pago'] ?? ''));
if ($fecha_limite_pago === '') {
    $fecha_limite_pago = date('Y-m-t');
}

// Tarifa mensual por tipo de predio. Si un barrio nunca las ha guardado, se
// proponen con la cuota general de vivienda para que puedan editarse.
$valorTarifa = function ($campo) use ($config) {
    $valor = $config[$campo] ?? $config['cuota_mensual'] ?? 10.00;
    return number_format((float)($valor ?: ($config['cuota_mensual'] ?? 10.00)), 2, '.', '');
};
$tarifasPredio = [
    'precio_comercio'     => ['🏬 Comercio', 'Pago mensual fijo por establecimiento comercial.'],
    'precio_tienda'       => ['🛍️ Tienda', 'Pago mensual fijo por tienda.'],
    'precio_empresa'      => ['🏢 Empresa', 'Pago mensual fijo por empresa.'],
    'precio_urbanizacion' => ['🏘️ Urbanización (por casa)', 'Se multiplica por la cantidad de casas declarada. Ej.: 4 casas × Bs. 3 = Bs. 12 en un solo pago.'],
    'precio_otro'         => ['📦 Otro predio', 'Pago mensual fijo para cualquier otro tipo de predio.'],
];

$title = "Configuración de Pagos - EcoCusco";
$header_title = "Configuración del Barrio";
$header_subtitle = "Define los montos de cobro, las multas y la fecha límite de pago para tus encargados de calle.";

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
        @media(max-width:768px) {
            .config-grid { grid-template-columns: 1fr; }
        }
    </style>
    <div class="config-flex" style="width: 100%; box-sizing: border-box; padding: 24px;">

    <div class="card config-financiera-card" style="width: 100%;">
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="background: #E9EFF4; color: #3E5C76; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">
                <i class="fas fa-money-check-alt"></i>
            </div>
            <h3 style="margin: 0;">Parámetros Financieros</h3>
        </div>

        <form action="router.php?page=configuracion" method="POST" style="display: flex; flex-direction: column; gap: 24px;">
            <input type="hidden" name="sid">
            <input type="hidden" name="action" value="actualizar_configuracion_barrio">

            <div class="config-grid">
                <div class="form-group">
                    <label style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">🏠 Cuota Mensual de Vivienda (Bs.)</label>
                    <input type="number" step="0.01" name="cuota_mensual" value="<?= number_format($config['cuota_mensual'], 2, '.', '') ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                </div>

                <div class="form-group">
                    <label style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">⚠️ Multa por Renovación de Servicio (Bs.)</label>
                    <input type="number" step="0.01" name="multa_renovacion" value="<?= number_format($config['multa_renovacion'], 2, '.', '') ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                </div>

                <div class="form-group">
                    <label for="fecha_limite_pago" style="font-weight: 700; display: block; margin-bottom: 8px; color: #374151;">📅 Fecha límite de pago para encargados de calle</label>
                    <input type="date" id="fecha_limite_pago" name="fecha_limite_pago" value="<?= htmlspecialchars($fecha_limite_pago, ENT_QUOTES, 'UTF-8') ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                    <small style="display: block; margin-top: 6px; font-size: 11px; color: #6B7280;">Fecha en la que deben realizar el pago los encargados de calle de tu barrio. Puedes modificarla cuando lo necesites.</small>
                </div>
            </div>

            <div style="border-top: 1px solid #E5E7EB; padding-top: 18px;">
                <h4 style="margin: 0 0 6px 0; font-size: 14px; color: #111827;">Tarifas por tipo de predio</h4>
                <p style="margin: 0 0 16px 0; font-size: 11px; color: #6B7280;">Define cuánto paga cada tipo de predio al mes. Estos valores se usan automáticamente al generar los cobros.</p>
                <div class="config-grid">
                    <?php foreach ($tarifasPredio as $campo => [$etiqueta, $ayuda]): ?>
                        <div class="form-group">
                            <label for="<?= $campo ?>" style="font-weight: 700; display: block; margin-bottom: 6px; color: #374151;"><?= $etiqueta ?> (Bs.)</label>
                            <input type="number" step="0.01" min="0.01" id="<?= $campo ?>" name="<?= $campo ?>" value="<?= $valorTarifa($campo) ?>" class="form-control" required style="padding: 12px; border: 1px solid #D1D5DB; border-radius: 8px;">
                            <small style="display: block; margin-top: 5px; font-size: 11px; color: #6B7280;"><?= $ayuda ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="background: #111827; color: white; padding: 14px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; margin-top: 2px;">
                💾 Guardar Configuración
            </button>
        </form>
    </div>


    </div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
?>
