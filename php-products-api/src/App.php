<?php

declare(strict_types=1);

namespace ProductsApi;

use DateTimeImmutable;
use PDO;
use ProductsApi\Domain\ApiKeys;
use ProductsApi\Domain\CategoryRepository;
use ProductsApi\Domain\CategoryValidator;
use ProductsApi\Domain\ListQuery;
use ProductsApi\Domain\Presenter;
use ProductsApi\Domain\ProductRepository;
use ProductsApi\Domain\ProductValidator;
use ProductsApi\Http\ApiException;
use ProductsApi\Http\Request;
use ProductsApi\Http\Response;
use ProductsApi\Http\Router;

final class App
{
    private readonly ProductRepository $products;
    private readonly CategoryRepository $categories;
    private readonly ApiKeys $keys;
    private readonly Router $router;

    /** @param \Closure(): DateTimeImmutable|null $clock */
    public function __construct(
        PDO $pdo,
        private readonly ?\Closure $clock = null,
        private readonly bool $debug = false,
    ) {
        $this->products = new ProductRepository($pdo);
        $this->categories = new CategoryRepository($pdo);
        $this->keys = new ApiKeys($pdo);
        $this->router = $this->routes();
    }

    /** @return list<string> */
    public function routeList(): array
    {
        return $this->router->describe();
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->path === '/' || $request->path === '/openapi') {
                return $this->docs($request);
            }
            $route = $this->router->match($request);
            if ($route['auth']) {
                $this->authenticate($request);
            }
            $response = ($route['handler'])($request, ...$route['params']);

