<?php
declare(strict_types=1);

namespace Stream\Security;

use PDO;
use Stream\Storage\Database;

/** Controla el número de peticiones por IP por minuto usando SQLite. */
final class RateLimiter
{
    private readonly PDO $pdo;

    public function __construct(
        Database             $db,
        private readonly int $maxPerMinute = 60,
    ) {
        $this->pdo = $db->pdo;
    }

    /**
     * Registra la petición de la IP y devuelve si está permitida.
     * Usa UPSERT atómico para evitar race conditions.
     */
    public function check(string $ip): RateLimitResult
    {
        $key = date('YmdHi');

        $this->pdo->prepare(<<<'SQL'
            INSERT INTO rate_limits (ip, minute_key, hits)
            VALUES (:ip, :key, 1)
            ON CONFLICT (ip, minute_key)
            DO UPDATE SET hits = hits + 1
        SQL)->execute([':ip' => $ip, ':key' => $key]);

        $stmt = $this->pdo->prepare(
            'SELECT hits FROM rate_limits WHERE ip = :ip AND minute_key = :key'
        );
        $stmt->execute([':ip' => $ip, ':key' => $key]);
        $hits = (int) ($stmt->fetchColumn() ?: 1);

        // Limpiar entradas viejas con probabilidad 1/100
        if (random_int(1, 100) === 1) {
            $this->purgeExpired();
        }

        return new RateLimitResult(
            allowed:     $hits <= $this->maxPerMinute,
            currentHits: $hits,
            maxAllowed:  $this->maxPerMinute,
        );
    }

    /** Elimina registros de minutos anteriores al actual. */
    private function purgeExpired(): void
    {
        $current = date('YmdHi');
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE minute_key < :current');
        $stmt->execute([':current' => $current]);
    }
}
