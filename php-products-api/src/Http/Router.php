<?php

declare(strict_types=1);

namespace ProductsApi\Http;

/**
 * Tiny router: method + pattern, "{id}" placeholders match positive integers.
 * Unknown paths give 404, known paths with another method give 405 with an Allow header.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: \Closure, auth: bool}> */
    private array $routes = [];

    public function add(string $method, string $pattern, \Closure $handler, bool $auth = false): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[1-9]\d{0,17})', $pattern) . '$#',
            'handler' => $handler,
            'auth' => $auth,
        ];
    }

    /** @return list<string> "METHOD /path" for every route, e.g. to compare with the OpenAPI file */
    public function describe(): array
    {
        return array_map(static fn (array $r): string => $r['method'] . ' ' . $r['pattern'], $this->routes);
    }

    /**
     * @return array{handler: \Closure, params: array<string, int>, auth: bool}
     * @throws ApiException
     */
    public function match(Request $request): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $m) !== 1) {
                continue;
            }
            $method = $request->method === 'HEAD' ? 'GET' : $request->method;
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));

            return ['handler' => $route['handler'], 'params' => $params, 'auth' => $route['auth']];
        }

        if ($allowed !== []) {
            throw new ApiException(405, 'method_not_allowed', "Method {$request->method} is not allowed here.", [], [
                'Allow' => implode(', ', array_unique($allowed)),
            ]);
        }
        throw new ApiException(404, 'not_found', 'No route for ' . $request->path . '.');
    }
}
