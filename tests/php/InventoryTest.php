<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\Domain\InventoryException;

final class InventoryTest extends TestCase
{
    public function testCompraAtualizaEstoqueECustoMedio(): void
    {
        // 1000 g a 2 centavos/g em estoque; compra 1000 g por R$ 30,00 (3 c/g) → média 2,5 c/g
        $leite = $this->ingredient('Leite', 'g', 1000, 2);
        $this->inventory()->purchase($leite, 1000, 3000, '2026-10-01', 'Atacado');

        $this->assertEqualsWithDelta(2000, (float) $this->value('SELECT stock FROM ingredients WHERE id = ?', [$leite]), 0.001);
        $this->assertEqualsWithDelta(2.5, (float) $this->value('SELECT unit_cost FROM ingredients WHERE id = ?', [$leite]), 0.0001);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM purchases'));
    }

    public function testProducaoConsomeIngredientesESubReceitas(): void
    {
        $leite = $this->ingredient('Leite', 'g', 2000, 2);
        $recheio = $this->recipe('Recheio', 1000, [['i', $leite, 400]], stock: 1500);
        $farinha = $this->ingredient('Farinha', 'g', 1000, 0.5);
        $bolo = $this->recipe('Bolo', 10, [['r', $recheio, 500], ['i', $farinha, 300]]);

        $result = $this->inventory()->produce($bolo, 2, '2026-10-02');

        $this->assertEquals(20, $result['units']);
        $this->assertSame(1100, $result['cost_cents']); // 2 × 550
        $this->assertEqualsWithDelta(500, (float) $this->value('SELECT stock FROM recipes WHERE id = ?', [$recheio]), 0.001);
        $this->assertEqualsWithDelta(400, (float) $this->value('SELECT stock FROM ingredients WHERE id = ?', [$farinha]), 0.001);
        $this->assertEqualsWithDelta(20, (float) $this->value('SELECT stock FROM recipes WHERE id = ?', [$bolo]), 0.001);
    }

    public function testProducaoSemEstoqueNaoMexeEmNada(): void
    {
        $leite = $this->ingredient('Leite', 'g', 100, 2);
        $acucar = $this->ingredient('Açúcar', 'g', 5000, 1);
        $doce = $this->recipe('Doce', 10, [['i', $acucar, 200], ['i', $leite, 395]]);

        try {
            $this->inventory()->produce($doce, 1, '2026-10-02');
            $this->fail('Deveria recusar a produção');
        } catch (InventoryException $error) {
            $this->assertStringContainsString('Leite (precisa 395 g, tem 100)', $error->getMessage());
        }
        // O açúcar NÃO pode ter sido descontado: tudo ou nada
        $this->assertEqualsWithDelta(5000, (float) $this->value('SELECT stock FROM ingredients WHERE id = ?', [$acucar]), 0.001);
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM productions'));
    }

    public function testVendaBaixaEstoqueEGuardaOCusto(): void
    {
        $leite = $this->ingredient('Leite', 'g', 0, 2);
        $brigadeiro = $this->recipe('Brigadeiro', 25, [['i', $leite, 395]], price: 250, stock: 50);

        $this->inventory()->sell($brigadeiro, 10, 2500, '2026-10-03', 'Festa da Ana');

        $this->assertEqualsWithDelta(40, (float) $this->value('SELECT stock FROM recipes WHERE id = ?', [$brigadeiro]), 0.001);
        $this->assertSame(316, (int) $this->value('SELECT cost_cents FROM sales')); // 31,6 × 10
    }

    public function testNaoVendeMaisDoQueTem(): void
    {
        $brigadeiro = $this->recipe('Brigadeiro', 25, [], stock: 5);
        $this->expectException(InventoryException::class);
        $this->expectExceptionMessage('Só há 5 un de Brigadeiro em estoque.');
        $this->inventory()->sell($brigadeiro, 6, 1500, '2026-10-03');
    }

    public function testEstoqueBaixo(): void
    {
        $this->ingredient('Leite', 'g', 100, 2, min: 500);
        $this->ingredient('Farinha', 'g', 900, 1, min: 500);
        $this->assertSame(['Leite'], array_column($this->inventory()->lowStock(), 'name'));
    }
}
