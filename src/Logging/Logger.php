<?php
declare(strict_types=1);

namespace Stream\Logging;

/** Escribe mensajes de log en archivo con nivel mínimo configurable. */
final class Logger
{
    private readonly LogLevel $minLevel;

    public function __construct(
        private readonly string $path,
        string                  $level = 'info',
    ) {
        $this->minLevel = LogLevel::fromString($level);

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear directorio de logs: {$dir}");
        }
    }

    /** Escribe un mensaje de nivel INFO. */
    public function info(string $message, array $context = []): void
    {
        $this->write(level: LogLevel::Info, message: $message, context: $context);
    }

    /** Escribe un mensaje de nivel DEBUG. */
    public function debug(string $message, array $context = []): void
    {
        $this->write(level: LogLevel::Debug, message: $message, context: $context);
    }

    /** Escribe un mensaje de nivel ERROR. */
    public function error(string $message, array $context = []): void
    {
        $this->write(level: LogLevel::Error, message: $message, context: $context);
    }

    /** Formatea y escribe la línea en el archivo si el nivel es suficiente. */
    private function write(LogLevel $level, string $message, array $context): void
    {
        if ($level->value < $this->minLevel->value) {
            return;
        }

        $ctx = $context !== []
            ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : '';

        $line = sprintf(
            "[%s] [%s] %s%s\n",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $level->label(),
            $message,
            $ctx,
        );

        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
