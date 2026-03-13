<?php
declare(strict_types=1);

namespace Stream\Storage;

use PDO;
use PDOException;

/**
 * Gestiona la conexión PDO a SQLite.
 */
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

        $this->setup();
    }

    /**
     * Devuelve la instancia única; la crea si no existe.
     */
    public static function getInstance(string $path): self
    {
        return self::$instance ??= new self($path);
    }

    /**
     * Configura SQLite y crea las tablas si no existen.
     */
    private function setup(): void
    {
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous  = NORMAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA temp_store   = MEMORY');

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS stream_hits (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                format       TEXT NOT NULL DEFAULT '',
                referer_raw  TEXT NOT NULL DEFAULT '',
                referer      TEXT NOT NULL DEFAULT '',
                referer_type TEXT NOT NULL DEFAULT 'unknown',
                ip           TEXT NOT NULL DEFAULT '',
                user_agent   TEXT NOT NULL DEFAULT '',
                country      TEXT NOT NULL DEFAULT '',
                country_code TEXT NOT NULL DEFAULT '',
                city         TEXT NOT NULL DEFAULT '',
                zip          TEXT NOT NULL DEFAULT '',
                lat          REAL,
                lon          REAL,
                created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_limits (
                ip         TEXT NOT NULL,
                minute_key TEXT NOT NULL,
                hits       INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (ip, minute_key)
            )
        SQL);

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
            )
        SQL);

        $this->createIndexes();
    }

    /**
     * Crea los índices necesarios.
     */
    private function createIndexes(): void
    {
        $indexes = [
            'idx_hits_format'       => 'CREATE INDEX IF NOT EXISTS idx_hits_format ON stream_hits (format)',
            'idx_hits_referer'      => 'CREATE INDEX IF NOT EXISTS idx_hits_referer ON stream_hits (referer)',
            'idx_hits_referer_type' => 'CREATE INDEX IF NOT EXISTS idx_hits_referer_type ON stream_hits (referer_type)',
            'idx_hits_created'      => 'CREATE INDEX IF NOT EXISTS idx_hits_created ON stream_hits (created_at)',
            'idx_hits_ip'           => 'CREATE INDEX IF NOT EXISTS idx_hits_ip ON stream_hits (ip)',
        ];

        foreach ($indexes as $sql) {
            $this->pdo->exec($sql);
        }
    }
}
