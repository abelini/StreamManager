<?php
declare(strict_types=1);

namespace Stream\Repository;

/** DTO inmutable con los KPIs globales del dashboard. */
readonly class StreamSummary
{
    public function __construct(
        public int $totalHits,
        public int $hitsToday,
        public int $uniqueIpsToday,
        public int $uniqueReferers,
    ) {}

    /** Serializa los campos para la respuesta JSON de la API. */
    public function toArray(): array
    {
        return [
            'total_hits'       => $this->totalHits,
            'hits_today'       => $this->hitsToday,
            'unique_ips_today' => $this->uniqueIpsToday,
            'unique_referers'  => $this->uniqueReferers,
        ];
    }
}
