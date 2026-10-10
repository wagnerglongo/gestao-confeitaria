<?php

declare(strict_types=1);

namespace Confeitaria\Domain;

final class RecipeCycleException extends \DomainException
{
    public function __construct(string $path)
    {
        parent::__construct("Receita circular: {$path}. Uma receita não pode usar a si mesma.");
    }
}