            return $request->method === 'GET' || $request->method === 'HEAD'
                ? $this->withEtag($request, $response)
                : $response;
        } catch (ApiException $e) {
            return $this->error($e->status, $e->errorCode, $e->getMessage(), $e->details, $e->headers);
        } catch (\Throwable $e) {
            error_log('Products API: ' . $e);

            return $this->error(500, 'server_error', $this->debug ? $e->getMessage() : 'Something went wrong on our side.');
        }
    }

    private function routes(): Router
    {
        $r = new Router();

        $r->add('GET', '/api/health', fn () => Response::json(['status' => 'ok']));

        // ---- Products ----
        $r->add('GET', '/api/products', function (Request $req): Response {
            $query = ListQuery::fromQuery($req->query, ProductRepository::FILTERS, ProductRepository::SORTABLE, 'name');
            $page = $this->products->paginate($query);

            return $this->collection($req, $query, $page, Presenter::product(...));
        });

        $r->add('GET', '/api/products/{id}', fn (Request $req, int $id) => Response::json(['data' => Presenter::product($this->product($id))]));

        $r->add('POST', '/api/products', function (Request $req): Response {
            $data = $this->productValidator()->validate($req->json());
            $id = $this->products->create($data, $this->now());

            return Response::json(['data' => Presenter::product($this->product($id))], 201, ['Location' => "/api/products/{$id}"]);
        }, auth: true);

        foreach (['PUT' => false, 'PATCH' => true] as $method => $partial) {
            $r->add($method, '/api/products/{id}', function (Request $req, int $id) use ($partial): Response {
                $current = $this->product($id);
                $this->checkIfMatch($req, ['data' => Presenter::product($current)]);
                $data = $this->productValidator()->validate($req->json(), $current, $partial);
                $this->products->update($id, $data, $this->now());

                return $this->withEtag($req, Response::json(['data' => Presenter::product($this->product($id))]));
            }, auth: true);
        }

        $r->add('DELETE', '/api/products/{id}', function (Request $req, int $id): Response {
            $this->checkIfMatch($req, ['data' => Presenter::product($this->product($id))]);
            $this->products->delete($id);

            return Response::noContent();
        }, auth: true);

        // ---- Categories ----
        $r->add('GET', '/api/categories', function (Request $req): Response {
            $query = ListQuery::fromQuery($req->query, ['q' => 'string'], CategoryRepository::SORTABLE, 'name');

            return $this->collection($req, $query, $this->categories->paginate($query), Presenter::category(...));
        });

        $r->add('GET', '/api/categories/{id}', fn (Request $req, int $id) => Response::json(['data' => Presenter::category($this->category($id))]));

        $r->add('POST', '/api/categories', function (Request $req): Response {
            $data = (new CategoryValidator($this->categories))->validate($req->json());
            $id = $this->categories->create($data, $this->now());

            return Response::json(['data' => Presenter::category($this->category($id))], 201, ['Location' => "/api/categories/{$id}"]);
        }, auth: true);

        foreach (['PUT' => false, 'PATCH' => true] as $method => $partial) {
            $r->add($method, '/api/categories/{id}', function (Request $req, int $id) use ($partial): Response {
                $current = $this->category($id);
                $data = (new CategoryValidator($this->categories))->validate($req->json(), $current, $partial);
                $this->categories->update($id, $data, $this->now());

                return Response::json(['data' => Presenter::category($this->category($id))]);
            }, auth: true);
        }

        $r->add('DELETE', '/api/categories/{id}', function (Request $req, int $id): Response {
            $category = $this->category($id);
            if ((int) $category['product_count'] > 0) {
                throw new ApiException(409, 'category_in_use', 'Move or delete the products of this category first.');
            }
            $this->categories->delete($id);

            return Response::noContent();
        }, auth: true);

        return $r;
    }

    /**
     * Standard list document: data + pagination meta + links.
     *
     * @param array{items: list<array<string, mixed>>, total: int} $page
     */
    private function collection(Request $req, ListQuery $query, array $page, \Closure $present): Response
    {
        $totalPages = max(1, (int) ceil($page['total'] / $query->perPage));
        if ($query->page > $totalPages && $page['total'] > 0) {
            throw new ApiException(422, 'invalid_query', 'Invalid query parameters.', ['page' => ["The last page is {$totalPages}."]]);
        }
        $link = function (int $n) use ($req): string {
            $params = array_merge(array_filter($req->query, 'is_string'), ['page' => (string) $n]);

            return $req->path . '?' . http_build_query($params);
        };

        return Response::json([
            'data' => array_map($present, $page['items']),
            'meta' => [
                'page' => $query->page,
                'per_page' => $query->perPage,
                'total' => $page['total'],
                'total_pages' => $totalPages,
            ],
            'links' => [
                'self' => $link($query->page),
                'first' => $link(1),
                'last' => $link($totalPages),
                'prev' => $query->page > 1 ? $link($query->page - 1) : null,
                'next' => $query->page < $totalPages ? $link($query->page + 1) : null,
            ],
        ]);
    }

    /** Strong ETag from the body; answers 304 when the client already has this version. */
    private function withEtag(Request $req, Response $response): Response
    {
        if ($response->status !== 200) {
            return $response;
        }
        $etag = '"' . hash('sha256', $response->body) . '"';
        if ($req->method === 'GET' && self::etagMatches($req->header('if-none-match'), $etag)) {
            return new Response('', 304, ['ETag' => $etag]);
        }

        return $response->withHeaders(['ETag' => $etag]);
    }

    /**
     * Optimistic locking: with If-Match, writes only apply to the version the client last read.
     *
     * @param array<string, mixed> $currentDocument the GET representation of the resource
     */
    private function checkIfMatch(Request $req, array $currentDocument): void
    {
        $ifMatch = $req->header('if-match');
        if ($ifMatch === null) {
            return;
        }
        $etag = '"' . hash('sha256', Response::json($currentDocument)->body) . '"';
        if (!self::etagMatches($ifMatch, $etag)) {
            throw new ApiException(412, 'precondition_failed', 'The resource changed since you read it. GET it again and retry.');
        }
    }

    private static function etagMatches(?string $header, string $etag): bool
    {
        if ($header === null) {
            return false;
        }
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
                return true;
            }
        }

        return false;
    }

    private function authenticate(Request $req): void
    {
        $key = $req->header('x-api-key');
        $auth = $req->header('authorization');
        if ($key === null && $auth !== null && preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) === 1) {
            $key = $m[1];
        }
        if ($key === null || $this->keys->verify($key, $this->now()) === null) {
            throw new ApiException(
                401,
                'unauthorized',
                $key === null ? 'This route needs an API key in the X-API-Key header.' : 'Invalid or revoked API key.',
                [],
                ['WWW-Authenticate' => 'Bearer realm="products-api"'],
            );
        }
    }

    /**
     * @param array<string, list<string>> $details
     * @param array<string, string>       $headers
     */
    private function error(int $status, string $code, string $message, array $details = [], array $headers = []): Response
    {
        $error = ['status' => $status, 'code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return Response::json(['error' => $error], $status, $headers);
    }

    private function docs(Request $req): Response
    {
        if ($req->method !== 'GET' && $req->method !== 'HEAD') {
            throw new ApiException(405, 'method_not_allowed', 'Only GET is allowed here.', [], ['Allow' => 'GET']);
        }
        $root = dirname(__DIR__);
        if ($req->path === '/openapi') {
            return new Response((string) file_get_contents($root . '/docs/openapi.yaml'), 200, ['Content-Type' => 'application/yaml; charset=utf-8']);
        }
        $sample = $this->handle(new Request('GET', '/api/products', ['per_page' => '1', 'sort' => '-price_cents']));
        $sampleJson = json_encode(json_decode($sample->body), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        require $root . '/templates/docs.php';

        return new Response((string) ob_get_clean(), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'self'",
        ]);
    }

    /** @return array<string, mixed> */
    private function product(int $id): array
    {
        return $this->products->find($id) ?? throw ApiException::notFound('Product');
    }

    /** @return array<string, mixed> */
    private function category(int $id): array
    {
        return $this->categories->find($id) ?? throw ApiException::notFound('Category');
    }

    private function productValidator(): ProductValidator
    {
        return new ProductValidator($this->products, $this->categories);
    }

    private function now(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
