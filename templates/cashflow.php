<header class="head head--row">
  <div><h1>Fluxo de caixa</h1><p class="muted"><?= br_date($from) ?> a <?= br_date($to) ?></p></div>
  <form method="get" action="/caixa" class="row row--end">
    <label class="field">De<input type="date" name="de" value="<?= e($from) ?>"></label>
    <label class="field">Até<input type="date" name="ate" value="<?= e($to) ?>"></label>
    <button class="button button--ghost" type="submit">Filtrar</button>
    <a class="button" href="/caixa.csv?de=<?= e($from) ?>&amp;ate=<?= e($to) ?>">Exportar para Excel</a>
  </form>
</header>
<section class="kpis">
  <div class="kpi"><span>Entradas</span><strong class="pos"><?= money($flow['income']) ?></strong></div>
  <div class="kpi"><span>Saídas</span><strong class="neg"><?= money($flow['outcome']) ?></strong></div>
  <div class="kpi kpi--accent"><span>Saldo do período</span><strong><?= money($flow['balance']) ?></strong></div>
</section>
<section class="card">
  <?php if (!$flow['rows']): ?><p class="muted">Nenhuma movimentação no período.</p><?php else: ?>
  <table class="table">
    <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th class="num">Valor</th><th class="num">Saldo</th></tr></thead>
    <tbody><?php foreach ($flow['rows'] as $r): ?>
      <tr><td><?= br_date($r['day']) ?></td><td><span class="tag <?= $r['type'] === 'entrada' ? 'tag--ok' : '' ?>"><?= e($r['category']) ?></span></td><td><?= e($r['description']) ?></td>
      <td class="num <?= $r['type'] === 'entrada' ? 'pos' : 'neg' ?>"><?= $r['type'] === 'entrada' ? '+' : '−' ?> <?= money($r['cents']) ?></td><td class="num"><?= money($r['balance']) ?></td></tr>
    <?php endforeach ?></tbody>
  </table><?php endif ?>
</section>
