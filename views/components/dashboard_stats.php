<?php
// views/components/dashboard_stats.php
?>
<div class="kpis">
    <?php foreach ($stats as $stat): ?>
        <div class="kpi" style="--kpi-color: <?= htmlspecialchars($stat['color'] ?? '#4C6E91') ?>;">
            <div class="kpi-top">
                <?php if (!empty($stat['icon'])): ?><span class="kpi-icon"><?= htmlspecialchars($stat['icon']) ?></span><?php endif; ?>
                <span class="kpi-label"><?php echo htmlspecialchars($stat['title']); ?></span>
            </div>
            <div class="kpi-value"><?php echo htmlspecialchars((string)$stat['value']); ?></div>
            <?php if (!empty($stat['hint'])): ?><div class="kpi-hint"><?= htmlspecialchars($stat['hint']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<style>
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin:0 0 24px}
.kpi{position:relative;min-height:112px;background:#fff;border:1px solid #d9e1e7;border-radius:3px;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;box-shadow:0 4px 14px rgba(23,33,43,.04);transition:transform .18s ease,box-shadow .18s ease}
.kpi::before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:var(--kpi-color)}
.kpi:hover{transform:translateY(-2px);box-shadow:0 7px 18px rgba(31,41,55,.09)}
.kpi-top{display:flex;align-items:center;gap:8px}.kpi-icon{font-size:18px;line-height:1;filter:saturate(.75)}
.kpi-label{font-size:10px;text-transform:uppercase;color:#6B7280;letter-spacing:.05em;font-weight:700;line-height:1.25}
.kpi-value{font-size:25px;font-weight:700;color:#111827;line-height:1.1;overflow-wrap:anywhere}.kpi-hint{font-size:10px;color:#657384;line-height:1.2;margin-top:5px}
@media(max-width:480px){.kpis{grid-template-columns:1fr 1fr;gap:10px}.kpi{min-height:92px;padding:12px}.kpi-value{font-size:19px}.kpi-label{font-size:9px}.kpi-icon{font-size:15px}}
</style>
