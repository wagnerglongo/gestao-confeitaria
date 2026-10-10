<?php

declare(strict_types=1);

namespace Confeitaria;

/**
 * Renderiza templates PHP. Todo texto vindo do banco passa por e(),
 * que escapa HTML, então um nome de produto como "<script>" aparece como texto.
 */
final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = $this->capture($template, $data);
        return $layout ? $this->capture($layout, $data + ['content' => $content]) : $content;
    }

    /** @param array<string, mixed> $data */
    private function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->directory . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
