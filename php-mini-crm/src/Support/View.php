<?php

declare(strict_types=1);

namespace MiniCrm\Support;

/**
 * Plain PHP templates. Templates run inside this object, so they call $this->e() to escape
 * every dynamic value and $this->csrf() to print the hidden token field.
 */
final class View
{
    /** @param array<string, mixed> $shared values available to every template */
    public function __construct(private readonly string $directory, private array $shared = [])
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $data += $this->shared;
        $content = $this->capture($template, $data);

        return $layout === null ? $content : $this->capture($layout, ['content' => $content] + $data);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function csrf(): string
    {
        $token = $this->shared['csrfToken'] ?? '';

        return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . $this->e($token) . '">';
    }

    public function date(string $value, string $format = 'd/m/Y'): string
    {
        return $this->e((new \DateTimeImmutable($value))->format($format));
    }

    /** @param array<string, mixed> $data */
    private function capture(string $template, array $data): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
