<?php

declare(strict_types=1);

namespace Confeitaria\Service;

/**
 * Token CSRF por sessão: todo formulário do painel envia o token e
 * o servidor recusa POSTs sem ele. Impede que outro site faça ações
 * no painel em nome de quem está logado.
 */
final class Csrf
{
    /** @param array<string, mixed> $session */
    public static function token(array &$session): string
    {
        if (empty($session['csrf'])) {
            $session['csrf'] = bin2hex(random_bytes(32));
        }
        return $session['csrf'];
    }

    /** @param array<string, mixed> $session */
    public static function check(array $session, ?string $sent): bool
    {
        return !empty($session['csrf']) && is_string($sent) && hash_equals($session['csrf'], $sent);
    }
}
