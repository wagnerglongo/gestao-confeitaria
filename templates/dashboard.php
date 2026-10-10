<?php
$max = max(1, ...array_values($daily));
$w = 720; $h = 180; $bar = $w / count($daily);
?>
<header class="head"><h1>Painel</h1><p class="muted">Últimos 30 dias (<?= br_date($from) ?> a <?= br_date($to) ?>)</p></header>

<section class="kpis" aria-label="Resumo">
  <div class="kpi"><span>Faturamento</span><strong><?= money($summary['revenue']) ?></strong><small><?= $summary['orders'] ?> vendas</small></div>
  <div class="kpi"><span>Lucro bruto</span><strong><?= money($summary['gross_profit']) ?></strong><small><?= $summary['margin'] !== null ? 'margem de ' . str_replace('.', ',', (string) $summary['margin']) . '%' : 'sem vendas' ?></small></div>
  <div class="kpi"><span>Despesas</span><strong><?= money($summary['expenses']) ?></strong><small>operacionais</small></div>
  <div class="kpi kpi--accent"><span>Lucro líquido</span><strong><?= money($summary['net_profit']) ?></strong><small>lucro bruto menos despesas</small></div>
</section>

<section class="card">
  <h2>Faturamento por dia</h2>
  <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h + 24 ?>" role="img" aria-label="Gráfico de faturamento diário">
    <?php $i = 0; foreach ($daily as $day => $cents): $bh = $cents / $max * $h; ?>
      <rect x="<?= round($i * $bar + 2, 1) ?>" y="<?= round($h - $bh, 1) ?>" width="<?= round($bar - 4, 1) ?>" height="<?= round(max($bh, $cents > 0 ? 2 : 0), 1) ?>" rx="2"><title><?= br_date($day) ?>: <?= money($cents) ?></title></rect>
      <?php if ($i % 7 === 0): ?><text x="<?= round($i * $bar + 2, 1) ?>" y="<?= $h + 18 ?>"><?= date('d/m', strtotime($day)) ?></text><?php endif ?>
    <?php $i++; endforeach ?>
    <line x1="0" x2="<?= $w ?>" y1="<?= $h ?>" y2="<?= $h ?>" />
  </svg>
</section>

<div class="grid-2">
  <section class="card">
    <h2>Mais vendidos</h2>
    <?php if (!$top): ?><p class="muted">Nenhuma venda no período.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Faturamento</th><th class="num">Lucro</th></tr></thead>
      <tbody><?php foreach ($top as $p): ?><tr><td><?= e($p['name']) ?></td><td class="num"><?= qty($p['quantity']) ?></td><td class="num"><?= money((int) $p['revenue']) ?></td><td class="num"><?= money((int) $p['profit']) ?></td></tr><?php endforeach ?></tbody>
    </table>
    <?php endif ?>
  </section>
  <section class="card">
    <h2>Estoque baixo</h2>
    <?php if (!$lowStock): ?><p class="muted">Tudo acima do mínimo.</p><?php else: ?>
    <ul class="list">
      <?php foreach ($lowStock as $ing): ?>
        <li><span><?= e($ing['name']) ?></span><span class="tag tag--warn"><?= qty($ing['stock']) ?> de <?= qty($ing['min_stock']) ?> <?= e($ing['unit']) ?></span></li>
      <?php endforeach ?>
    </ul>
    <a class="button button--ghost" href="/compras">Registrar compra</a>
    <?php endif ?>
  </section>
</div>
