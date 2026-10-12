<?php

declare(strict_types=1);

namespace ContactForm\Domain;

use PDO;

/**
 * Sliding-window rate limit stored in SQLite, so it works across PHP-FPM/Apache workers
 * without Redis. Keys (IP addresses) are stored as salted hashes, never in clear text.
 */
final class RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $salt,
    ) {
    }

    /** True if another hit is allowed; does not record anything. */
    public function allows(string $bucket, string $key, int $maxHits, int $windowSeconds, int $now): bool
    {
        return $this->hits($bucket, $key, $windowSeconds, $now) < $maxHits;
    }

    public function hit(string $bucket, string $key, int $now): void
    {
        $this->pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, hit_at) VALUES (:b, :k, :t)')
            ->execute(['b' => $bucket, 'k' => $this->hash($key), 't' => $now]);
    }

    public function hits(string $bucket, string $key, int $windowSeconds, int $now): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = :b AND key_hash = :k AND hit_at > :since'
        );
        $stmt->execute(['b' => $bucket, 'k' => $this->hash($key), 'since' => $now - $windowSeconds]);

        return (int) $stmt->fetchColumn();
    }

    /** Seconds until the oldest hit in the window expires (0 when allowed). */
    public function retryAfter(string $bucket, string $key, int $maxHits, int $windowSeconds, int $now): int
    {
        if ($this->allows($bucket, $key, $maxHits, $windowSeconds, $now)) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT hit_at FROM rate_limits WHERE bucket = :b AND key_hash = :k AND hit_at > :since
             ORDER BY hit_at DESC LIMIT 1 OFFSET :offset'
        );
        $stmt->bindValue('b', $bucket);
        $stmt->bindValue('k', $this->hash($key));
        $stmt->bindValue('since', $now - $windowSeconds, PDO::PARAM_INT);
        $stmt->bindValue('offset', $maxHits - 1, PDO::PARAM_INT);
        $stmt->execute();
        $oldestCounted = (int) $stmt->fetchColumn();

        return max(1, $oldestCounted + $windowSeconds - $now);
    }

    /** Deletes hits older than the largest window in use. */
    public function prune(int $olderThanSeconds, int $now): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE hit_at <= :before');
        $stmt->execute(['before' => $now - $olderThanSeconds]);

        return $stmt->rowCount();
    }

    private function hash(string $key): string
    {
        return hash_hmac('sha256', $key, $this->salt);
    }
}
