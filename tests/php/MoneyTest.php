<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\Money;

final class MoneyTest extends TestCase
{
    public function testFormataEmReais(): void
    {
        $this->assertSame('R$ 0,90', Money::format(90));
        $this->assertSame('R$ 1.234,50', Money::format(123450));
    }

    public function testInterpretaOQueOUsuarioDigita(): void
    {
        $this->assertSame(1200, Money::parse('12'));
        $this->assertSame(1250, Money::parse('12,5'));
        $this->assertSame(2490, Money::parse('24,90'));
        $this->assertSame(990, Money::parse('R$ 9,90'));
        $this->assertSame(990, Money::parse('9.90'));
        $this->assertSame(123450, Money::parse('1.234,50'));
        $this->assertSame(123400, Money::parse('1.234'));
    }

    public function testRecusaValoresInvalidos(): void
    {
        foreach (['', 'abc', '12,345', '1,2,3', '-5', '12.34.56,7'] as $input) {
            $this->assertNull(Money::parse($input), $input);
        }
    }

    public function testNaoTemErroDeArredondamento(): void
    {
        // Em float, 0.1 + 0.2 = 0.30000000000000004; em centavos a conta fecha
        $this->assertSame(Money::parse('0,30'), Money::parse('0,10') + Money::parse('0,20'));
        $this->assertSame(1990, Money::parse('19,90'));
    }
}
