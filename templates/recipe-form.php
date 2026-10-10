<?php
$err = fn ($k) => isset($errors[$k]) ? '<p class="error">' . e($errors[$k]) . '</p>' : '';
$priceValue = $values['price'] ?? (isset($values['price_cents']) && $values['price_cents'] ? number_format($values['price_cents'] / 100, 2, ',', '.') : '');
$rows = $items ?: [['ingredient_id' => null, 'sub_recipe_id' => null, 'quantity' => '']];
?>
<header class="head"><h1><?= $recipe ? e($recipe['name']) : 'Nova receita' ?></h1></header>
<div class="grid-2 grid-2--form">
  <form method="post" action="/receitas<?= $recipe ? '/' . (int) $recipe['id'] : '' ?>" class="card stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field">Nome<input name="name" value="<?= e($values['name'] ?? '') ?>" maxlength="100" required></label><?= $err('name') ?>
    <div class="row">
      <label class="field">Uma fornada rende<input name="yield" inputmode="decimal" value="<?= e(isset($values['yield']) && $values['yield'] !== '' ? qty($values['yield']) : '') ?>" required></label>
      <label class="field">Unidade<input name="yield_unit" value="<?= e($values['yield_unit'] ?? 'un') ?>" maxlength="12" placeholder="un, g, fatias"></label>
    </div><?= $err('yield') ?>

    <fieldset class="items">
      <legend>Ingredientes de uma fornada</legend>
      <div id="items">
        <?php foreach ($rows as $row): $ref = $row['ingredient_id'] ? 'i:' . $row['ingredient_id'] : ($row['sub_recipe_id'] ? 'r:' . $row['sub_recipe_id'] : ''); ?>
          <div class="item-row">
            <select name="item[]" aria-label="Ingrediente ou sub-receita">
              <option value="">Escolha…</option>
              <optgroup label="Ingredientes"><?php foreach ($ingredients as $i): ?><option value="i:<?= (int) $i['id'] ?>" data-unit="<?= e($i['unit']) ?>" <?= $ref === 'i:' . $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?> (<?= e($i['unit']) ?>)</option><?php endforeach ?></optgroup>
              <?php if ($subRecipes): ?><optgroup label="Sub-receitas"><?php foreach ($subRecipes as $s): ?><option value="r:<?= (int) $s['id'] ?>" <?= $ref === 'r:' . $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> (<?= e($s['yield_unit']) ?>)</option><?php endforeach ?></optgroup><?php endif ?>
            </select>
            <input name="quantity[]" inputmode="decimal" placeholder="Qtd." aria-label="Quantidade" value="<?= e($row['quantity'] !== '' ? qty($row['quantity']) : '') ?>">
            <button class="icon" type="button" data-remove aria-label="Remover linha">×</button>
          </div>
        <?php endforeach ?>
      </div>
      <button class="button button--ghost" type="button" id="add-item">Adicionar ingrediente</button>
    </fieldset><?= $err('items') ?>

    <div class="row">
      <label class="field">Preço de venda (por unidade)<input name="price" inputmode="decimal" placeholder="4,50" value="<?= e($priceValue) ?>"></label>
      <label class="check"><input type="checkbox" name="sellable" value="1" <?= !empty($values['sellable']) ? 'checked' : '' ?>> Vendido ao cliente</label>
    </div><?= $err('price') ?>
    <p class="muted small">Desmarque "vendido" para receitas-base, como massas e recheios usados em outros produtos.</p>
    <div class="actions"><button class="button" type="submit">Salvar receita</button><a href="/receitas">Voltar</a></div>
  </form>

  <aside class="card">
    <h2>Custo e preço</h2>
    <?php if (!$cost): ?>
      <p class="muted">Salve a receita para ver o custo.</p>
    <?php else: $price = (int) ($recipe['price_cents'] ?? 0); $margin = \Confeitaria\Domain\RecipeCost::margin($cost['unit'], $price); ?>
      <dl class="facts">
        <dt>Custo da fornada</dt><dd><?= money($cost['batch']) ?></dd>
        <dt>Custo por <?= e($recipe['yield_unit']) ?></dt><dd><?= money($cost['unit']) ?></dd>
        <?php if ($price): ?><dt>Margem atual</dt><dd><span class="tag <?= $margin < 40 ? 'tag--warn' : 'tag--ok' ?>"><?= str_replace('.', ',', (string) $margin) ?>%</span></dd><?php endif ?>
      </dl>
      <h3>Preço sugerido</h3>
      <table class="table">
        <thead><tr><th>Margem</th><th class="num">Preço</th></tr></thead>
        <tbody><?php foreach ([50, 60, 70] as $m): ?><tr><td><?= $m ?>%</td><td class="num"><?= money(\Confeitaria\Domain\RecipeCost::suggestedPrice($cost['unit'], $m)) ?></td></tr><?php endforeach ?></tbody>
      </table>
      <p class="muted small">Margem sobre o preço de venda. Ex.: 60% significa que o custo é 40% do preço.</p>
    <?php endif ?>
  </aside>
</div>
