<?php

declare(strict_types=1);

namespace Modernized\Support;

/** Session storage abstraction: native PHP sessions in production, an array in tests. */
interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** New session id after login, to prevent session fixation. */
    public function regenerate(): void;
}
