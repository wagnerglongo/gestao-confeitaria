<?php

declare(strict_types=1);

namespace Confeitaria\Domain;

use PDO;

/**
 * Relatórios: fluxo de caixa do período e indicadores do painel.
 * Entradas = vendas. Saídas = compras de ingredientes + despesas.
 */
final class Reports
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Movimentações do período, ordenadas por data, com saldo acumulado.
     * @return array{rows: list<array<string, mixed>>, income: int, outcome: int, balance: int}
     */
    public function cashFlow(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.sold_on AS day, 'entrada' AS type, 'Venda' AS category,
                    r.name AS description, s.quantity AS qty, s.total_cents AS cents
             FROM sales s JOIN recipes r ON r.id = s.recipe_id WHERE s.sold_on BETWEEN :from1 AND :to1
             UNION ALL
             SELECT p.purchased_on, 'saida', 'Compra', i.name, NULL, p.total_cents
             FROM purchases p JOIN ingredients i ON i.id = p.ingredient_id WHERE p.purchased_on BETWEEN :from2 AND :to2
             UNION ALL
             SELECT e.spent_on, 'saida', e.category, e.description, NULL, e.total_cents
             FROM expenses e WHERE e.spent_on BETWEEN :from3 AND :to3
             ORDER BY day, type"
        );
        $stmt->execute(['from1' => $from, 'to1' => $to, 'from2' => $from, 'to2' => $to, 'from3' => $from, 'to3' => $to]);

        $balance = 0;
        $income = 0;
        $outcome = 0;
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $cents = (int) $row['cents'];
            if ($row['type'] === 'entrada') {
                $income += $cents;
                $balance += $cents;
            } else {
                $outcome += $cents;
                $balance -= $cents;
            }
            if ($row['qty'] !== null) {
                $row['description'] .= ' (' . Inventory::qty((float) $row['qty']) . ')';
            }
            unset($row['qty']);
            $rows[] = $row + ['balance' => $balance];
        }

        return ['rows' => $rows, 'income' => $income, 'outcome' => $outcome, 'balance' => $income - $outcome];
    }

    /** Faturamento, custo dos produtos vendidos e lucro bruto do período. */
    public function summary(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(total_cents),0) AS revenue, COALESCE(SUM(cost_cents),0) AS cost, COUNT(*) AS orders FROM sales WHERE sold_on BETWEEN ? AND ?');
        $stmt->execute([$from, $to]);
        $sales = $stmt->fetch();

        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(total_cents),0) FROM expenses WHERE spent_on BETWEEN ? AND ?');
        $stmt->execute([$from, $to]);
        $expenses = (int) $stmt->fetchColumn();

        $revenue = (int) $sales['revenue'];
        $cost = (int) $sales['cost'];
        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'gross_profit' => $revenue - $cost,
            'expenses' => $expenses,
            'net_profit' => $revenue - $cost - $expenses,
            'orders' => (int) $sales['orders'],
            'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
        ];
    }

    /** Faturamento por dia, preenchendo dias sem venda com zero (para o gráfico). */
    public function dailyRevenue(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT sold_on, SUM(total_cents) AS cents FROM sales WHERE sold_on BETWEEN ? AND ? GROUP BY sold_on');
        $stmt->execute([$from, $to]);
        $byDay = array_column($stmt->fetchAll(), 'cents', 'sold_on');

        $days = [];
        for ($day = new \DateTimeImmutable($from); $day <= new \DateTimeImmutable($to); $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $days[$key] = (int) ($byDay[$key] ?? 0);
        }
        return $days;
    }

    /** Produtos mais vendidos no período. */
    public function topProducts(string $from, string $to, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.name, SUM(s.quantity) AS quantity, SUM(s.total_cents) AS revenue, SUM(s.total_cents - s.cost_cents) AS profit
             FROM sales s JOIN recipes r ON r.id = s.recipe_id
             WHERE s.sold_on BETWEEN ? AND ?
             GROUP BY r.id, r.name ORDER BY revenue DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    /** CSV do fluxo de caixa (separador ";" e BOM, para abrir certo no Excel em português). */
    public function cashFlowCsv(string $from, string $to): string
    {
        $flow = $this->cashFlow($from, $to);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Data', 'Tipo', 'Categoria', 'Descrição', 'Valor (R$)', 'Saldo (R$)'], ';');
        foreach ($flow['rows'] as $row) {
            $sign = $row['type'] === 'entrada' ? 1 : -1;
            fputcsv($out, [
                date('d/m/Y', strtotime($row['day'])),
                $row['type'],
                $row['category'],
                self::safeCsv($row['description']),
                number_format($sign * $row['cents'] / 100, 2, ',', ''),
                number_format($row['balance'] / 100, 2, ',', ''),
            ], ';');
        }
        rewind($out);
        return stream_get_contents($out);
    }

    /**
     * Evita "injeção de fórmula" no Excel: um texto começando com = + - @
     * seria executado como fórmula ao abrir a planilha.
     */
    public static function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
