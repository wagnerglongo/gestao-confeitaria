<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\App;
use Confeitaria\Service\Auth;

final class AppTest extends TestCase
{
    private function app(array &$session): App
    {
        return new App($this->pdo, $session, dirname(__DIR__, 2) . '/templates', '2026-10-10');
    }

    public function testTudoExigeLogin(): void
    {
        $session = [];
        foreach (['/', '/receitas', '/caixa.csv'] as $path) {
            [$status, $headers] = $this->app($session)->handle('GET', $path);
            $this->assertSame(303, $status, $path);
            $this->assertSame('/login', $headers['Location']);
        }
    }

    public function testLoginEPostSemCsrf(): void
    {
        (new Auth($this->pdo))->createUser('a@b.com', 'certa');
        $session = [];
        $app = $this->app($session);
        $app->handle('GET', '/login');
        [$status] = $app->handle('POST', '/login', [], ['_csrf' => $session['csrf'], 'email' => 'a@b.com', 'password' => 'certa']);
        $this->assertSame(303, $status);
        [$status] = $app->handle('POST', '/despesas', [], ['description' => 'x', 'total' => '1']);
        $this->assertSame(419, $status);
    }

    public function testSalvarReceitaQueCriariaCicloERecusado(): void
    {
        $leite = $this->ingredient('Leite', 'g', 0, 2);
        $massa = $this->recipe('Massa', 1000, [['i', $leite, 500]]);
        $bolo = $this->recipe('Bolo', 8, [['r', $massa, 800]]);

        $session = ['user_id' => 1, 'csrf' => 't'];
        [$status, , $body] = $this->app($session)->handle('POST', '/receitas/' . $massa, [], [
            '_csrf' => 't', 'name' => 'Massa', 'yield' => '1000', 'price' => '0',
            'item' => ["i:{$leite}", "r:{$bolo}"], 'quantity' => ['500', '1'],
        ]);
        $this->assertSame(200, $status);
        $this->assertStringContainsString('receita circular', $body);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM recipe_items WHERE recipe_id = ?', [$massa]), 'nada foi gravado');
    }

    public function testExportaCsv(): void
    {
        $session = ['user_id' => 1];
        [$status, $headers, $body] = $this->app($session)->handle('GET', '/caixa.csv', ['de' => '2026-10-01', 'ate' => '2026-10-31']);
        $this->assertSame(200, $status);
        $this->assertStringContainsString('text/csv', $headers['Content-Type']);
        $this->assertStringContainsString('fluxo-de-caixa-2026-10-01-a-2026-10-31.csv', $headers['Content-Disposition']);
        $this->assertStringContainsString('Data;Tipo', $body);
    }

    public function testNumerosNoFormatoBrasileiro(): void
    {
        $this->assertSame(1500.5, App::number('1.500,5'));
        $this->assertSame(1500.5, App::number('1500.5'));
        $this->assertSame(0.5, App::number('0,5'));
        $this->assertNull(App::number('abc'));
        $this->assertNull(App::number(''));
    }
}
