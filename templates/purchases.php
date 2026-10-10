<header class="head"><h1>Compras</h1><p class="muted">Dá entrada no estoque e recalcula o custo médio do ingrediente.</p></header>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif ?>
<div class="grid-2 grid-2--form">
  <section class="card">
    <h2>Histórico</h2>
    <?php if (!$history): ?><p class="muted">Nenhuma compra ainda.</p><?php else: ?>
    <table class="table"><thead><tr><th>Data</th><th>Ingrediente</th><th class="num">Qtd.</th><th class="num">Total</th></tr></thead>
      <tbody><?php foreach ($history as $p): ?><tr><td><?= br_date($p['purchased_on']) ?></td><td><?= e($p['name']) ?><?= $p['supplier'] ? '<br><small class="muted">' . e($p['supplier']) . '</small>' : '' ?></td><td class="num"><?= qty($p['quantity']) ?> <?= e($p['unit']) ?></td><td class="num"><?= money($p['total_cents']) ?></td></tr><?php endforeach ?></tbody>
    </table><?php endif ?>
  </section>
  <form method="post" action="/compras" class="card stack">
    <h2>Nova compra</h2>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field">Ingrediente<select name="ingredient_id" required><?php foreach ($ingredients as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?> (<?= e($i['unit']) ?>)</option><?php endforeach ?></select></label>
    <div class="row">
      <label class="field">Quantidade<input name="quantity" inputmode="decimal" placeholder="1000" required></label>
      <label class="field">Valor total<input name="total" inputmode="decimal" placeholder="25,90" required></label>
    </div>
    <label class="field">Fornecedor (opcional)<input name="supplier" maxlength="100"></label>
    <label class="field">Data<input type="date" name="date" value="<?= e($today) ?>"></label>
    <button class="button" type="submit">Registrar compra</button>
  </form>
</div>
