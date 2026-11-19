<?php
// views/public/cotizacion.php — Cotización pública con estimado en US$ y Bs.
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

$pdo   = (new \app\models\mainModel())->conectar();
$config = tarifas_barrio($pdo, barrio_id_actual($pdo));
$tasa   = (float)($config['precio_bolivar'] ?? 0) > 0
        ? (float)$config['precio_bolivar']
        : tasa_bolivar($pdo);

/** Barrios registrados y tarifa mensual por vivienda de cada uno. */
$stmtBarrios = $pdo->query("SELECT b.id, b.nombre, c.precio_urbanizacion, c.cuota_mensual
                            FROM barrios b
                            LEFT JOIN configuraciones_barrio c ON c.barrio_id = b.id
                            WHERE (b.ciudad IS NULL OR b.ciudad = '' OR LOWER(b.ciudad) NOT LIKE '%cusco%')
                            ORDER BY b.nombre");
$barrios   = [];
$barriosJs = [];
foreach ($stmtBarrios->fetchAll(PDO::FETCH_ASSOC) as $barrio) {
    $mensual = ($barrio['precio_urbanizacion'] !== null)
        ? (float)$barrio['precio_urbanizacion']
        : (float)($barrio['cuota_mensual'] ?? 10.00);
    $barrios[] = [
        'id'      => (int)$barrio['id'],
        'nombre'  => $barrio['nombre'],
        'mensual' => $mensual,
    ];
    $barriosJs[(int)$barrio['id']] = $mensual;
}

$barrioElegidoId = (int)($_GET['barrio'] ?? 0);
if (!array_key_exists($barrioElegidoId, $barriosJs)) {
    $barrioElegidoId = (int)($config['barrio_id'] ?? 0);
}
if (!array_key_exists($barrioElegidoId, $barriosJs) && !empty($barriosJs)) {
    $barrioElegidoId = (int)array_key_first($barriosJs);
}
$mensualElegido = $barriosJs[$barrioElegidoId]
    ?? (float)($config['precio_urbanizacion'] ?? $config['cuota_mensual'] ?? 10.00);

/** Planes y tarifas referenciales tomadas de la configuración del barrio. */
$planes = [
    'urbanizacion' => [
        'nombre'      => 'Plan Urbanización',
        'icono'       => 'fa-house-chimney-window',
        'descripcion' => 'Comités, urbanizaciones y barrios completos.',
        'base'        => $mensualElegido,
        'por'         => 'por casa / mes',
        'unidad1'     => 'casa',
        'unidadN'     => 'casas',
        'detalle'     => 'Se aplica la tarifa mensual del barrio por cada casa indicada.',
    ],
    'personal' => [
        'nombre'      => 'Plan Personal',
        'icono'       => 'fa-house',
        'descripcion' => 'Una sola vivienda con servicio mensual.',
        'base'        => $mensualElegido,
        'por'         => 'por vivienda / mes',
        'unidad1'     => 'vivienda',
        'unidadN'     => 'viviendas',
        'detalle'     => 'Una sola vivienda: no requiere cantidad de casas. Se aplica la tarifa mensual del barrio.',
    ],
    'comercial' => [
        'nombre'      => 'Plan Comercial',
        'icono'       => 'fa-store',
        'descripcion' => 'Negocios, restaurantes y plazas comerciales.',
        'base'        => (float)($config['precio_comercio'] ?? $config['cuota_mensual'] ?? 10.00),
        'por'         => 'por local / mes',
        'unidad1'     => 'local',
        'unidadN'     => 'locales',
        'detalle'     => 'Recolección dedicada y mayor capacidad de ruta.',
    ],
    'especial' => [
        'nombre'      => 'Recojo Especial',
        'icono'       => 'fa-truck-ramp-box',
        'descripcion' => 'Eventos, desmonte o electrónicos.',
        'base'        => (float)($config['precio_especial'] ?? 20.00),
        'por'         => 'pago único',
        'unidad1'     => 'servicio',
        'unidadN'     => 'servicios',
        'detalle'     => 'Precio base por servicio. Llámanos al 04160791814 o escribe a epsiccaciqueticoporo2023@gmail.com para confirmar volumen y material.',
    ],
];

$planElegido = $_GET['plan'] ?? 'urbanizacion';
if (!array_key_exists($planElegido, $planes)) {
    $planElegido = 'urbanizacion';
}

$planesJs = [];
foreach ($planes as $clave => $plan) {
    $planesJs[$clave] = [
        'nombre'    => $plan['nombre'],
        'base'      => $plan['base'],
        'por'       => $plan['por'],
        'unidad1'   => $plan['unidad1'],
        'unidadN'   => $plan['unidadN'],
        'conBarrio' => in_array($clave, ['personal', 'urbanizacion'], true),
    ];
}

$title = "Cotización | EcoReciclaje";
ob_start();
?>

<section class="cot-hero">
    <div class="container cot-hero-inner">
        <span class="cot-pill"><i class="fas fa-calculator me-2"></i> Cotización gratuita y sin compromiso</span>
        <h1>Calcula el costo de tu recolección</h1>
        <p>Elige un plan, indica cuántas casas, viviendas o locales necesitas y mira el estimado en dólares y bolívares al instante.</p>
    </div>
</section>

<div class="container cotizacion-page">
    <div class="cot-tasa">
        <div class="cot-tasa-icon"><i class="fas fa-arrow-right-arrow-left"></i></div>
        <div>
            <strong>Tasa vigente: 1 US$ = Bs. <?= number_format($tasa, 2, '.', ',') ?></strong>
            <span>Actualizada automáticamente · todos los importes se muestran en ambas monedas</span>
        </div>
    </div>

    <h2 class="cot-section-title">1. Elige el tipo de servicio</h2>
    <p class="cot-section-sub">Tarifas referenciales configuradas para tu barrio.</p>

    <div class="cot-planes">
        <?php foreach ($planes as $clave => $plan): ?>
            <button type="button"
                    class="cot-plan <?= $clave === $planElegido ? 'seleccionado' : '' ?>"
                    data-plan="<?= htmlspecialchars($clave) ?>"
                    onclick="cotElegirPlan('<?= htmlspecialchars($clave) ?>')">
                <span class="cot-plan-icon"><i class="fas <?= htmlspecialchars($plan['icono']) ?>"></i></span>
                <strong><?= htmlspecialchars($plan['nombre']) ?></strong>
                <span class="cot-plan-desc"><?= htmlspecialchars($plan['descripcion']) ?></span>
                <span class="cot-plan-precio">
                    <?= $plan['base'] === null ? 'A calcular' : monto($plan['base'], $tasa) ?>
                    <small><?= htmlspecialchars($plan['por']) ?></small>
                </span>
                <?php if ($clave === 'especial'): ?>
                    <span class="cot-plan-contacto">
                        <a href="tel:04160791814"><i class="fas fa-phone me-1"></i> 04160791814</a>
                        <a href="mailto:epsiccaciqueticoporo2023@gmail.com"><i class="fas fa-envelope me-1"></i> epsiccaciqueticoporo2023@gmail.com</a>
                    </span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="cot-calculadora">
        <div class="cot-calc-form">
            <h2 class="cot-section-title mb-3">2. Ajusta tu estimado</h2>

            <label for="cotPlan">Tipo de servicio</label>
            <select id="cotPlan" name="plan" onchange="cotCalcular()">
                <?php foreach ($planes as $clave => $plan): ?>
                    <option value="<?= htmlspecialchars($clave) ?>" <?= $clave === $planElegido ? 'selected' : '' ?>>
                        <?= htmlspecialchars($plan['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="cotBarrioWrap" style="display:none;">
                <label for="cotBarrio" class="mt-3">Barrio / Urbanización</label>
                <select id="cotBarrio" onchange="cotCalcular()">
                    <?php foreach ($barrios as $barrio): ?>
                        <option value="<?= (int)$barrio['id'] ?>" <?= (int)$barrio['id'] === $barrioElegidoId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($barrio['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="cot-nota mt-2" id="cotBarrioTarifa"></p>
            </div>

            <div id="cotCantidadWrap">
                <label for="cotCantidad" class="mt-3" id="cotCantidadLabel">Cantidad de casas</label>
                <input type="number" id="cotCantidad" name="cantidad" min="1" max="10000" step="1"
                       value="1" oninput="cotCalcular()">
            </div>

            <p class="cot-nota mt-3">
                <i class="fas fa-circle-info me-1"></i>
                <span id="cotDetallePlan"><?= htmlspecialchars($planes[$planElegido]['detalle']) ?></span>
            </p>
        </div>

        <div class="cot-resultado">
            <span class="cot-resultado-label" id="cotResultadoLabel">Estimado mensual referencial</span>
            <div class="cot-resultado-usd" id="cotResultadoUSD">US$ 0,00</div>
            <div class="cot-resultado-bs" id="cotResultadoBS">Bs. 0,00</div>
            <div class="cot-resultado-linea"></div>
            <p id="cotDesglose">—</p>
            <span class="cot-resultado-aviso">El monto final se confirma tras la visita técnica.</span>
        </div>
    </div>

    <div class="cot-contacto">
        <div>
            <i class="fas fa-phone"></i>
            <span>Habla con un asesor</span>
            <strong><a href="tel:04160791814">04160791814</a></strong>
        </div>
        <div>
            <i class="fas fa-envelope"></i>
            <span>Escríbenos</span>
            <strong><a href="mailto:epsiccaciqueticoporo2023@gmail.com">epsiccaciqueticoporo2023@gmail.com</a></strong>
        </div>
        <div>
            <i class="fas fa-map-marker-alt"></i>
            <span>Ubicación y horario</span>
            <strong>Calle 2 esquina - carrera 13, Barrio Obrero</strong>
            <small>Lunes a Viernes · 8:00 a.m. - 6:00 p.m.</small>
        </div>
    </div>

</div>

<style>
    .cot-hero {
        background: linear-gradient(120deg, rgba(31,41,55,.94), rgba(76,110,145,.92)),
                    url('/reciclaje/img/servicio.jpeg') center/cover no-repeat;
        color: #fff;
        padding: 70px 20px 80px;
        text-align: center;
    }
    .cot-hero-inner { max-width: 820px; }
    .cot-pill {
        display: inline-block;
        background: rgba(255,255,255,.15);
        border: 1px solid rgba(255,255,255,.35);
        padding: 7px 18px;
        border-radius: 999px !important;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: .3px;
        margin-bottom: 18px;
    }
    .cot-hero h1 { font-size: 42px; font-weight: 800; margin: 0 0 14px; }
    .cot-hero p { font-size: 17px; opacity: .9; margin: 0; }

    .cotizacion-page { padding-top: 34px; padding-bottom: 70px; }
    .cot-tasa {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #EEF4F8;
        border: 1px solid #D6E2EC;
        border-left: 5px solid #4C6E91;
        padding: 14px 20px;
        border-radius: 12px !important;
        margin-bottom: 34px;
    }
    .cot-tasa-icon {
        width: 42px; height: 42px;
        display: flex; align-items: center; justify-content: center;
        background: #4C6E91; color: #fff;
        border-radius: 50% !important;
        flex-shrink: 0;
    }
    .cot-tasa strong { display: block; color: #1F2937; font-size: 15px; }
    .cot-tasa span { display: block; color: #6B7280; font-size: 12.5px; margin-top: 2px; }

    .cot-section-title { color: #1F2937; font-size: 25px; font-weight: 700; margin: 0; }
    .cot-section-sub { color: #6B7280; font-size: 14px; margin: 5px 0 20px; }

    .cot-planes {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 30px;
    }
    .cot-plan {
        background: #fff;
        border: 2px solid #E5E7EB;
        border-radius: 14px !important;
        padding: 20px 16px;
        text-align: left;
        cursor: pointer;
        transition: all .18s ease;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 215px;
    }
    .cot-plan:hover { border-color: #9DB4C9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(31,41,55,.08); }
    .cot-plan.seleccionado { border-color: #4C6E91; background: #F5F9FC; box-shadow: 0 8px 22px rgba(76,110,145,.15); }
    .cot-plan-icon {
        width: 42px; height: 42px;
        display: flex; align-items: center; justify-content: center;
        background: #EEF4F8; color: #4C6E91;
        border-radius: 10px !important;
        font-size: 19px;
    }
    .cot-plan.seleccionado .cot-plan-icon { background: #4C6E91; color: #fff; }
    .cot-plan strong { color: #1F2937; font-size: 16px; }
    .cot-plan-desc { color: #6B7280; font-size: 12.5px; line-height: 1.5; flex: 1; }
    .cot-plan-precio { color: #2F4858; font-weight: 700; font-size: 15px; }
    .cot-plan-precio small { display: block; color: #9CA3AF; font-weight: 500; font-size: 11.5px; margin-top: 2px; }

    .cot-calculadora {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 22px;
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 16px !important;
        padding: 28px;
        box-shadow: 0 6px 22px rgba(31,41,55,.06);
    }
    .cot-calc-form label,
    .cot-formulario label {
        display: block;
        color: #374151;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .cot-calc-form select,
    .cot-calc-form input,
    .cot-formulario input,
    .cot-formulario textarea {
        width: 100%;
        padding: 11px 13px;
        border: 1px solid #D1D5DB;
        border-radius: 8px !important;
        font-size: 14px;
        background: #fff;
        color: #1F2937;
        outline: none;
    }
    .cot-calc-form select:focus,
    .cot-calc-form input:focus,
    .cot-formulario input:focus,
    .cot-formulario textarea:focus { border-color: #4C6E91; box-shadow: 0 0 0 3px rgba(76,110,145,.12); }
    .cot-nota { color: #6B7280; font-size: 12.5px; background: #F9FAFB; padding: 10px 12px; border-radius: 8px !important; }

    .cot-resultado {
        background: linear-gradient(145deg, #2F4858, #4C6E91);
        color: #fff;
        border-radius: 14px !important;
        padding: 26px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .cot-resultado-label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: .8; }
    .cot-resultado-usd { font-size: 40px; font-weight: 800; line-height: 1.15; margin-top: 8px; }
    .cot-resultado-bs { font-size: 21px; font-weight: 600; opacity: .95; }
    .cot-resultado-linea { height: 1px; background: rgba(255,255,255,.25); margin: 18px 0 12px; }
    .cot-resultado p { font-size: 13px; margin: 0 0 8px; opacity: .9; }
    .cot-resultado-aviso { font-size: 11.5px; opacity: .7; }

    .cot-alert { padding: 13px 16px; border-radius: 9px !important; font-size: 14px; margin-bottom: 18px; }
    .cot-alert-ok { background: #E8F4EC; color: #256A3A; border: 1px solid #BFE0C9; }
    .cot-alert-error { background: #FBEDED; color: #8A3B3B; border: 1px solid #E8C4C4; }

    .cot-formulario {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 16px !important;
        padding: 28px;
        box-shadow: 0 6px 22px rgba(31,41,55,.06);
    }
    .cot-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .cot-formulario textarea { resize: vertical; }
    .cot-submit-row { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: 22px; }
    .cot-submit-row span { color: #9CA3AF; font-size: 12.5px; }
    .cot-submit-row a { color: #4C6E91; font-weight: 600; text-decoration: none; }
    .cot-submit-row a:hover { text-decoration: underline; }
    .cot-btn {
        background: #4C6E91;
        color: #fff;
        border: none;
        padding: 13px 30px;
        border-radius: 999px !important;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: background .18s ease;
    }
    .cot-btn:hover { background: #33485C; }

    .cot-plan-contacto {
        display: flex;
        flex-direction: column;
        gap: 3px;
        margin-top: 4px;
        padding-top: 9px;
        border-top: 1px dashed #D1D5DB;
        font-size: 11.5px;
    }
    .cot-plan-contacto a { color: #4C6E91; text-decoration: none; font-weight: 600; overflow-wrap: anywhere; }
    .cot-plan-contacto a:hover { text-decoration: underline; }

    .cot-contacto {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-top: 24px;
    }
    .cot-contacto > div {
        background: #F5F9FC;
        border: 1px solid #D6E2EC;
        border-radius: 12px !important;
        padding: 16px 18px;
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .cot-contacto i { color: #4C6E91; font-size: 18px; margin-bottom: 4px; }
    .cot-contacto span { color: #9CA3AF; font-size: 11.5px; text-transform: uppercase; letter-spacing: .5px; }
    .cot-contacto strong { color: #1F2937; font-size: 14px; overflow-wrap: anywhere; }
    .cot-contacto small { color: #6B7280; font-size: 12px; }
    .cot-contacto a { color: #4C6E91; text-decoration: none; }
    .cot-contacto a:hover { text-decoration: underline; }

    @media (max-width: 960px) {
        .cot-planes { grid-template-columns: repeat(2, 1fr); }
        .cot-calculadora { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .cot-hero h1 { font-size: 30px; }
        .cot-planes, .cot-grid, .cot-contacto { grid-template-columns: 1fr; }
        .cot-calculadora, .cot-formulario { padding: 20px; }
        .cot-resultado-usd { font-size: 32px; }
    }
</style>

<script>
(function () {
    const TASA = <?= json_encode($tasa) ?>;
    const PLANES = <?= json_encode($planesJs, JSON_UNESCAPED_UNICODE) ?>;
    const BARRIO_TARIFAS = <?= json_encode($barriosJs, JSON_UNESCAPED_UNICODE) ?>;

    function fmtUSD(v) {
        return 'US$ ' + Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function fmtBS(v) {
        return 'Bs. ' + Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function tarifaDeBarrio() {
        const id = document.getElementById('cotBarrio').value;
        return Object.prototype.hasOwnProperty.call(BARRIO_TARIFAS, id)
            ? parseFloat(BARRIO_TARIFAS[id])
            : null;
    }

    window.cotCalcular = function () {
        const clave = document.getElementById('cotPlan').value;
        const plan = PLANES[clave] || null;
        const conBarrio = !!(plan && plan.conBarrio);
        const esPersonal = clave === 'personal';
        const tarifaBarrio = conBarrio ? tarifaDeBarrio() : null;

        document.getElementById('cotBarrioWrap').style.display = conBarrio ? '' : 'none';
        document.getElementById('cotCantidadWrap').style.display = esPersonal ? 'none' : '';

        let base = (plan && plan.base !== null && plan.base !== undefined) ? parseFloat(plan.base) : null;
        if (conBarrio && tarifaBarrio !== null) base = tarifaBarrio;

        const cantidad = esPersonal ? 1 : Math.max(1, parseInt(document.getElementById('cotCantidad').value, 10) || 1);
        const total = base === null ? null : Math.round(base * cantidad * 100) / 100;
        const unidad = base === null ? 'unidad' : (cantidad === 1 ? plan.unidad1 : plan.unidadN);

        document.getElementById('cotBarrioTarifa').textContent = conBarrio && tarifaBarrio !== null
            ? 'Cuota mensual del barrio: ' + fmtUSD(tarifaBarrio) + ' / ' + fmtBS(Math.round(tarifaBarrio * TASA * 100) / 100)
            : '';

        document.getElementById('cotResultadoUSD').textContent = total === null ? 'A calcular' : fmtUSD(total);
        document.getElementById('cotResultadoBS').textContent  = total === null
            ? 'Según volumen y material'
            : fmtBS(Math.round(total * TASA * 100) / 100);

        document.getElementById('cotResultadoLabel').textContent = clave === 'especial'
            ? 'Estimado por recojo (pago único)'
            : 'Estimado mensual referencial';
        document.getElementById('cotCantidadLabel').textContent = esPersonal
            ? 'Una sola vivienda'
            : 'Cantidad de ' + (plan ? plan.unidadN : 'unidades');
        document.getElementById('cotDesglose').textContent = total === null
            ? 'Un asesor evaluará el volumen y te enviará el monto exacto.'
            : fmtUSD(base) + ' × ' + cantidad + ' ' + unidad + ' (' + plan.por + ')';

        document.querySelectorAll('.cot-plan').forEach(function (el) {
            const datos = PLANES[el.dataset.plan];
            if (datos) {
                let precioBase = (datos.base !== null && datos.base !== undefined) ? parseFloat(datos.base) : null;
                if (datos.conBarrio && tarifaBarrio !== null) precioBase = tarifaBarrio;
                const precioEl = el.querySelector('.cot-plan-precio');
                if (precioEl) {
                    precioEl.innerHTML = (precioBase === null ? 'A calcular' : fmtUSD(precioBase)) + ' <small>' + datos.por + '</small>';
                }
            }
            el.classList.toggle('seleccionado', el.dataset.plan === clave);
        });
    };

    window.cotElegirPlan = function (clave) {
        document.getElementById('cotPlan').value = clave;
        window.cotCalcular();
        document.querySelector('.cot-calculadora').scrollIntoView({behavior: 'smooth', block: 'center'});
    };

    window.cotCalcular();
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/public_layout.php';
