<header class="head"><h1>Vendas</h1></header>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif ?>
<div class="grid-2 grid-2--form">
  <section class="card">
    <h2>Últimas vendas</h2>
    <?php if (!$history): ?><p class="muted">Nenhuma venda ainda.</p><?php else: ?>
    <table class="table"><thead><tr><th>Data</th><th>Produto</th><th class="num">Qtd.</th><th class="num">Total</th><th class="num">Lucro</th></tr></thead>
      <tbody><?php foreach ($history as $s): ?><tr><td><?= br_date($s['sold_on']) ?></td><td><?= e($s['name']) ?><?= $s['customer'] ? '<br><small class="muted">' . e($s['customer']) . '</small>' : '' ?></td><td class="num"><?= qty($s['quantity']) ?></td><td class="num"><?= money($s['total_cents']) ?></td><td class="num"><?= money($s['total_cents'] - $s['cost_cents']) ?></td></tr><?php endforeach ?></tbody>
    </table><?php endif ?>
  </section>
  <form method="post" action="/vendas" class="card stack">
    <h2>Nova venda</h2>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field">Produto<select name="recipe_id" required><?php foreach ($recipes as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?> (<?= qty($r['stock']) ?> em estoque · <?= money($r['price_cents']) ?>)</option><?php endforeach ?></select></label>
    <div class="row">
      <label class="field">Quantidade<input name="quantity" inputmode="decimal" required></label>
      <label class="field">Valor total <span class="muted">(vazio = preço × qtd.)</span><input name="total" inputmode="decimal"></label>
    </div>
    <label class="field">Cliente (opcional)<input name="customer" maxlength="100"></label>
    <label class="field">Data<input type="date" name="date" value="<?= e($today) ?>"></label>
    <button class="button" type="submit">Registrar venda</button>
  </form>
</div>
