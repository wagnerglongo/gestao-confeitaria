<?php

declare(strict_types=1);

namespace Confeitaria\Domain;

use PDO;
use Throwable;

/**
 * Movimentações de estoque: compra, produção e venda.
 *
 * Toda operação roda dentro de uma transação: ou tudo é gravado, ou nada.
 * Antes de mexer no estoque, confere se há o suficiente de TUDO; assim a
 * produção nunca fica pela metade (problema da versão anterior do sistema).
 */
final class Inventory
{
    public function __construct(private readonly PDO $pdo, private readonly RecipeCost $costs)
    {
    }

    /**
     * Registra a compra e atualiza o custo do ingrediente pela média ponderada:
     * (estoque × custo atual + compra) / (estoque + quantidade comprada).
     */
    public function purchase(int $ingredientId, float $quantity, int $totalCents, string $date, ?string $supplier = null): void
    {
        if ($quantity <= 0 || $totalCents <= 0) {
            throw new InventoryException('Informe quantidade e valor maiores que zero.');
        }

        $this->transaction(function () use ($ingredientId, $quantity, $totalCents, $date, $supplier) {
            $ingredient = $this->ingredient($ingredientId);
            $stock = max(0.0, (float) $ingredient['stock']);
            $newStock = $stock + $quantity;
            $newCost = ($stock * (float) $ingredient['unit_cost'] + $totalCents) / $newStock;

            $this->pdo->prepare('UPDATE ingredients SET stock = stock + ?, unit_cost = ? WHERE id = ?')
                ->execute([$quantity, round($newCost, 4), $ingredientId]);
            $this->pdo->prepare('INSERT INTO purchases (ingredient_id, quantity, total_cents, supplier, purchased_on) VALUES (?, ?, ?, ?, ?)')
                ->execute([$ingredientId, $quantity, $totalCents, $supplier, $date]);
        });
    }

    /**
     * Quanto de cada ingrediente e sub-receita uma produção precisa.
     * @return list<array{type: string, id: int, name: string, unit: string, needed: float, available: float}>
     */
    public function requirements(int $recipeId, float $batches): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ri.quantity,
                    ri.ingredient_id, i.name AS ing_name, i.unit AS ing_unit, i.stock AS ing_stock,
                    ri.sub_recipe_id, r.name AS rec_name, r.yield_unit AS rec_unit, r.stock AS rec_stock
             FROM recipe_items ri
             LEFT JOIN ingredients i ON i.id = ri.ingredient_id
             LEFT JOIN recipes r ON r.id = ri.sub_recipe_id
             WHERE ri.recipe_id = ?"
        );
        $stmt->execute([$recipeId]);

        return array_map(fn (array $row) => $row['ingredient_id'] !== null
            ? ['type' => 'ingredient', 'id' => (int) $row['ingredient_id'], 'name' => $row['ing_name'], 'unit' => $row['ing_unit'],
               'needed' => (float) $row['quantity'] * $batches, 'available' => (float) $row['ing_stock']]
            : ['type' => 'recipe', 'id' => (int) $row['sub_recipe_id'], 'name' => $row['rec_name'], 'unit' => $row['rec_unit'],
               'needed' => (float) $row['quantity'] * $batches, 'available' => (float) $row['rec_stock']],
            $stmt->fetchAll());
    }

    /** Produz N fornadas: consome ingredientes e sub-receitas e aumenta o estoque do produto. */
    public function produce(int $recipeId, float $batches, string $date): array
    {
        if ($batches <= 0) {
            throw new InventoryException('Informe um número de fornadas maior que zero.');
        }

        return $this->transaction(function () use ($recipeId, $batches, $date) {
            $needs = $this->requirements($recipeId, $batches);
            if (!$needs) {
                throw new InventoryException('Essa receita ainda não tem ingredientes.');
            }

            $missing = array_filter($needs, fn ($n) => $n['available'] + 1e-9 < $n['needed']);
            if ($missing) {
                throw new InventoryException('Estoque insuficiente: ' . implode('; ', array_map(
                    fn ($n) => sprintf('%s (precisa %s %s, tem %s)', $n['name'], self::qty($n['needed']), $n['unit'], self::qty($n['available'])),
                    $missing,
                )));
            }

            $cost = (int) round($this->costs->batchCost($recipeId) * $batches);
            $units = $batches * (float) $this->recipe($recipeId)['yield'];

            $useIngredient = $this->pdo->prepare('UPDATE ingredients SET stock = stock - ? WHERE id = ?');
            $useRecipe = $this->pdo->prepare('UPDATE recipes SET stock = stock - ? WHERE id = ?');
            foreach ($needs as $need) {
                ($need['type'] === 'ingredient' ? $useIngredient : $useRecipe)->execute([$need['needed'], $need['id']]);
            }

            $this->pdo->prepare('UPDATE recipes SET stock = stock + ? WHERE id = ?')->execute([$units, $recipeId]);
            $this->pdo->prepare('INSERT INTO productions (recipe_id, batches, units, cost_cents, produced_on) VALUES (?, ?, ?, ?, ?)')
                ->execute([$recipeId, $batches, $units, $cost, $date]);

            return ['units' => $units, 'cost_cents' => $cost];
        });
    }

    /** Venda: baixa o estoque do produto e guarda o custo do momento (para calcular lucro depois). */
    public function sell(int $recipeId, float $quantity, int $totalCents, string $date, ?string $customer = null): void
    {
        if ($quantity <= 0 || $totalCents < 0) {
            throw new InventoryException('Informe quantidade maior que zero e um valor válido.');
        }

        $this->transaction(function () use ($recipeId, $quantity, $totalCents, $date, $customer) {
            $recipe = $this->recipe($recipeId);
            if ((float) $recipe['stock'] + 1e-9 < $quantity) {
                throw new InventoryException(sprintf('Só há %s %s de %s em estoque.', self::qty((float) $recipe['stock']), $recipe['yield_unit'], $recipe['name']));
            }
            $cost = (int) round($this->costs->unitCost($recipeId) * $quantity);

            $this->pdo->prepare('UPDATE recipes SET stock = stock - ? WHERE id = ?')->execute([$quantity, $recipeId]);
            $this->pdo->prepare('INSERT INTO sales (recipe_id, quantity, total_cents, cost_cents, customer, sold_on) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$recipeId, $quantity, $totalCents, $cost, $customer, $date]);
        });
    }

    /** Ingredientes abaixo do estoque mínimo. */
    public function lowStock(): array
    {
        return $this->pdo->query('SELECT * FROM ingredients WHERE min_stock > 0 AND stock < min_stock ORDER BY name')->fetchAll();
    }

    public static function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }

    private function ingredient(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ingredients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new InventoryException('Ingrediente não encontrado.');
    }

    private function recipe(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recipes WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new InventoryException('Produto não encontrado.');
    }

    private function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
}
