<?php
$nav = ['dashboard' => ['/', 'Painel'], 'recipes' => ['/receitas', 'Receitas'], 'production' => ['/producao', 'Produção'],
        'sales' => ['/vendas', 'Vendas'], 'ingredients' => ['/ingredientes', 'Ingredientes'], 'purchases' => ['/compras', 'Compras'],
        'expenses' => ['/despesas', 'Despesas'], 'cashflow' => ['/caixa', 'Caixa']];
$current = $active === 'recipe-form' ? 'recipes' : $active;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($nav[$current][1] ?? 'Gestão') ?> · Gestão de Confeitaria</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body>
  <aside class="side">
    <a class="brand" href="/">Gestão de<br>Confeitaria</a>
    <nav aria-label="Principal">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" <?= $key === $current ? 'aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach ?>
    </nav>
    <form method="post" action="/sair"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link" type="submit">Sair</button></form>
  </aside>
  <main class="main">
    <?php if ($flash): ?><p class="flash" role="status"><?= e($flash) ?></p><?php endif ?>
    <?= $content ?>
  </main>
</body>
</html>
