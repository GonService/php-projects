<?php

declare(strict_types=1);

namespace Modernized;

use DateTimeImmutable;
use Modernized\Customers\CustomerRepository;
use Modernized\Customers\CustomerService;
use Modernized\Customers\ValidationException;
use Modernized\Http\HttpException;
use Modernized\Http\Request;
use Modernized\Http\Response;
use Modernized\Http\Router;
use Modernized\Support\Csrf;
use Modernized\Support\Session;
use Modernized\Support\View;
use PDO;

/**
 * The customers page after refactoring: the same feature as before/clientes.php (search, add,
 * delete), split into routing (here), business rules (CustomerService), SQL (CustomerRepository)
 * and HTML (templates/).
 */
final class App
{
    private readonly CustomerService $service;
    private readonly Csrf $csrf;
    private readonly View $view;
    private readonly Router $router;

    /** @param \Closure(): DateTimeImmutable|null $clock */
    public function __construct(PDO $pdo, private readonly Session $session, ?\Closure $clock = null)
    {
        $clock ??= static fn () => new DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
        $this->service = new CustomerService(new CustomerRepository($pdo), $clock);
        $this->csrf = new Csrf($session);
        $this->view = new View(dirname(__DIR__) . '/templates');
        $this->router = $this->routes();
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->path === '/healthz') {
                return Response::text("ok\n");
            }
            // Every state-changing request must carry the token from our own form.
            if ($request->method === 'POST' && !$this->csrf->isValid($request->body[Csrf::FIELD] ?? null)) {
                throw new HttpException(419, 'The form expired or did not come from this site. Reload the page and try again.');
            }
            $this->view->share('csrfToken', $this->csrf->token());

            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return Response::html($this->view->render('error', ['status' => $e->status, 'message' => $e->getMessage()]), $e->status);
        } catch (\Throwable $e) {
            // Log the details for us; show nothing internal (no SQL, no paths) to the visitor.
            error_log('Customers page: ' . $e);

            return Response::html($this->view->render('error', ['status' => 500, 'message' => 'Something went wrong. Please try again.']), 500);
        }
    }

    private function routes(): Router
    {
        $r = new Router();

        $r->get('/', fn (Request $req) => $this->page($req->query('q')));

        $r->post('/customers', function (Request $req): Response {
            try {
                $customer = $this->service->register($req->body);
            } catch (ValidationException $e) {
                return $this->page('', $e->input, $e->errors, 422);
            }
            $this->session->set('flash', "Customer {$customer->name} added.");

            return Response::redirect('/');
        });

        // Deleting changes data, so it is a POST with a CSRF token, never a GET link.
        $r->post('/customers/{id}/delete', function (Request $req, int $id): Response {
            if (!$this->service->remove($id)) {
                throw new HttpException(404, 'Customer not found.');
            }
            $this->session->set('flash', 'Customer deleted.');

            return Response::redirect('/');
        });

        return $r;
    }

    /**
     * @param array<string, string> $old    form values to show again
     * @param array<string, string> $errors field => message
     */
    private function page(string $term, array $old = [], array $errors = [], int $status = 200): Response
    {
        $flash = $this->session->get('flash');
        $this->session->remove('flash');

        return Response::html($this->view->render('customers', [
            'term' => mb_substr($term, 0, 100),
            'customers' => $this->service->search($term),
            'old' => $old,
            'errors' => $errors,
            'flash' => is_string($flash) ? $flash : null,
        ]), $status);
    }
}
