<?php
declare(strict_types=1);

namespace Stream\Logging;

/** Niveles de log ordenados por severidad ascendente. */
enum LogLevel: int
{
    case Debug = 0;
    case Info  = 1;
    case Error = 2;

    /** Devuelve la etiqueta en texto para escribir en el log. */
    public function label(): string
    {
        return match($this) {
            self::Debug => 'DEBUG',
            self::Info  => 'INFO',
            self::Error => 'ERROR',
        };
    }

    /** Construye un LogLevel desde un string; cualquier valor desconocido resulta en Info. */
    public static function fromString(string $level): self
    {
        return match(strtolower($level)) {
            'debug' => self::Debug,
            'error' => self::Error,
            default => self::Info,
        };
    }
}
