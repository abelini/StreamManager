<?php
declare(strict_types=1);

namespace Stream\Repository;

/** Value object que representa un rango de fechas validado para queries. */
readonly class DateRange
{
    public string $from;
    public string $to;

    /** Acepta fechas en formato Y-m-d; usa los últimos 7 días si son inválidas. */
    public function __construct(string $from = '', string $to = '')
    {
        $this->from = $this->validate($from) ? $from : date('Y-m-d', strtotime('-7 days'));
        $this->to   = $this->validate($to)   ? $to   : date('Y-m-d');
    }

    /** Construye un DateRange para los últimos N días hasta hoy. */
    public static function lastDays(int $days): self
    {
        return new self(
            from: date('Y-m-d', strtotime("-{$days} days")),
            to:   date('Y-m-d'),
        );
    }

    /** Verifica que el string sea una fecha Y-m-d válida. */
    private function validate(string $date): bool
    {
        if ($date === '') {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
