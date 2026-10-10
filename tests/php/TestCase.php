<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\Database;
use Confeitaria\Domain\Inventory;
use Confeitaria\Domain\RecipeCost;
use PDO;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Database::connect('sqlite::memory:');
        Database::migrate($this->pdo);
    }

    protected function ingredient(string $name, string $unit, float $stock, float $unitCost, float $min = 0): int
    {
        $this->pdo->prepare('INSERT INTO ingredients (name, unit, stock, unit_cost, min_stock) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $unit, $stock, $unitCost, $min]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param list<array{0: 'i'|'r', 1: int, 2: float}> $items */
    protected function recipe(string $name, float $yield, array $items, int $price = 0, float $stock = 0): int
    {
        $this->pdo->prepare('INSERT INTO recipes (name, yield, price_cents, stock) VALUES (?, ?, ?, ?)')->execute([$name, $yield, $price, $stock]);
        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('INSERT INTO recipe_items (recipe_id, ingredient_id, sub_recipe_id, quantity) VALUES (?, ?, ?, ?)');
        foreach ($items as [$type, $itemId, $quantity]) {
            $stmt->execute([$id, $type === 'i' ? $itemId : null, $type === 'r' ? $itemId : null, $quantity]);
        }
        return $id;
    }

    protected function inventory(): Inventory
    {
        return new Inventory($this->pdo, new RecipeCost($this->pdo));
    }

    protected function value(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
