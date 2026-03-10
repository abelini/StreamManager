<?php
declare(strict_types=1);

namespace Stream\Security;

/** DTO inmutable con el resultado de una verificación de rate limit. */
readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int  $currentHits,
        public int  $maxAllowed,
    ) {}

    /** Devuelve cuántas peticiones quedan disponibles en el minuto actual. */
    public function remainingHits(): int
    {
        return max(0, $this->maxAllowed - $this->currentHits);
    }
}
