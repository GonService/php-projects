<?php

declare(strict_types=1);

namespace MiniCrm\Http;

/** Minimal router: "/customers/{id}" placeholders match digits only. */
final class Router
{
    /** @var list<array{string, string, \Closure}> */
    private array $routes = [];

    public function get(string $pattern, \Closure $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, \Closure $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(Request $request): Response
    {
        $methodAllowed = false;
        foreach ($this->routes as [$method, $pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
            if (preg_match($regex, $request->path, $m) !== 1) {
                continue;
            }
            if ($method !== $request->method) {
                $methodAllowed = true;
                continue;
            }
            $params = array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));

            return $handler($request, ...$params);
        }

        throw $methodAllowed
            ? new HttpException(405, 'Method not allowed')
            : new HttpException(404, 'Page not found');
    }
}
