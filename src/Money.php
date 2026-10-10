<?php

declare(strict_types=1);

namespace Confeitaria;

/**
 * Dinheiro guardado em centavos (inteiro) para nunca ter erro de arredondamento
 * de ponto flutuante (0,1 + 0,2 != 0,3).
 */
final class Money
{
    /** "R$ 1.234,50" */
    public static function format(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Converte o que o usuário digitou em centavos.
     * Aceita "12", "12,5", "12,50", "1.234,50", "R$ 9,90" e "9.90".
     * Devolve null se não for um valor válido.
     */
    public static function parse(string $input): ?int
    {
        $value = trim(str_replace(['R$', ' '], '', $input));
        if ($value === '' || !preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+([.,]\d{1,2})?$/', $value)) {
            return null;
        }

        if (str_contains($value, ',')) {
            // Formato brasileiro: ponto é milhar, vírgula é decimal
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            // "1.234" sem vírgula: ponto como milhar
            $value = str_replace('.', '', $value);
        }

        return (int) round(((float) $value) * 100);
    }
}
