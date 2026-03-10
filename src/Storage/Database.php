<?php
declare(strict_types=1);

namespace Stream\Storage;

use PDO;
use PDOException;

/** Singleton que gestiona la conexión PDO a SQLite y ejecuta migraciones. */
final class Database
{
    private static self|null $instance = null;
    public readonly PDO $pdo;

    private function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear el directorio: {$dir}");
        }

        try {
            $this->pdo = new PDO(
                dsn: "sqlite:{$path}",
                options: [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('No se pudo abrir la base de datos SQLite', previous: $e);
        }

        $this->applyPragmas();
        $this->migrate();
    }

    /** Devuelve la instancia única; la crea si no existe. */
    public static function getInstance(string $path): self
    {
        return self::$instance ??= new self($path);
    }

    /** Aplica configuraciones de rendimiento y seguridad a SQLite. */
    private function applyPragmas(): void
    {
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous  = NORMAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA temp_store   = MEMORY');
    }

    /** Ejecuta migraciones pendientes en orden de versión. */
    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_migrations (
                version    INTEGER PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            )
        SQL);

        $version = (int) $this->pdo
            ->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')
            ->fetchColumn();

        if ($version < 1) {
            $this->migrateV1();
        }

        if ($version < 2) {
            $this->migrateV2();
        }
    }

    /** v1: crea tablas base stream_hits y rate_limits. */
    private function migrateV1(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS stream_hits (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                format       TEXT NOT NULL DEFAULT '',
                referer_raw  TEXT NOT NULL DEFAULT '',
                referer      TEXT NOT NULL DEFAULT '',
                referer_type TEXT NOT NULL DEFAULT 'unknown',
                ip           TEXT NOT NULL DEFAULT '',
                user_agent   TEXT NOT NULL DEFAULT '',
                created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            CREATE INDEX IF NOT EXISTS idx_hits_format       ON stream_hits (format);
            CREATE INDEX IF NOT EXISTS idx_hits_referer      ON stream_hits (referer);
            CREATE INDEX IF NOT EXISTS idx_hits_referer_type ON stream_hits (referer_type);
            CREATE INDEX IF NOT EXISTS idx_hits_created      ON stream_hits (created_at);
            CREATE INDEX IF NOT EXISTS idx_hits_ip           ON stream_hits (ip);

            CREATE TABLE IF NOT EXISTS rate_limits (
                ip         TEXT NOT NULL,
                minute_key TEXT NOT NULL,
                hits       INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (ip, minute_key)
            );
        SQL);
        $this->pdo->exec("INSERT INTO schema_migrations (version) VALUES (1)");
    }

    /** v2: agrega tabla ip_geo y columnas de geolocalización a stream_hits. */
    private function migrateV2(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ip_geo (
                ip           TEXT PRIMARY KEY,
                country      TEXT NOT NULL DEFAULT '',
                country_code TEXT NOT NULL DEFAULT '',
                city         TEXT NOT NULL DEFAULT '',
                zip          TEXT NOT NULL DEFAULT '',
                lat          REAL,
                lon          REAL,
                fetched_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );
        SQL);

        // ALTER TABLE solo agrega columnas que no existan (BD puede venir de v1)
        $existing = array_column(
            $this->pdo->query("PRAGMA table_info(stream_hits)")->fetchAll(),
            'name'
        );
        $toAdd = [
            'country'      => "TEXT NOT NULL DEFAULT ''",
            'country_code' => "TEXT NOT NULL DEFAULT ''",
            'city'         => "TEXT NOT NULL DEFAULT ''",
            'zip'          => "TEXT NOT NULL DEFAULT ''",
            'lat'          => 'REAL',
            'lon'          => 'REAL',
        ];
        foreach ($toAdd as $col => $type) {
            if (!in_array($col, $existing, true)) {
                $this->pdo->exec("ALTER TABLE stream_hits ADD COLUMN {$col} {$type}");
            }
        }

        $this->pdo->exec("INSERT INTO schema_migrations (version) VALUES (2)");
    }
}
