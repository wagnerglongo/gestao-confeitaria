<header class="head head--row"><div><h1>Receitas e produtos</h1><p class="muted">Custo calculado a partir do custo médio dos ingredientes, incluindo sub-receitas.</p></div><a class="button" href="/receitas/nova">Nova receita</a></header>
<section class="card">
  <table class="table">
    <thead><tr><th>Receita</th><th class="num">Rende</th><th class="num">Custo/un</th><th class="num">Preço</th><th class="num">Margem</th><th class="num">Estoque</th></tr></thead>
    <tbody>
    <?php foreach ($recipes as $r): ?>
      <tr>
        <td><a href="/receitas/<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a><?= $r['sellable'] ? '' : ' <span class="tag">base</span>' ?></td>
        <td class="num"><?= qty($r['yield']) ?> <?= e($r['yield_unit']) ?></td>
        <td class="num"><?= $r['unit_cost'] === null ? '—' : money($r['unit_cost']) ?></td>
        <td class="num"><?= $r['price_cents'] ? money($r['price_cents']) : '—' ?></td>
        <td class="num"><?php if ($r['margin'] !== null): ?><span class="tag <?= $r['margin'] < 40 ? 'tag--warn' : 'tag--ok' ?>"><?= str_replace('.', ',', (string) $r['margin']) ?>%</span><?php else: ?>—<?php endif ?></td>
        <td class="num"><?= qty($r['stock']) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</section>
