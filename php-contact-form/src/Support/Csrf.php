<?php

declare(strict_types=1);

namespace ContactForm\Support;

/** Synchronizer token: one random token per session, compared in constant time. */
final class Csrf
{
    public const FIELD = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('csrf_token');
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('csrf_token', $token);
        }

        return $token;
    }

    public function isValid(mixed $submitted): bool
    {
        $token = $this->session->get('csrf_token');

        return is_string($token) && is_string($submitted) && hash_equals($token, $submitted);
    }
}
