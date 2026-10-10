<header class="head"><h1>Despesas</h1><p class="muted">Gastos que não são ingredientes: gás, energia, entrega, divulgação.</p></header>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif ?>
<div class="grid-2 grid-2--form">
  <section class="card">
    <?php if (!$history): ?><p class="muted">Nenhuma despesa ainda.</p><?php else: ?>
    <table class="table"><thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="num">Valor</th></tr></thead>
      <tbody><?php foreach ($history as $x): ?><tr><td><?= br_date($x['spent_on']) ?></td><td><?= e($x['description']) ?></td><td><span class="tag"><?= e($x['category']) ?></span></td><td class="num"><?= money($x['total_cents']) ?></td></tr><?php endforeach ?></tbody>
    </table><?php endif ?>
  </section>
  <form method="post" action="/despesas" class="card stack">
    <h2>Nova despesa</h2>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field">Descrição<input name="description" maxlength="120" required></label>
    <div class="row">
      <label class="field">Categoria<select name="category"><?php foreach ($categories as $c): ?><option><?= e($c) ?></option><?php endforeach ?></select></label>
      <label class="field">Valor<input name="total" inputmode="decimal" required></label>
    </div>
    <label class="field">Data<input type="date" name="date" value="<?= e($today) ?>"></label>
    <button class="button" type="submit">Registrar despesa</button>
  </form>
</div>
