<?php
declare(strict_types=1);

namespace Stream\Storage;

use PDO;

/**
 * Geolocaliza IPs usando ip-api.com con cache en SQLite.
 * Límite gratuito: 45 req/min sin API key.
 */
final class GeoLocator
{
    private readonly PDO $pdo;

    /** Prefijos de IPs privadas o reservadas que no se deben consultar. */
    private const array SKIP_RANGES = [
        '127.', '10.', '192.168.',
        '172.16.', '172.17.', '172.18.', '172.19.', '172.2',
        '::1', 'unknown',
    ];

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo;
    }

    /**
     * Devuelve la geolocalización de una IP.
     * Consulta el cache primero; si no existe llama a ip-api.com.
     * Nunca lanza excepción — devuelve GeoResult::empty() ante cualquier error.
     */
    public function locate(string $ip): GeoResult
    {
        if ($this->shouldSkip($ip)) {
            return GeoResult::empty();
        }

        $cached = $this->fromCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetchFromApi($ip);
        $this->saveToCache($ip, $result);

        return $result;
    }

    /** Indica si la IP es privada o reservada y no debe consultarse. */
    private function shouldSkip(string $ip): bool
    {
        if ($ip === '' || $ip === 'unknown') {
            return true;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        foreach (self::SKIP_RANGES as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** Busca la IP en la tabla ip_geo; devuelve null si no existe. */
    private function fromCache(string $ip): GeoResult|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT country, country_code, city, zip, lat, lon FROM ip_geo WHERE ip = :ip'
        );
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new GeoResult(
            country:     $row['country']      ?? '',
            countryCode: $row['country_code'] ?? '',
            city:        $row['city']         ?? '',
            zip:         $row['zip']          ?? '',
            lat:         $row['lat'] !== null ? (float) $row['lat'] : null,
            lon:         $row['lon'] !== null ? (float) $row['lon'] : null,
        );
    }

    /** Consulta ip-api.com via cURL; devuelve GeoResult::empty() si falla. */
    private function fetchFromApi(string $ip): GeoResult
    {
        try {
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,zip,lat,lon";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            $json     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_errno($ch);
            curl_close($ch);

            if ($curlErr !== 0 || $json === false || $httpCode !== 200) {
                return GeoResult::empty();
            }

            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

            if (($data['status'] ?? '') !== 'success') {
                return GeoResult::empty();
            }

            return new GeoResult(
                country:     $data['country']     ?? '',
                countryCode: $data['countryCode'] ?? '',
                city:        $data['city']        ?? '',
                zip:         $data['zip']         ?? '',
                lat:         isset($data['lat']) ? (float) $data['lat'] : null,
                lon:         isset($data['lon']) ? (float) $data['lon'] : null,
            );
        } catch (\Throwable) {
            return GeoResult::empty();
        }
    }

    /** Guarda o actualiza la geo de una IP en el cache. */
    private function saveToCache(string $ip, GeoResult $geo): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT OR REPLACE INTO ip_geo
                (ip, country, country_code, city, zip, lat, lon)
            VALUES
                (:ip, :country, :country_code, :city, :zip, :lat, :lon)
        SQL);
        $stmt->execute([
            ':ip'           => $ip,
            ':country'      => $geo->country,
            ':country_code' => $geo->countryCode,
            ':city'         => $geo->city,
            ':zip'          => $geo->zip,
            ':lat'          => $geo->lat,
            ':lon'          => $geo->lon,
        ]);
    }
}
