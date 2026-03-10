<?php
declare(strict_types=1);

namespace Stream\Http;

use Stream\Enum\StreamFormat;
use Stream\Enum\RefererType;

/** Value object inmutable que encapsula y sanitiza los datos de la petición entrante. */
readonly class StreamRequest
{
    public StreamFormat|null  $format;
    public string             $refererRaw;
    public string             $referer;
    public RefererType        $refererType;
    public string             $ip;
    public string             $userAgent;
    public \DateTimeImmutable $requestedAt;

    public function __construct()
    {
        $this->format      = StreamFormat::tryFromString($_GET['format'] ?? '');
        $raw               = $_GET['referer'] ?? $_GET['ref'] ?? '';
        $this->refererRaw  = trim($raw);
        $this->ip          = $this->resolveIp();
        $this->userAgent   = mb_substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        $this->requestedAt = new \DateTimeImmutable('now', new \DateTimeZone('America/Mazatlan'));

        [$referer, $type]  = $this->parseReferer($this->refererRaw);
        $this->referer     = $referer;
        $this->refererType = $type;
    }

    /** Indica si la petición tiene un formato de stream válido. */
    public function isValid(): bool
    {
        return $this->format !== null;
    }

    /** Serializa los campos para insertar en la BD o pasar al logger. */
    public function toArray(): array
    {
        return [
            'format'       => $this->format?->value ?? '',
            'referer_raw'  => $this->refererRaw,
            'referer'      => $this->referer,
            'referer_type' => $this->refererType->value,
            'ip'           => $this->ip,
            'user_agent'   => $this->userAgent,
        ];
    }

    /**
     * Limpia y clasifica el referer automáticamente.
     * Contiene punto → dominio. Sin punto → app.
     * Elimina sufijos que WordPress agrega (ej: "uas.edu.mxver108251").
     */
    private function parseReferer(string $raw): array
    {
        if ($raw === '') {
            return ['', RefererType::Unknown];
        }

        foreach ([' ', '?', '&', '/'] as $sep) {
            $raw = explode($sep, $raw)[0];
        }

        $raw   = preg_replace('/ver\d+$/i', '', $raw) ?? $raw;
        $clean = strtolower(
            preg_replace('/[^a-zA-Z0-9.\-]/', '', trim($raw)) ?? ''
        );

        if ($clean === '') {
            return ['', RefererType::Unknown];
        }

        if (str_contains($clean, '.')) {
            return [$clean, RefererType::Domain];
        }

        return [$clean, RefererType::App];
    }

    /**
     * Resuelve la IP real del cliente.
     * Solo confía en X-Forwarded-For si la petición viene de un proxy local.
     */
    private function resolveIp(): string
    {
        $remoteAddr     = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedProxies = ['127.0.0.1', '::1'];

        if (in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                $candidate = trim(explode(',', $forwarded)[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false
            ? $remoteAddr
            : 'unknown';
    }
}
