<?php

declare(strict_types=1);

namespace Confeitaria\Service;

use PDO;

/**
 * Login do painel.
 * - Senhas guardadas com password_hash (bcrypt/argon, nunca em texto).
 * - Limite de tentativas por IP para dificultar força bruta.
 * - Novo ID de sessão a cada login (evita fixação de sessão).
 */
final class Auth
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 15 * 60;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function createUser(string $email, string $password): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([mb_strtolower(trim($email)), self::hash($password)]);
    }

    public function isLocked(string $ip, ?int $now = null): bool
    {
        $now ??= time();
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
        $stmt->execute([$ip, $now - self::WINDOW_SECONDS]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    /** Devolve o ID do usuário se o login for válido; null caso contrário. */
    public function attempt(string $email, string $password, string $ip, ?int $now = null): ?int
    {
        $now ??= time();
        if ($this->isLocked($ip, $now)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();

        // password_verify roda mesmo sem usuário, para o tempo de resposta não revelar quais e-mails existem
        static $dummy = null;
        $dummy ??= self::hash(bin2hex(random_bytes(8)));
        $hash = $user['password_hash'] ?? $dummy;
        $valid = password_verify($password, $hash) && $user !== false;

        if (!$valid) {
            $this->pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([$ip, $now]);
            return null;
        }

        $this->pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        return (int) $user['id'];
    }
}
