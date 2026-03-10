# Stream Redirector + Stats — Setup Guide

## Estructura de archivos

```
/tu-webroot/
├── .htaccess                  ← Reglas de reescritura Apache
└── stream/
    ├── index.php              ← Entry point del tracker
    ├── stats.php              ← Dashboard de estadísticas
    ├── config.php             ← ⚠️  Configuración (editar antes de subir)
    ├── autoload.php           ← PSR-4 autoloader (sin Composer)
    ├── data/                  ← Creado automáticamente
    │   ├── stream_stats.sqlite
    │   └── access.log
    └── src/
        ├── Controller/
        │   └── StreamController.php
        ├── Http/
        │   └── StreamRequest.php
        ├── Logging/
        │   └── Logger.php
        ├── Repository/
        │   └── HitRepository.php
        ├── Security/
        │   └── RateLimiter.php
        └── Storage/
            └── Database.php
```

---

## 1. Configurar `stream/config.php`

```php
'streams' => [
    'mp3'  => ['url' => 'https://TU-SERVIDOR:8000/stream'],
    'm3u8' => ['url' => 'https://TU-VIDEO-SERVER/live/stream.m3u8'],
],
'stats' => [
    'username' => 'admin',
    'password' => 'CLAVE_MUY_SEGURA',
],
```

---

## 2. Permisos en servidor

```bash
# El directorio data/ necesita escritura por el webserver
mkdir -p stream/data
chmod 750 stream/data
chown www-data:www-data stream/data   # o el usuario de tu webserver
```

---

## 3. Requisitos del servidor

- **PHP 5.6+** (compatible hasta PHP 8.x)
- **mod_rewrite** habilitado en Apache
- **Extensión PDO + pdo_sqlite** (viene incluida en PHP por defecto)
- **AllowOverride All** en el VirtualHost de Apache

---

## 4. Verificar mod_rewrite

```bash
# Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2

# En el VirtualHost asegúrate de tener:
# AllowOverride All
```

---

## 5. URLs de uso

| Acción | URL |
|---|---|
| Stream MP3 | `https://tudominio.com/?format=mp3&referer=app.com\|miapp` |
| Stream M3U8 | `https://tudominio.com/?format=m3u8&referer=app.com\|miapp` |
| Dashboard stats | `https://tudominio.com/stream/stats/` |
| API JSON stats | `https://tudominio.com/stream/stats/?api=1` |

---

## 6. Seguridad adicional recomendada

```apache
# Agregar al .htaccess para mover data/ fuera del webroot
# (O configura config.php con ruta absoluta fuera del webroot)
RewriteRule ^stream/data/ - [F,L]
```

### Mover la BD fuera del webroot (recomendado)

```php
// config.php
'db' => ['path' => '/var/private/stream_stats.sqlite'],
'log' => ['path' => '/var/private/access.log'],
```

---

## 7. Restricción por referrer (opcional)

Para que solo ciertos dominios puedan acceder:

```php
'allowed_referers' => [
    'miapp.com',
    'partner.net',
],
```

---

## 8. Rate limiting

Por defecto: **60 peticiones por IP por minuto**.
Ajusta en `config.php`:

```php
'rate_limit' => [
    'enabled'        => true,
    'max_per_minute' => 120,
],
```

---

## 9. API de estadísticas (JSON)

`GET /stream/stats/?api=1`  → Requiere autenticación HTTP Basic

Devuelve:
```json
{
  "summary":     { "total_hits": 1234, "hits_today": 56, ... },
  "by_format":   [{ "format": "mp3", "total": 800 }, ...],
  "top_domains": [{ "domain": "app.com", "total": 300 }, ...],
  "top_apps":    [...],
  "by_day":      [...],
  "by_hour":     [...],
  "recent":      [...]
}
```
