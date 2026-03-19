<?php
declare(strict_types=1);

namespace Stream\Repository;

use PDO;
use Stream\Storage\Database;

/** Acceso a datos para la tabla stream_hits. */
final class HitRepository
{
    private readonly PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo;
    }

    /** Inserta un nuevo hit con todos sus campos de geo y referer. */
    public function record(array $data): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO stream_hits
                (format, referer_raw, referer, referer_type, ip, user_agent,
                 country, country_code, city, zip, lat, lon, created_at)
            VALUES
                (:format, :referer_raw, :referer, :referer_type, :ip, :user_agent,
                 :country, :country_code, :city, :zip, :lat, :lon, :created_at)
        SQL);

        $stmt->execute([
            ':format'       => $data['format']       ?? '',
            ':referer_raw'  => $data['referer_raw']  ?? '',
            ':referer'      => $data['referer']      ?? '',
            ':referer_type' => $data['referer_type'] ?? 'unknown',
            ':ip'           => $data['ip']           ?? '',
            ':user_agent'   => $data['user_agent']   ?? '',
            ':country'      => $data['country']      ?? '',
            ':country_code' => $data['country_code'] ?? '',
            ':city'         => $data['city']         ?? '',
            ':zip'          => $data['zip']          ?? '',
            ':lat'          => $data['lat']          ?? null,
            ':lon'          => $data['lon']          ?? null,
            ':created_at'   => $data['created_at']   ?? date('Y-m-d H:i:s'),
        ]);
    }

    /** Devuelve los KPIs globales: totales, hits hoy e IPs únicas hoy. */
    public function summary(): StreamSummary
    {
        $total = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM stream_hits')
            ->fetchColumn();

        $today = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM stream_hits WHERE date(created_at) = date('now', 'localtime')")
            ->fetchColumn();

        $uniqueIps = (int) $this->pdo
            ->query("SELECT COUNT(DISTINCT ip) FROM stream_hits WHERE date(created_at) = date('now', 'localtime')")
            ->fetchColumn();

        $uniqueReferers = (int) $this->pdo
            ->query("SELECT COUNT(DISTINCT referer) FROM stream_hits WHERE referer != ''")
            ->fetchColumn();

        return new StreamSummary(
            totalHits:      $total,
            hitsToday:      $today,
            uniqueIpsToday: $uniqueIps,
            uniqueReferers: $uniqueReferers,
        );
    }

    /** Devuelve el total de hits agrupado por formato en el rango dado. */
    public function totalByFormat(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT format, COUNT(*) as total
            FROM   stream_hits
            WHERE  date(created_at) BETWEEN :from AND :to
            GROUP  BY format
            ORDER  BY total DESC
        SQL);
        $stmt->execute([':from' => $range->from, ':to' => $range->to]);
        return $stmt->fetchAll();
    }

    /** Devuelve los dominios con más hits en el rango dado. */
    public function topDomains(DateRange $range, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT referer, COUNT(*) as total
            FROM   stream_hits
            WHERE  referer_type = 'domain'
              AND  date(created_at) BETWEEN :from AND :to
            GROUP  BY referer
            ORDER  BY total DESC
            LIMIT  :limit
        SQL);
        $stmt->bindValue(':from',  $range->from);
        $stmt->bindValue(':to',    $range->to);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Devuelve las apps con más hits en el rango dado. */
    public function topApps(DateRange $range, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT referer, COUNT(*) as total
            FROM   stream_hits
            WHERE  referer_type = 'app'
              AND  date(created_at) BETWEEN :from AND :to
            GROUP  BY referer
            ORDER  BY total DESC
            LIMIT  :limit
        SQL);
        $stmt->bindValue(':from',  $range->from);
        $stmt->bindValue(':to',    $range->to);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Devuelve hits por día y formato para graficar tendencias. */
    public function hitsByDay(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT strftime('%Y-%m-%d', created_at) AS day,
                   format,
                   COUNT(*) AS total
            FROM   stream_hits
            WHERE  date(created_at) BETWEEN :from AND :to
            GROUP  BY day, format
            ORDER  BY day ASC
        SQL);
        $stmt->execute([':from' => $range->from, ':to' => $range->to]);
        return $stmt->fetchAll();
    }

    /** Devuelve hits agrupados por hora del día. */
    public function hitsByHour(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT strftime('%H', created_at) AS hour,
                   COUNT(*) AS total
            FROM   stream_hits
            WHERE  date(created_at) BETWEEN :from AND :to
            GROUP  BY hour
            ORDER  BY hour ASC
        SQL);
        $stmt->execute([':from' => $range->from, ':to' => $range->to]);
        return $stmt->fetchAll();
    }

    /** Devuelve hits de audio y video por día para la gráfica comparativa. */
    public function audioVsVideo(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT
                strftime('%Y-%m-%d', created_at) AS day,
                SUM(CASE WHEN format = 'mp3' THEN 1 ELSE 0 END) AS audio,
                SUM(CASE WHEN format = 'hls' THEN 1 ELSE 0 END) AS video
            FROM   stream_hits
            WHERE  date(created_at) BETWEEN :from AND :to
            GROUP  BY day
            ORDER  BY day ASC
        SQL);
        $stmt->execute([':from' => $range->from, ':to' => $range->to]);
        return $stmt->fetchAll();
    }

    /** Devuelve puntos geográficos con coordenadas para el mapa. */
    public function geoPoints(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT country, country_code, city, zip,
                   AVG(lat) AS lat, AVG(lon) AS lon,
                   COUNT(*) AS total
            FROM   stream_hits
            WHERE  lat IS NOT NULL AND lon IS NOT NULL
              AND  date(created_at) BETWEEN :from AND :to
            GROUP  BY country_code, city, zip
            ORDER  BY total DESC
        SQL);
        $stmt->execute([':from' => $range->from, ':to' => $range->to]);
        return $stmt->fetchAll();
    }

    /** Devuelve los países con más hits en el rango dado. */
    public function topCountries(DateRange $range, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT country, country_code, COUNT(*) as total
            FROM   stream_hits
            WHERE  country != ''
              AND  date(created_at) BETWEEN :from AND :to
            GROUP  BY country_code
            ORDER  BY total DESC
            LIMIT  :limit
        SQL);
        $stmt->bindValue(':from',  $range->from);
        $stmt->bindValue(':to',    $range->to);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Devuelve los últimos N hits ordenados por ID descendente. */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id, format, referer, referer_type, ip,
                   country, country_code, city, created_at
            FROM   stream_hits
            ORDER  BY id DESC
            LIMIT  :limit
        SQL);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
