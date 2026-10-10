<?php $err = fn ($k) => isset($errors[$k]) ? '<p class="error">' . e($errors[$k]) . '</p>' : ''; ?>
<header class="head"><h1>Ingredientes e embalagens</h1><p class="muted">O estoque e o custo entram pelas <a href="/compras">compras</a>.</p></header>
<div class="grid-2 grid-2--form">
  <section class="card">
    <table class="table">
      <thead><tr><th>Nome</th><th class="num">Estoque</th><th class="num">Custo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($ingredients as $i): $low = $i['min_stock'] > 0 && $i['stock'] < $i['min_stock']; ?>
        <tr>
          <td><?= e($i['name']) ?><?= $i['kind'] === 'embalagem' ? ' <span class="tag">embalagem</span>' : '' ?></td>
          <td class="num"><span class="<?= $low ? 'tag tag--warn' : '' ?>"><?= qty($i['stock']) ?> <?= e($i['unit']) ?></span></td>
          <td class="num"><?= $i['unit'] === 'un' ? money($i['unit_cost']) . '/un' : money($i['unit_cost'] * 1000) . '/' . ($i['unit'] === 'g' ? 'kg' : 'L') ?></td>
          <td class="num"><a href="/ingredientes/<?= (int) $i['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2><?= $editing ? 'Editar ' . e($editing['name']) : 'Novo ingrediente' ?></h2>
    <form method="post" action="/ingredientes<?= $editing ? '/' . (int) $editing['id'] : '' ?>" class="stack">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="field">Nome<input name="name" value="<?= e($values['name'] ?? '') ?>" maxlength="100" required></label><?= $err('name') ?>
      <label class="field">Unidade de medida
        <select name="unit"><?php foreach (\Confeitaria\App::UNITS as $k => $label): ?><option value="<?= $k ?>" <?= ($values['unit'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select>
      </label><?= $err('unit') ?>
      <label class="field">Tipo
        <select name="kind"><option value="ingrediente">Ingrediente</option><option value="embalagem" <?= ($values['kind'] ?? '') === 'embalagem' ? 'selected' : '' ?>>Embalagem</option></select>
      </label>
      <label class="field">Estoque mínimo (alerta)<input name="min_stock" inputmode="decimal" value="<?= e(qty($values['min_stock'] ?? 0)) ?>"></label><?= $err('min_stock') ?>
      <div class="actions"><button class="button" type="submit">Salvar</button><?php if ($editing): ?><a href="/ingredientes">Cancelar</a><?php endif ?></div>
    </form>
  </section>
</div>
