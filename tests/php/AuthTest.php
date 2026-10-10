<?php

declare(strict_types=1);

namespace Confeitaria\Tests;

use Confeitaria\Service\Auth;

final class AuthTest extends TestCase
{
    public function testLoginComSenhaCorretaEIncorreta(): void
    {
        $auth = new Auth($this->pdo);
        $auth->createUser('Dono@Exemplo.com', 'segredo-forte');

        $this->assertNotNull($auth->attempt('dono@exemplo.com', 'segredo-forte', '1.1.1.1'));
        $this->assertNull($auth->attempt('dono@exemplo.com', 'errada', '1.1.1.1'));
        $this->assertNull($auth->attempt('ninguem@exemplo.com', 'segredo-forte', '1.1.1.1'));
    }

    public function testSenhaNaoFicaEmTextoNoBanco(): void
    {
        $pdo = $this->pdo;
        (new Auth($pdo))->createUser('a@b.com', 'segredo-forte');
        $hash = $pdo->query('SELECT password_hash FROM users')->fetchColumn();
        $this->assertStringNotContainsString('segredo-forte', $hash);
        $this->assertTrue(password_verify('segredo-forte', $hash));
    }

    public function testBloqueiaDepoisDeCincoErrosEDesbloqueiaComOTempo(): void
    {
        $auth = new Auth($this->pdo);
        $auth->createUser('a@b.com', 'certa');
        $now = 1_700_000_000;

        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            $auth->attempt('a@b.com', 'errada', '9.9.9.9', $now);
        }
        $this->assertTrue($auth->isLocked('9.9.9.9', $now));
        $this->assertNull($auth->attempt('a@b.com', 'certa', '9.9.9.9', $now), 'bloqueado mesmo com a senha certa');
        $this->assertFalse($auth->isLocked('8.8.8.8', $now), 'outro IP não é afetado');
        $this->assertNotNull($auth->attempt('a@b.com', 'certa', '9.9.9.9', $now + Auth::WINDOW_SECONDS + 1));
    }
}
