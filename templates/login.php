<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Entrar · Gestão de Confeitaria</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="login">
  <main class="login__box">
    <p class="brand">Gestão de Confeitaria</p>
    <h1>Entrar</h1>
    <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif ?>
    <form method="post" action="/login" class="stack">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="field">E-mail<input type="email" name="email" autocomplete="username" required autofocus></label>
      <label class="field">Senha<input type="password" name="password" autocomplete="current-password" required></label>
      <button class="button" type="submit">Entrar</button>
    </form>
  </main>
</body>
</html>
