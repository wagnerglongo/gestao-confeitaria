<?php

declare(strict_types=1);

namespace Confeitaria;

use PDO;

/**
 * Conexão e estrutura do banco. Funciona com SQLite (desenvolvimento/testes)
 * e MySQL (produção). Quantidades ficam na unidade base do ingrediente
 * (g, ml ou un) e dinheiro em centavos.
 */
final class Database
{
    public static function connect(?string $dsn = null): PDO
    {
        $dsn ??= getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/database.sqlite';
        $pdo = new PDO($dsn, getenv('DB_USER') ?: null, getenv('DB_PASSWORD') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if (self::isSqlite($pdo)) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }

    public static function isSqlite(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public static function migrate(PDO $pdo): void
    {
        $sqlite = self::isSqlite($pdo);
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

        $tables = [
            // Ingredientes e embalagens. unit_cost = centavos por unidade base (média ponderada das compras)
            "ingredients (
                id {$id},
                name VARCHAR(100) NOT NULL,
                unit VARCHAR(4) NOT NULL,
                kind VARCHAR(12) NOT NULL DEFAULT 'ingrediente',
                stock DECIMAL(14,3) NOT NULL DEFAULT 0,
                unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
                min_stock DECIMAL(14,3) NOT NULL DEFAULT 0
            )",
            // Receita = produto. yield = quantas unidades uma fornada rende
            "recipes (
                id {$id},
                name VARCHAR(100) NOT NULL,
                yield DECIMAL(10,3) NOT NULL,
                yield_unit VARCHAR(12) NOT NULL DEFAULT 'un',
                price_cents INT NOT NULL DEFAULT 0,
                stock DECIMAL(14,3) NOT NULL DEFAULT 0,
                sellable TINYINT NOT NULL DEFAULT 1
            )",
            // Cada linha usa um ingrediente OU outra receita (sub-receita)
            "recipe_items (
                id {$id},
                recipe_id INT NOT NULL,
                ingredient_id INT NULL,
                sub_recipe_id INT NULL,
                quantity DECIMAL(14,3) NOT NULL,
                FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
                FOREIGN KEY (sub_recipe_id) REFERENCES recipes(id)
            )",
            "productions (
                id {$id},
                recipe_id INT NOT NULL,
                batches DECIMAL(10,3) NOT NULL,
                units DECIMAL(14,3) NOT NULL,
                cost_cents INT NOT NULL,
                produced_on DATE NOT NULL,
                FOREIGN KEY (recipe_id) REFERENCES recipes(id)
            )",
            "purchases (
                id {$id},
                ingredient_id INT NOT NULL,
                quantity DECIMAL(14,3) NOT NULL,
                total_cents INT NOT NULL,
                supplier VARCHAR(100),
                purchased_on DATE NOT NULL,
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
            )",
            "sales (
                id {$id},
                recipe_id INT NOT NULL,
                quantity DECIMAL(10,3) NOT NULL,
                total_cents INT NOT NULL,
                cost_cents INT NOT NULL,
                customer VARCHAR(100),
                sold_on DATE NOT NULL,
                FOREIGN KEY (recipe_id) REFERENCES recipes(id)
            )",
            "expenses (
                id {$id},
                description VARCHAR(120) NOT NULL,
                category VARCHAR(40) NOT NULL,
                total_cents INT NOT NULL,
                spent_on DATE NOT NULL
            )",
            "users (
                id {$id},
                email VARCHAR(160) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL
            )",
            "login_attempts (
                id {$id},
                ip VARCHAR(64) NOT NULL,
                attempted_at INT NOT NULL
            )",
        ];

        foreach ($tables as $table) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$table}{$engine}");
        }
    }
}
