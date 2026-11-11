<?php
/**
 * Renderiza un resumen visual sencillo sin depender de una librería externa.
 *
 * @param array $chart_data Cada elemento requiere label, value y color opcional.
 * @param string $chart_title
 */
$chart_data = $chart_data ?? [];
$chart_title = $chart_title ?? 'Resumen visual';
$chart_total = array_sum(array_map(static fn($item) => max(0, (float) ($item['value'] ?? 0)), $chart_data));
$chart_max = 0;
foreach ($chart_data as $item) {
    $chart_max = max($chart_max, (float) ($item['value'] ?? 0));
}
$chart_max = max(1, $chart_max);
$chart_note = $chart_note ?? 'Compara cada indicador con los demás para identificar rápidamente dónde se concentra la actividad.';
?>
<?php if (!empty($chart_data)): ?>
<section class="dashboard-chart card" aria-label="<?= htmlspecialchars($chart_title) ?>">
    <div class="chart-heading">
        <div>
            <span class="eyebrow">Indicadores</span>
            <h3><?= htmlspecialchars($chart_title) ?></h3>
        </div>
        <strong><?= number_format($chart_total, 0, ',', '.') ?><small> total</small></strong>
    </div>
    <p class="chart-note"><?= htmlspecialchars($chart_note) ?></p>
    <div class="chart-content">
        <div class="chart-visual" aria-hidden="true">
        <?php foreach ($chart_data as $item):
            $value = max(0, (float) ($item['value'] ?? 0));
            $height = ($value / $chart_max) * 100;
        ?>
            <div class="chart-column">
                <span class="chart-column-value"><?= htmlspecialchars((string) ($item['display'] ?? number_format($value, 0, ',', '.'))) ?></span>
                <div class="chart-column-track"><span style="height:<?= number_format($height, 2, '.', '') ?>%;background:<?= htmlspecialchars($item['color'] ?? '#176b87') ?>"></span></div>
                <small><?= htmlspecialchars($item['short_label'] ?? $item['label']) ?></small>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="chart-bars">
        <?php foreach ($chart_data as $item):
            $value = max(0, (float) ($item['value'] ?? 0));
            $percent = ($value / $chart_max) * 100;
            $share = $chart_total > 0 ? ($value / $chart_total) * 100 : 0;
        ?>
        <div class="chart-row">
            <div class="chart-label">
                <span><i style="background:<?= htmlspecialchars($item['color'] ?? '#176b87') ?>"></i><?= htmlspecialchars($item['label']) ?></span>
                <b><?= htmlspecialchars((string) ($item['display'] ?? number_format($value, 0, ',', '.'))) ?> <em><?= number_format($share, 0) ?>%</em></b>
            </div>
            <div class="progress-track"><span style="width:<?= number_format($percent, 2, '.', '') ?>%;background:<?= htmlspecialchars($item['color'] ?? '#176b87') ?>"></span></div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</section>
<style>
.dashboard-chart{padding:22px;margin-bottom:20px}.chart-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.chart-heading h3{margin:4px 0 0;font-size:15px}.chart-heading>strong{font-size:22px;color:#17212b;white-space:nowrap}.chart-heading small{font-size:10px;color:#657384;font-weight:500}.eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#176b87;font-weight:700}.chart-note{margin:8px 0 20px;color:#657384;font-size:11px;line-height:1.5}.chart-content{display:grid;grid-template-columns:minmax(230px, .9fr) minmax(280px, 1.1fr);gap:28px;align-items:end}.chart-visual{height:190px;display:flex;align-items:stretch;justify-content:space-around;gap:12px;padding:0 10px;border-bottom:1px solid #d9e1e7;border-left:1px solid #d9e1e7}.chart-column{height:100%;display:flex;flex:1;min-width:0;flex-direction:column;align-items:center;justify-content:flex-end;gap:5px}.chart-column-value{font-size:10px;font-weight:700;color:#17212b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.chart-column-track{height:145px;width:28px;display:flex;align-items:flex-end;background:repeating-linear-gradient(to top,#eef2f5 0,#eef2f5 1px,transparent 1px,transparent 36px);border-radius:2px 2px 0 0}.chart-column-track span{display:block;width:100%;min-height:3px;border-radius:2px 2px 0 0}.chart-column small{font-size:9px;color:#657384;text-align:center;line-height:1.2;max-width:72px}.chart-bars{display:grid;grid-template-columns:1fr;gap:14px}.chart-label{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px;color:#657384;font-size:11px}.chart-label span{display:flex;align-items:center;gap:7px}.chart-label i{width:8px;height:8px;display:inline-block;border-radius:1px}.chart-label b{color:#17212b;white-space:nowrap}.chart-label em{font-style:normal;color:#176b87;font-size:10px;font-weight:500;margin-left:4px}.chart-row .progress-track{height:8px}@media(max-width:700px){.chart-content{grid-template-columns:1fr;gap:24px}.chart-visual{order:0}.chart-bars{order:1}}
</style>
<?php endif; ?>
