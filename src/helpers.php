<?php

declare(strict_types=1);

/** Escapa texto para HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Centavos → "R$ 12,90". */
function money(int|float $cents): string
{
    return \Confeitaria\Money::format((int) round($cents));
}

/** 1500.5 → "1.500,5" */
function qty(int|float|string $value): string
{
    return \Confeitaria\Domain\Inventory::qty((float) $value);
}

/** "2026-10-10" → "10/10/2026" */
function br_date(string $date): string
{
    return date('d/m/Y', strtotime($date));
}
