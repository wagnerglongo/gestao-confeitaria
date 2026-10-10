<header class="head"><h1>Produção</h1><p class="muted">Registra as fornadas, consome os ingredientes e aumenta o estoque do produto.</p></header>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif ?>
<div class="grid-2 grid-2--form">
  <section class="card">
    <form method="get" action="/producao" class="stack" id="plan">
      <div class="row">
        <label class="field">Receita
          <select name="receita" data-autosubmit><option value="">Escolha…</option><?php foreach ($recipes as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $selected === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select>
        </label>
        <label class="field">Fornadas<input name="fornadas" inputmode="decimal" value="<?= e(qty($batches)) ?>"></label>
      </div>
      <button class="button button--ghost" type="submit">Ver o que vai ser usado</button>
    </form>
    <?php if ($needs): $ok = !array_filter($needs, fn ($n) => $n['available'] + 1e-9 < $n['needed']); ?>
      <table class="table">
        <thead><tr><th>Item</th><th class="num">Precisa</th><th class="num">Em estoque</th></tr></thead>
        <tbody><?php foreach ($needs as $n): $short = $n['available'] + 1e-9 < $n['needed']; ?>
          <tr><td><?= e($n['name']) ?><?= $n['type'] === 'recipe' ? ' <span class="tag">sub-receita</span>' : '' ?></td><td class="num"><?= qty($n['needed']) ?> <?= e($n['unit']) ?></td><td class="num"><span class="tag <?= $short ? 'tag--warn' : 'tag--ok' ?>"><?= qty($n['available']) ?></span></td></tr>
        <?php endforeach ?></tbody>
      </table>
      <form method="post" action="/producao" class="row row--end">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="recipe_id" value="<?= $selected ?>">
        <input type="hidden" name="batches" value="<?= e((string) $batches) ?>">
        <label class="field">Data<input type="date" name="date" value="<?= e($today) ?>"></label>
        <button class="button" type="submit" <?= $ok ? '' : 'disabled' ?>>Registrar produção</button>
      </form>
      <?php if (!$ok): ?><p class="muted small">Falta estoque. Registre uma compra ou produza a sub-receita antes.</p><?php endif ?>
    <?php endif ?>
  </section>
  <section class="card">
    <h2>Últimas produções</h2>
    <?php if (!$history): ?><p class="muted">Nenhuma produção ainda.</p><?php else: ?>
    <table class="table"><thead><tr><th>Data</th><th>Receita</th><th class="num">Unidades</th><th class="num">Custo</th></tr></thead>
      <tbody><?php foreach ($history as $p): ?><tr><td><?= br_date($p['produced_on']) ?></td><td><?= e($p['name']) ?></td><td class="num"><?= qty($p['units']) ?> <?= e($p['yield_unit']) ?></td><td class="num"><?= money($p['cost_cents']) ?></td></tr><?php endforeach ?></tbody>
    </table><?php endif ?>
  </section>
</div>
