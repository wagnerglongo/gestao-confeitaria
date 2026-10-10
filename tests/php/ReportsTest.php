<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\Domain\Reports;

final class ReportsTest extends TestCase
{
    private function seed(): void
    {
        $leite = $this->ingredient('Leite', 'g', 0, 2);
        $brigadeiro = $this->recipe('Brigadeiro', 25, [['i', $leite, 395]], stock: 100);
        $this->inventory()->purchase($leite, 1000, 2000, '2026-10-01');
        $this->inventory()->sell($brigadeiro, 20, 5000, '2026-10-02');
        $this->pdo->exec("INSERT INTO expenses (description, category, total_cents, spent_on) VALUES ('Gás', 'Operacional', 1200, '2026-10-03')");
        $this->pdo->exec("INSERT INTO expenses (description, category, total_cents, spent_on) VALUES ('Fora do período', 'Outros', 999, '2026-11-01')");
    }

    public function testFluxoDeCaixaComSaldoAcumulado(): void
    {
        $this->seed();
        $flow = (new Reports($this->pdo))->cashFlow('2026-10-01', '2026-10-31');

        $this->assertSame(5000, $flow['income']);
        $this->assertSame(3200, $flow['outcome']);
        $this->assertSame(1800, $flow['balance']);
        $this->assertSame([-2000, 3000, 1800], array_column($flow['rows'], 'balance'));
        $this->assertSame('Brigadeiro (20)', $flow['rows'][1]['description']);
    }

    public function testResumoComLucro(): void
    {
        $this->seed();
        $summary = (new Reports($this->pdo))->summary('2026-10-01', '2026-10-31');
        $this->assertSame(5000, $summary['revenue']);
        $this->assertSame(632, $summary['cost']);        // 20 × 31,6
        $this->assertSame(4368, $summary['gross_profit']);
        $this->assertSame(3168, $summary['net_profit']);  // menos despesas de 1200
    }

    public function testFaturamentoDiarioPreencheDiasSemVenda(): void
    {
        $this->seed();
        $days = (new Reports($this->pdo))->dailyRevenue('2026-10-01', '2026-10-03');
        $this->assertSame(['2026-10-01' => 0, '2026-10-02' => 5000, '2026-10-03' => 0], $days);
    }

    public function testCsvParaExcelEmPortuguesESemInjecaoDeFormula(): void
    {
        $this->pdo->exec("INSERT INTO expenses (description, category, total_cents, spent_on) VALUES ('=HYPERLINK(\"http://x\")', 'Outros', 100, '2026-10-05')");
        $csv = (new Reports($this->pdo))->cashFlowCsv('2026-10-01', '2026-10-31');

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM para acentos no Excel');
        $this->assertStringContainsString('Data;Tipo;Categoria', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('-1,00', $csv);
    }
}
