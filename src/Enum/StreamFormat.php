<?php
declare(strict_types=1);

namespace Stream\Enum;

/** Formatos de stream soportados por el redirector. */
enum StreamFormat: string
{
    case Mp3 = 'mp3';
    case Hls = 'hls';

    /** Devuelve la etiqueta legible del formato. */
    public function label(): string
    {
        return match($this) {
            self::Mp3 => 'Audio',
            self::Hls => 'Video',
        };
    }

    /** Devuelve la clase CSS para el badge del formato en el dashboard. */
    public function badgeClass(): string
    {
        return match($this) {
            self::Mp3 => 'mp3',
            self::Hls => 'hls',
        };
    }

    /**
     * Construye un StreamFormat desde un string case-insensitive.
     * Acepta 'm3u8' como alias de 'hls' por compatibilidad.
     * Devuelve null si el valor no es reconocido.
     */
    public static function tryFromString(string $value): self|null
    {
        $normalized = strtolower(trim($value));

        if ($normalized === 'm3u8') {
            return self::Hls;
        }

        return self::tryFrom($normalized);
    }

    /** Devuelve un array con todos los valores del enum. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
