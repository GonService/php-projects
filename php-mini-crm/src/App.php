<?php

declare(strict_types=1);

namespace MiniCrm;

use DateTimeImmutable;
use MiniCrm\Domain\CustomerFilter;
use MiniCrm\Domain\CustomerRepository;
use MiniCrm\Domain\CustomerStatus;
use MiniCrm\Domain\CustomerValidator;
use MiniCrm\Domain\InteractionRepository;
use MiniCrm\Domain\InteractionType;
use MiniCrm\Domain\ReminderRepository;
use MiniCrm\Domain\UserRepository;
use MiniCrm\Http\HttpException;
use MiniCrm\Http\Request;
use MiniCrm\Http\Response;
use MiniCrm\Http\Router;
use MiniCrm\Support\Csrf;
use MiniCrm\Support\Session;
use MiniCrm\Support\View;
use PDO;

/**
 * The whole web application: routes, authentication and CSRF checks around plain handlers.
 * Built with explicit dependencies so tests can drive it with an in-memory database.
 */
final class App
{
    private readonly CustomerRepository $customers;
    private readonly InteractionRepository $interactions;
    private readonly ReminderRepository $reminders;
    private readonly UserRepository $users;
    private readonly Csrf $csrf;
    private readonly View $view;
    private readonly Router $router;

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    /** @param \Closure(): DateTimeImmutable|null $clock */
    public function __construct(
        PDO $pdo,
        private readonly Session $session,
        private readonly ?\Closure $clock = null,
    ) {
        $this->customers = new CustomerRepository($pdo);
        $this->interactions = new InteractionRepository($pdo);
        $this->reminders = new ReminderRepository($pdo);
        $this->users = new UserRepository($pdo);
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

            $userId = $this->session->get('user_id');
            $this->user = is_int($userId) ? $this->users->find($userId) : null;

            $public = in_array($request->path, ['/login'], true);
            if ($this->user === null && !$public) {
                return Response::redirect('/login');
            }
            if ($request->method === 'POST' && !$this->csrf->isValid($request->body[Csrf::FIELD] ?? null)) {
                throw new HttpException(419, 'Your session expired. Go back, reload the page and try again.');
            }

            $this->view->share('csrfToken', $this->csrf->token());
            $this->view->share('user', $this->user);
            $this->view->share('path', $request->path);
            $this->view->share('flash', $this->takeFlash());

            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return Response::html(
                $this->view->render('error', ['status' => $e->status, 'message' => $e->getMessage()], null),
                $e->status,
            );
        }
    }

    private function routes(): Router
    {
        $r = new Router();

        $r->get('/login', fn (Request $req) => $this->user !== null
            ? Response::redirect('/')
            : Response::html($this->view->render('login', ['email' => '', 'error' => null], null)));

        $r->post('/login', function (Request $req): Response {
            $user = $this->users->authenticate($req->input('email'), $req->input('password'));
            if ($user === null) {
                return Response::html($this->view->render('login', [
                    'email' => $req->input('email'),
                    'error' => 'Invalid e-mail or password.',
                ], null), 422);
            }
            $this->session->regenerate();
            $this->session->set('user_id', (int) $user['id']);

            return Response::redirect('/');
        });

        $r->post('/logout', function (): Response {
            $this->session->remove('user_id');
            $this->session->regenerate();

            return Response::redirect('/login');
        });

        $r->get('/', fn () => Response::html($this->view->render('dashboard', [
            'today' => $this->today(),
            'statusCounts' => $this->customers->countByStatus(),
            'reminderCounts' => $this->reminders->counts($this->today()),
            'followUps' => $this->reminders->open($this->today(), 8),
            'recent' => $this->interactions->recent(6),
        ])));

        $r->get('/customers', function (Request $req): Response {
            $filter = CustomerFilter::fromQuery($req->query);

            return Response::html($this->view->render('customers/index', [
                'filter' => $filter,
                'customers' => $this->customers->search($filter, $this->today()),
            ]));
        });

        $r->get('/customers/new', fn () => $this->customerForm(null, ['status' => CustomerStatus::Lead->value], []));

        $r->post('/customers', function (Request $req): Response {
            $result = (new CustomerValidator($this->customers))->validate($req->body);
            if ($result['errors'] !== []) {
                return $this->customerForm(null, $result['data'], $result['errors'], 422);
            }
            $id = $this->customers->create($result['data']);
            $this->flash('Customer created.');

            return Response::redirect("/customers/{$id}");
        });

        $r->get('/customers/{id}', function (Request $req, int $id): Response {
            $customer = $this->findCustomer($id);

            return Response::html($this->view->render('customers/show', [
                'customer' => $customer,
                'today' => $this->today(),
                'interactions' => $this->interactions->forCustomer($id),
                'reminders' => $this->reminders->forCustomer($id, $this->today()),
                'types' => InteractionType::cases(),
            ]));
        });

        $r->get('/customers/{id}/edit', fn (Request $req, int $id) => $this->customerForm($id, $this->findCustomer($id), []));

        $r->post('/customers/{id}', function (Request $req, int $id): Response {
            $this->findCustomer($id);
            $result = (new CustomerValidator($this->customers))->validate($req->body, $id);
            if ($result['errors'] !== []) {
                return $this->customerForm($id, $result['data'], $result['errors'], 422);
            }
            $this->customers->update($id, $result['data']);
            $this->flash('Customer updated.');

            return Response::redirect("/customers/{$id}");
        });

        $r->post('/customers/{id}/interactions', function (Request $req, int $id): Response {
            $this->findCustomer($id);
            $type = InteractionType::tryFrom($req->input('type'));
            $body = $req->input('body');
            if ($type === null || $body === '' || mb_strlen($body) > 5000) {
                $this->flash('Choose a type and write a note (up to 5000 characters).', 'error');
            } else {
                $this->interactions->add($id, (int) $this->user['id'], $type, $body, $this->now()->format('Y-m-d H:i:s'));
                $this->flash('Interaction added to the timeline.');
            }

            return Response::redirect("/customers/{$id}");
        });

        $r->post('/customers/{id}/reminders', function (Request $req, int $id): Response {
            $this->findCustomer($id);
            $title = $req->input('title');
            $due = DateTimeImmutable::createFromFormat('!Y-m-d', $req->input('due_date'));
            if ($title === '' || mb_strlen($title) > 200 || $due === false || $due->format('Y-m-d') !== $req->input('due_date')) {
                $this->flash('A follow-up needs a title (up to 200 characters) and a valid date.', 'error');
            } else {
                $this->reminders->add($id, $title, $due->format('Y-m-d'));
                $this->flash('Follow-up scheduled for ' . $due->format('d/m/Y') . '.');
            }

            return Response::redirect("/customers/{$id}");
        });

        $r->post('/reminders/{id}/done', function (Request $req, int $id): Response {
            $reminder = $this->reminders->find($id) ?? throw new HttpException(404, 'Follow-up not found.');
            $this->reminders->markDone($id, $this->now()->format('Y-m-d H:i:s'));
            $this->flash('Follow-up marked as done.');
            $back = $req->input('back') === 'dashboard' ? '/' : '/customers/' . (int) $reminder['customer_id'];

            return Response::redirect($back);
        });

        return $r;
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function customerForm(?int $id, array $values, array $errors, int $status = 200): Response
    {
        return Response::html($this->view->render('customers/form', [
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'statuses' => CustomerStatus::cases(),
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function findCustomer(int $id): array
    {
        return $this->customers->find($id) ?? throw new HttpException(404, 'Customer not found.');
    }

    private function flash(string $message, string $kind = 'success'): void
    {
        $this->session->set('flash', ['message' => $message, 'kind' => $kind]);
    }

    /** @return array{message: string, kind: string}|null */
    private function takeFlash(): ?array
    {
        $flash = $this->session->get('flash');
        $this->session->remove('flash');

        return is_array($flash) ? $flash : null;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
    }

    private function today(): string
    {
        return $this->now()->format('Y-m-d');
    }
}
