<?php
declare(strict_types=1);

namespace Stream\Controller;

use Stream\Http\StreamRequest;
use Stream\Logging\Logger;
use Stream\Repository\HitRepository;
use Stream\Security\RateLimiter;
use Stream\Storage\GeoLocator;
use curl_init;

/** Orquesta validación, rate limiting, geo, logging y redirección del stream. */
final class StreamController
{
    public function __construct(
        private readonly array $config,
        private readonly HitRepository $hits,
        private readonly RateLimiter $limiter,
        private readonly Logger $logger,
        private readonly GeoLocator $geo,
    ) {
    }

    /** Punto de entrada principal; procesa la petición y redirige o aborta. */
    public function handle(): never
    {
        $request = new StreamRequest();

        if (!$request->isValid()) {
            $this->logger->error('Formato no valido', $request->toArray());
            $this->abort(400, 'Formato de stream no soportado.');
        }

        if ($this->config['rate_limit']['enabled'] ?? false) {
            $result = $this->limiter->check($request->ip);
            if (!$result->allowed) {
                $this->logger->error('Rate limit excedido', [
                    'ip' => $request->ip,
                    'hits' => $result->currentHits,
                ]);
                header('Retry-After: 60');
                $this->abort(429, 'Demasiadas peticiones. Intenta en un minuto.');
            }
        }

        $allowed = $this->config['allowed_referers'] ?? [];
        if ($allowed !== [] && !in_array($request->referer, $allowed, true)) {
            $this->logger->error('Referer no permitido', ['referer' => $request->referer]);
            $this->abort(403, 'Acceso no autorizado.');
        }

        $formatValue = $request->format?->value ?? '';
        $streamCfg = $this->config['streams'][$formatValue] ?? null;

        if ($streamCfg === null) {
            $this->abort(404, 'Stream no disponible.');
        }

        $geoResult = $this->geo->locate($request->ip);

        $json = $request->toJson($request->toArray(), $geoResult->toArray());
        // aqui va la llamada a la api, igual puede ser al final, ayudame a decidir

        try {
            $this->hits->record([
                ...$request->toArray(),
                ...$geoResult->toArray(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error registrando hit: ' . $e->getMessage());
        }

        // Enviar a API en background (no esperar respuesta)
        $basePath = dirname(__DIR__, 2);
        $workerPath = $basePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'HitSender.php';

        if (file_exists($workerPath)) {
            // Guardar el JSON en un archivo temporal para pasarlo al worker
            $tempFile = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'hit_' . time() . '_' . random_int(1000, 9999) . '.json';
            file_put_contents($tempFile, $json);

            if (PHP_OS_FAMILY === 'Windows') {
                $command = "php \"$workerPath\" --file \"$tempFile\"";
            } else {
                $command = "php '$workerPath' --file '$tempFile'";
            }
            // Ejecutar en background sin esperar respuesta
            pclose(popen($command, 'r'));
        }

        $this->logger->info('Stream redirect', [
            'format' => $formatValue,
            'referer' => $request->referer,
            'type' => $request->refererType->value,
            'ip' => $request->ip,
            'city' => $geoResult->city,
            'country' => $geoResult->country,
            'dest' => $streamCfg['url'],
        ]);

        $code = (isset($streamCfg['redirect']) && in_array((int) $streamCfg['redirect'], [301, 302, 307, 308], true))
            ? (int) $streamCfg['redirect']
            : 302;

        //header('Location: ' . $streamCfg['url'], replace: true, response_code: $code);
        exit;
    }

    /** Envía un código HTTP con mensaje de texto y termina la ejecución. */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }
}
