<?php

declare(strict_types=1);

namespace ContactForm;

use ContactForm\Domain\ContactValidator;
use ContactForm\Domain\MessageRepository;
use ContactForm\Domain\RateLimiter;
use ContactForm\Domain\Settings;
use ContactForm\Domain\Topic;
use ContactForm\Http\HttpException;
use ContactForm\Http\Request;
use ContactForm\Http\Response;
use ContactForm\Http\Router;
use ContactForm\Mail\Email;
use ContactForm\Mail\MailException;
use ContactForm\Mail\Mailer;
use ContactForm\Support\Csrf;
use ContactForm\Support\Session;
use ContactForm\Support\View;
use DateTimeImmutable;
use PDO;

final class App
{
    public const HONEYPOT = 'website';

    private readonly MessageRepository $messages;
    private readonly RateLimiter $limiter;
    private readonly Settings $settings;
    private readonly Csrf $csrf;
    private readonly View $view;
    private readonly Router $router;

    /** @param \Closure(): DateTimeImmutable|null $clock */
    public function __construct(
        PDO $pdo,
        private readonly Session $session,
        private readonly Mailer $mailer,
        private readonly Config $config = new Config(),
        private readonly ?\Closure $clock = null,
    ) {
        $this->messages = new MessageRepository($pdo);
        $this->settings = new Settings($pdo);
        $this->limiter = new RateLimiter($pdo, $this->settings->secret());
        $this->csrf = new Csrf($session);
        $this->view = new View(dirname(__DIR__) . '/templates', ['companyName' => $config->companyName]);
        $this->router = $this->routes();
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->path === '/healthz') {
                return Response::text("ok\n");
            }
            if (str_starts_with($request->path, '/inbox') && $request->path !== '/inbox/login' && !$this->isInboxUser()) {
                return Response::redirect('/inbox/login');
            }
            if ($request->method === 'POST' && !$this->csrf->isValid($request->body[Csrf::FIELD] ?? null)) {
                throw new HttpException(419, 'The form expired. Reload the page and try again.');
            }
            $this->view->share('csrfToken', $this->csrf->token());

            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return Response::html($this->view->render('error', ['status' => $e->status, 'message' => $e->getMessage()]), $e->status);
        }
    }

    private function routes(): Router
    {
        $r = new Router();

        $r->get('/', fn () => $this->form([], []));

        $r->post('/', function (Request $req): Response {
            $now = $this->now()->getTimestamp();
            $c = $this->config;

            if (!$this->limiter->allows('contact', $req->ip, $c->maxSubmissions, $c->submissionWindow, $now)) {
                $wait = $this->limiter->retryAfter('contact', $req->ip, $c->maxSubmissions, $c->submissionWindow, $now);
                $response = $this->form($req->body, [], 429, sprintf(
                    'Too many messages from your connection. Please try again in %d minute(s) or write to us by e-mail.',
                    (int) ceil($wait / 60),
                ));

                return new Response($response->body, 429, $response->headers + ['Retry-After' => (string) $wait]);
            }
            $this->limiter->hit('contact', $req->ip, $now);

            // Bots fill the hidden field: pretend it worked so they do not adapt.
            if ($req->input(self::HONEYPOT) !== '') {
                return $this->blockSpam();
            }

            $result = (new ContactValidator())->validate($req->body);
            if ($result['errors'] !== []) {
                return $this->form($req->body, $result['errors'], 422);
            }

            // A complete, valid form posted faster than a person can type is also a bot.
            $renderedAt = $this->session->get('form_rendered_at');
            if (!is_int($renderedAt) || $now - $renderedAt < $c->minFillSeconds) {
                return $this->blockSpam();
            }

            $data = $result['data'];
            $id = $this->messages->save(
                $data,
                hash_hmac('sha256', $req->ip, $this->settings->secret()),
                $req->headers['user-agent'] ?? '',
                $this->now()->format('Y-m-d H:i:s'),
            );
            $this->messages->setMailStatus($id, $this->notify($id, $data));
            $this->session->remove('form_rendered_at');
            $this->session->set('sent_name', $data['name']);

            return Response::redirect('/thanks');
        });

        $r->get('/thanks', function (): Response {
            $name = $this->session->get('sent_name');
            $this->session->remove('sent_name');

            return Response::html($this->view->render('thanks', ['name' => is_string($name) ? $name : null]));
        });

        $r->get('/inbox/login', fn () => $this->isInboxUser()
            ? Response::redirect('/inbox')
            : Response::html($this->view->render('inbox/login', ['error' => null])));

        $r->post('/inbox/login', function (Request $req): Response {
            $now = $this->now()->getTimestamp();
            $c = $this->config;
            if (!$this->limiter->allows('login', $req->ip, $c->maxLoginAttempts, $c->loginWindow, $now)) {
                return Response::html($this->view->render('inbox/login', ['error' => 'Too many attempts. Wait a few minutes.']), 429);
            }
            if (!$this->settings->verifyInboxPassword($req->input('password'))) {
                $this->limiter->hit('login', $req->ip, $now);

                return Response::html($this->view->render('inbox/login', ['error' => 'Wrong password.']), 422);
            }
            $this->session->regenerate();
            $this->session->set('inbox', true);

            return Response::redirect('/inbox');
        });

        $r->post('/inbox/logout', function (): Response {
            $this->session->remove('inbox');
            $this->session->regenerate();

            return Response::redirect('/inbox/login');
        });

        $r->get('/inbox', fn (Request $req) => $this->inbox($req, null));

        $r->get('/inbox/{id}', function (Request $req, int $id): Response {
            $message = $this->findMessage($id);
            $this->messages->markRead($id, $this->now()->format('Y-m-d H:i:s'));

            return $this->inbox($req, $this->messages->find($id) ?? $message);
        });

        $r->post('/inbox/{id}/unread', function (Request $req, int $id): Response {
            $this->findMessage($id);
            $this->messages->markUnread($id);

            return Response::redirect('/inbox');
        });

        $r->post('/inbox/{id}/delete', function (Request $req, int $id): Response {
            $this->findMessage($id);
            $this->messages->delete($id);

            return Response::redirect('/inbox');
        });

        return $r;
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function form(array $values, array $errors, int $status = 200, ?string $alert = null): Response
    {
        // Remember when the form was first shown; re-renders after errors keep the original time.
        if ($status === 200 || !is_int($this->session->get('form_rendered_at'))) {
            $this->session->set('form_rendered_at', $this->now()->getTimestamp());
        }

        return Response::html($this->view->render('form', [
            'values' => $values,
            'errors' => $errors,
            'alert' => $alert,
            'topics' => Topic::cases(),
        ]), $status);
    }

    private function inbox(Request $req, ?array $selected): Response
    {
        $unreadOnly = $req->query('show') === 'unread';

        return Response::html($this->view->render('inbox/index', [
            'messages' => $this->messages->list($unreadOnly),
            'counts' => $this->messages->counts(),
            'unreadOnly' => $unreadOnly,
            'selected' => $selected,
            'spamBlocked' => (int) $this->settings->get('spam_blocked'),
        ], 'inbox/layout'));
    }

    private function blockSpam(): Response
    {
        $this->settings->set('spam_blocked', (string) ((int) $this->settings->get('spam_blocked') + 1));

        return Response::redirect('/thanks');
    }

    /** @param array<string, string> $data */
    private function notify(int $id, array $data): string
    {
        $topic = Topic::from($data['topic'])->label();
        try {
            $this->mailer->send(new Email(
                from: $this->config->mailFrom,
                to: $this->config->mailTo,
                subject: "[Contact #{$id}] {$topic} - {$data['name']}",
                textBody: "Name: {$data['name']}\nE-mail: {$data['email']}\nPhone: {$data['phone']}\n"
                    . "Company: {$data['company']}\nSubject: {$topic}\n\n{$data['body']}\n",
                replyTo: $data['email'],
            ));

            return $this->mailer->name() === 'disabled' ? 'disabled' : 'sent';
        } catch (MailException|\InvalidArgumentException $e) {
            // The message is already stored; a mail outage must not lose it or show an error to the visitor.
            error_log('Contact form: e-mail notification failed: ' . $e->getMessage());

            return 'failed';
        }
    }

    /** @return array<string, mixed> */
    private function findMessage(int $id): array
    {
        return $this->messages->find($id) ?? throw new HttpException(404, 'Message not found.');
    }

    private function isInboxUser(): bool
    {
        return $this->session->get('inbox') === true;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
    }
}
