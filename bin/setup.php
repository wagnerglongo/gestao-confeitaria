<?php

declare(strict_types=1);

/**
 * Cria as tabelas, o usuário e (opcional) 30 dias de dados de exemplo.
 *   ADMIN_PASSWORD=... php bin/setup.php
 *   php bin/setup.php --sem-exemplo
 */

use Confeitaria\Database;
use Confeitaria\Domain\Inventory;
use Confeitaria\Domain\RecipeCost;
use Confeitaria\Service\Auth;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Database::connect();
Database::migrate($pdo);

$empty = (int) $pdo->query('SELECT COUNT(*) FROM ingredients')->fetchColumn() === 0;
if ($empty && !in_array('--sem-exemplo', $argv, true)) {
    mt_srand(42); // dados de exemplo sempre iguais
    $inventory = new Inventory($pdo, new RecipeCost($pdo));
    $today = new DateTimeImmutable();
    $day = fn (int $ago) => $today->modify("-{$ago} days")->format('Y-m-d');

    $ingredient = function (string $name, string $unit, float $min, string $kind = 'ingrediente') use ($pdo): int {
        $pdo->prepare('INSERT INTO ingredients (name, unit, kind, min_stock) VALUES (?, ?, ?, ?)')->execute([$name, $unit, $kind, $min]);
        return (int) $pdo->lastInsertId();
    };
    $leite = $ingredient('Leite condensado', 'g', 2000);
    $creme = $ingredient('Creme de leite', 'g', 1000);
    $cacau = $ingredient('Chocolate em pó 50%', 'g', 500);
    $manteiga = $ingredient('Manteiga', 'g', 500);
    $farinha = $ingredient('Farinha de trigo', 'g', 2000);
    $acucar = $ingredient('Açúcar', 'g', 2000);
    $ovos = $ingredient('Ovos', 'un', 24);
    $leiteIntegral = $ingredient('Leite integral', 'ml', 2000);
    $granulado = $ingredient('Granulado belga', 'g', 300);
    $forminha = $ingredient('Forminha nº 4', 'un', 200, 'embalagem');
    $pote = $ingredient('Pote 250 ml com tampa', 'un', 30, 'embalagem');

    // Compras iniciais (quantidade, total em centavos)
    foreach ([[$leite, 12000, 13800], [$creme, 6000, 5400], [$cacau, 3000, 8700], [$manteiga, 3000, 8400], [$farinha, 10000, 5900],
              [$acucar, 10000, 4900], [$ovos, 120, 9600], [$leiteIntegral, 12000, 6600], [$granulado, 2000, 11800],
              [$forminha, 1000, 3500], [$pote, 120, 8400]] as [$id, $q, $total]) {
        $inventory->purchase($id, $q, $total, $day(30), 'Atacado Exemplo');
    }

    $recipe = function (string $name, float $yield, string $unit, int $price, bool $sellable, array $items) use ($pdo): int {
        $pdo->prepare('INSERT INTO recipes (name, yield, yield_unit, price_cents, sellable) VALUES (?, ?, ?, ?, ?)')->execute([$name, $yield, $unit, $price, $sellable ? 1 : 0]);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO recipe_items (recipe_id, ingredient_id, sub_recipe_id, quantity) VALUES (?, ?, ?, ?)');
        foreach ($items as [$type, $itemId, $q]) {
            $stmt->execute([$id, $type === 'i' ? $itemId : null, $type === 'r' ? $itemId : null, $q]);
        }
        return $id;
    };
    $massaBrigadeiro = $recipe('Massa de brigadeiro', 1000, 'g', 0, false, [['i', $leite, 790], ['i', $creme, 200], ['i', $cacau, 60], ['i', $manteiga, 30]]);
    $massaBolo = $recipe('Massa de bolo de chocolate', 1200, 'g', 0, false, [['i', $farinha, 300], ['i', $acucar, 250], ['i', $ovos, 4], ['i', $cacau, 80], ['i', $leiteIntegral, 240], ['i', $manteiga, 100]]);
    $brigadeiro = $recipe('Brigadeiro gourmet', 50, 'un', 300, true, [['r', $massaBrigadeiro, 1000], ['i', $granulado, 250], ['i', $forminha, 50]]);
    $boloPote = $recipe('Bolo de pote de brigadeiro', 8, 'un', 1600, true, [['r', $massaBolo, 1200], ['r', $massaBrigadeiro, 800], ['i', $pote, 8]]);
    $boloInteiro = $recipe('Bolo de chocolate inteiro', 1, 'un', 9000, true, [['r', $massaBolo, 1200], ['r', $massaBrigadeiro, 600]]);

    // 30 dias de operação: produz quando precisa, vende todo dia
    for ($ago = 29; $ago >= 0; $ago--) {
        $date = $day($ago);
        if ($ago % 6 === 0) {
            $inventory->purchase($leite, 6000, 7200, $date, 'Atacado Exemplo');
            $inventory->purchase($granulado, 1000, 6200, $date, 'Atacado Exemplo');
            $inventory->purchase($creme, 2000, 1800, $date, 'Atacado Exemplo');
            $inventory->purchase($ovos, 60, 4800, $date, 'Granja Exemplo');
            $inventory->purchase($cacau, 1500, 4500, $date, 'Atacado Exemplo');
            $inventory->purchase($manteiga, 1000, 2900, $date, 'Atacado Exemplo');
        }
        // [receita, fornadas, produz quando o estoque fica abaixo de]
        foreach ([[$massaBrigadeiro, 3, 2200], [$massaBolo, 2, 2500], [$brigadeiro, 1, 40], [$boloPote, 1, 8], [$boloInteiro, 1, 1]] as [$id, $batches, $threshold]) {
            $stock = (float) $pdo->query("SELECT stock FROM recipes WHERE id = {$id}")->fetchColumn();
            if ($stock < $threshold) {
                try {
                    $inventory->produce($id, $batches, $date);
                } catch (Throwable) {
                    // sem estoque: segue o dia
                }
            }
        }
        foreach ([[$brigadeiro, mt_rand(10, 40), 300], [$boloPote, mt_rand(2, 7), 1600], [$boloInteiro, mt_rand(0, 1), 9000]] as [$id, $q, $price]) {
            $stock = (float) $pdo->query("SELECT stock FROM recipes WHERE id = {$id}")->fetchColumn();
            $q = min($q, (int) $stock);
            if ($q > 0) {
                $inventory->sell($id, $q, $q * $price, $date);
            }
        }
        if ($ago % 10 === 0) {
            $pdo->prepare('INSERT INTO expenses (description, category, total_cents, spent_on) VALUES (?, ?, ?, ?)')->execute(['Gás de cozinha', 'Operacional', 12500, $date]);
        }
        if ($ago === 15) {
            $pdo->prepare('INSERT INTO expenses (description, category, total_cents, spent_on) VALUES (?, ?, ?, ?)')->execute(['Impulsionamento no Instagram', 'Marketing', 5000, $date]);
        }
    }
    echo "✔ 30 dias de dados de exemplo criados.\n";
}

if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    $email = getenv('ADMIN_EMAIL') ?: 'admin@exemplo.com';
    $password = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(6));
    (new Auth($pdo))->createUser($email, $password);
    echo "✔ Usuário: {$email}\n";
    if (!getenv('ADMIN_PASSWORD')) {
        echo "  Senha gerada: {$password}\n";
    }
}
echo "✔ Pronto.\n";
