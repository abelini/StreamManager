# 📻 Radio UAS — Stream Redirector

Sistema de redirección de streams de audio y video con estadísticas en tiempo real. Intercepta las peticiones, registra métricas (formato, origen, geolocalización) en SQLite y redirige al servidor de streaming real.

---

## Requisitos

- PHP 8.2+
- Extensiones: `pdo_sqlite`, `curl`, `mbstring`
- Apache con `mod_rewrite` y `mod_headers`

---

## Instalación

```bash
# 1. Subir todos los archivos al servidor
# 2. Copiar config de ejemplo y editar credenciales
cp config.example.php config.php

# 3. Asegurarse de que el directorio data/ sea escribible
chmod 750 data/
```

El sistema crea y migra la base de datos SQLite automáticamente en el primer request.

---

## Configuración

Editar `config.php`:

```php
'streams' => [
    'mp3' => ['url' => 'https://tuservidor.com:8000/stream', 'redirect' => 302],
    'hls' => ['url' => 'https://tuservidor.com/live/stream.m3u8', 'redirect' => 302],
],

'stats' => [
    'enabled'  => true,
    'username' => 'admin',
    'password' => 'tu_clave_segura',
],

'rate_limit' => [
    'enabled'        => true,
    'max_per_minute' => 60,
],
```

---

## Uso

### Redirección de stream

```
# Audio MP3
https://stream.radiouas.org/?format=mp3&referer=uas.edu.mx

# Video HLS
https://stream.radiouas.org/?format=hls&ref=android

# Parámetros aceptados
?format=   mp3 | hls | m3u8 (alias de hls)
?referer=  dominio (tunein.com) o nombre de app (android, ios, alexa)
?ref=      alias corto de ?referer=
```

El sistema detecta automáticamente si el origen es un dominio (contiene `.`) o una app.

### Dashboard de estadísticas

```
https://stream.radiouas.org/stats
```

Requiere autenticación HTTP Basic con las credenciales definidas en `config.php`.

### API JSON

```
https://stream.radiouas.org/stats?api=1&from=2026-03-01&to=2026-03-31
```

---

## Arquitectura

```
src/
├── Controller/
│   └── StreamController      Orquesta validación, geo, logging y redirección
├── Enum/
│   ├── StreamFormat          mp3 | hls (acepta m3u8 como alias)
│   └── RefererType           domain | app | unknown
├── Http/
│   └── StreamRequest         Value object inmutable de la petición
├── Logging/
│   ├── Logger                Escribe en archivo con nivel mínimo configurable
│   └── LogLevel              Enum: Debug | Info | Error
├── Repository/
│   ├── HitRepository         Queries sobre stream_hits
│   ├── StreamSummary         DTO de KPIs para el dashboard
│   └── DateRange             Value object de rango de fechas validado
├── Security/
│   ├── RateLimiter           Control de peticiones por IP/minuto vía SQLite
│   └── RateLimitResult       DTO del resultado de verificación
└── Storage/
    ├── Database              Singleton PDO + migraciones automáticas
    ├── GeoLocator            Geolocalización vía ip-api.com con cache SQLite
    └── GeoResult             DTO inmutable de datos geográficos
```

**Sin Composer** — autoloader PSR-4 manual en `autoload.php`. No hay dependencias externas; todo usa extensiones nativas de PHP (`pdo_sqlite`, `curl`).

---

## Base de datos

SQLite en `data/stream_stats.sqlite`. Las migraciones se ejecutan automáticamente.

| Tabla            | Descripción                              |
|------------------|------------------------------------------|
| `stream_hits`    | Registro de cada petición al stream      |
| `ip_geo`         | Cache de geolocalización por IP          |
| `rate_limits`    | Control de peticiones por IP/minuto      |
| `schema_migrations` | Versiones de migración aplicadas      |

---

## Geolocalización

Usa [ip-api.com](http://ip-api.com) (gratuito, 45 req/min sin API key). Los resultados se cachean en SQLite para no repetir consultas por la misma IP.

Para actualizar el cache de IPs existentes sin código postal:

```bash
php geo-backfill.php
```

---

## Zona horaria

Todos los timestamps se guardan en hora local `America/Mazatlan` (UTC-7).

---

## Estructura de archivos

```
/
├── .htaccess               Reglas de rewrite y CORS
├── index.php               Entry point del redirector
├── stats.php               Dashboard + API JSON
├── autoload.php            Autoloader PSR-4 sin Composer
├── config.php              Credenciales y configuración (no versionar)
├── config.example.php      Plantilla de configuración
├── data/
│   ├── stream_stats.sqlite Base de datos SQLite
│   └── access.log          Log de accesos
└── src/                    Código fuente (ver Arquitectura)
```

---

## Seguridad

- `data/` debe estar fuera del webroot o protegido con `.htaccess`
- `config.php` está en `.gitignore` — nunca subir credenciales al repositorio
- Rate limiting activo por defecto: 60 req/min por IP
- Autenticación HTTP Basic en el dashboard

---

## Licencia

Uso interno — Radio UAS.