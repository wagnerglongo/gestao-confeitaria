<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\Domain\RecipeCost;
use Confeitaria\Domain\RecipeCycleException;

final class RecipeCostTest extends TestCase
{
    public function testCustoDeReceitaSimples(): void
    {
        // Leite condensado: 2 centavos por grama; chocolate: 5 centavos por grama
        $leite = $this->ingredient('Leite condensado', 'g', 0, 2);
        $choco = $this->ingredient('Chocolate', 'g', 0, 5);
        $brigadeiro = $this->recipe('Brigadeiro', 25, [['i', $leite, 395], ['i', $choco, 50]]);

        $costs = new RecipeCost($this->pdo);
        $this->assertEqualsWithDelta(1040, $costs->batchCost($brigadeiro), 0.001);   // 790 + 250
        $this->assertEqualsWithDelta(41.6, $costs->unitCost($brigadeiro), 0.001);    // 1040 / 25
    }

    public function testSubReceitaEntraProporcionalmente(): void
    {
        $leite = $this->ingredient('Leite condensado', 'g', 0, 2);
        $farinha = $this->ingredient('Farinha', 'g', 0, 0.5);
        // Recheio rende 1000 g por fornada e custa 800 → 0,8 centavo por grama
        $recheio = $this->recipe('Recheio', 1000, [['i', $leite, 400]]);
        // Bolo usa 500 g de recheio (400 centavos) + 300 g de farinha (150) e rende 10 fatias
        $bolo = $this->recipe('Bolo', 10, [['r', $recheio, 500], ['i', $farinha, 300]]);

        $costs = new RecipeCost($this->pdo);
        $this->assertEqualsWithDelta(550, $costs->batchCost($bolo), 0.001);
        $this->assertEqualsWithDelta(55, $costs->unitCost($bolo), 0.001);
    }

    public function testDetectaReceitaCircular(): void
    {
        $a = $this->recipe('Massa A', 1, []);
        $b = $this->recipe('Massa B', 1, [['r', $a, 1]]);
        $this->pdo->prepare('INSERT INTO recipe_items (recipe_id, sub_recipe_id, quantity) VALUES (?, ?, 1)')->execute([$a, $b]);

        $this->expectException(RecipeCycleException::class);
        $this->expectExceptionMessage('Massa A → Massa B → Massa A');
        (new RecipeCost($this->pdo))->batchCost($a);
    }

    public function testImpedeCicloAntesDeSalvar(): void
    {
        $a = $this->recipe('A', 1, []);
        $b = $this->recipe('B', 1, [['r', $a, 1]]);
        $c = $this->recipe('C', 1, [['r', $b, 1]]);
        $costs = new RecipeCost($this->pdo);

        $this->assertTrue($costs->wouldCreateCycle($a, $c), 'A → C → B → A');
        $this->assertTrue($costs->wouldCreateCycle($a, $a), 'A dentro de A');
        $this->assertFalse($costs->wouldCreateCycle($c, $a), 'C já depende de A, sem ciclo');
    }

    public function testPrecoSugeridoEMargem(): void
    {
        // custo 40 centavos com margem de 60% → preço 100
        $this->assertSame(100, RecipeCost::suggestedPrice(40, 60));
        $this->assertSame(42, RecipeCost::suggestedPrice(41.6, 0));
        $this->assertSame(60.0, RecipeCost::margin(40, 100));
        $this->assertNull(RecipeCost::margin(40, 0));
        $this->expectException(\InvalidArgumentException::class);
        RecipeCost::suggestedPrice(40, 100);
    }
}
