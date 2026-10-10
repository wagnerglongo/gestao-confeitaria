<?php

declare(strict_types=1);

namespace Confeitaria\Domain;

use PDO;

/**
 * Calcula quanto custa produzir uma receita, inclusive quando ela usa outras
 * receitas (ex.: o bolo usa o recheio, que usa leite condensado e chocolate).
 *
 * - Custo de uma fornada = soma dos ingredientes + custo proporcional das sub-receitas.
 * - Custo por unidade = custo da fornada / rendimento.
 * - Detecta ciclos (A usa B, que usa A), que fariam o cálculo rodar para sempre.
 * - Guarda em memória o que já calculou para não repetir trabalho.
 */
final class RecipeCost
{
    /** @var array<int, float> custo por unidade, em centavos */
    private array $memo = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Custo de UMA fornada, em centavos (float para não perder precisão no meio da conta). */
    public function batchCost(int $recipeId, array $visiting = []): float
    {
        if (isset($visiting[$recipeId])) {
            throw new RecipeCycleException($this->cycleNames([...array_keys($visiting), $recipeId]));
        }
        $visiting[$recipeId] = true;

        $total = 0.0;
        foreach ($this->items($recipeId) as $item) {
            $total += $item['ingredient_id'] !== null
                ? (float) $item['quantity'] * (float) $item['unit_cost']
                : (float) $item['quantity'] * $this->unitCost((int) $item['sub_recipe_id'], $visiting);
        }
        return $total;
    }

    /** Custo de UMA unidade do produto, em centavos. */
    public function unitCost(int $recipeId, array $visiting = []): float
    {
        if (isset($this->memo[$recipeId])) {
            return $this->memo[$recipeId];
        }
        $stmt = $this->pdo->prepare('SELECT yield FROM recipes WHERE id = ?');
        $stmt->execute([$recipeId]);
        $yield = (float) $stmt->fetchColumn();
        if ($yield <= 0) {
            throw new \DomainException('A receita precisa ter rendimento maior que zero.');
        }
        return $this->memo[$recipeId] = $this->batchCost($recipeId, $visiting) / $yield;
    }

    /**
     * Preço sugerido para atingir a margem desejada sobre o preço de venda.
     * Margem de 60% => o custo representa 40% do preço.
     */
    public static function suggestedPrice(float $unitCostCents, float $marginPercent): int
    {
        if ($marginPercent < 0 || $marginPercent >= 100) {
            throw new \InvalidArgumentException('A margem precisa ficar entre 0% e 99%.');
        }
        return (int) ceil($unitCostCents / (1 - $marginPercent / 100));
    }

    /** Margem real de um preço: (preço - custo) / preço. */
    public static function margin(float $unitCostCents, int $priceCents): ?float
    {
        return $priceCents > 0 ? round(($priceCents - $unitCostCents) / $priceCents * 100, 1) : null;
    }

    /**
     * Verifica se colocar $subRecipeId dentro de $recipeId criaria um ciclo.
     * Usado ao salvar a receita, antes de gravar.
     */
    public function wouldCreateCycle(int $recipeId, int $subRecipeId): bool
    {
        if ($recipeId === $subRecipeId) {
            return true;
        }
        $stack = [$subRecipeId];
        $seen = [];
        while ($stack) {
            $current = array_pop($stack);
            if ($current === $recipeId) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $stmt = $this->pdo->prepare('SELECT sub_recipe_id FROM recipe_items WHERE recipe_id = ? AND sub_recipe_id IS NOT NULL');
            $stmt->execute([$current]);
            array_push($stack, ...array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }
        return false;
    }

    /** @return list<array<string, mixed>> */
    private function items(int $recipeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ri.ingredient_id, ri.sub_recipe_id, ri.quantity, i.unit_cost
             FROM recipe_items ri LEFT JOIN ingredients i ON i.id = ri.ingredient_id
             WHERE ri.recipe_id = ?'
        );
        $stmt->execute([$recipeId]);
        return $stmt->fetchAll();
    }

    /** @param list<int> $ids */
    private function cycleNames(array $ids): string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM recipes WHERE id = ?');
        $names = array_map(function (int $id) use ($stmt) {
            $stmt->execute([$id]);
            return (string) $stmt->fetchColumn();
        }, $ids);
        return implode(' → ', $names);
    }
}
