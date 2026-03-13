# Stream Manager

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

# 2. Crear config.local.php basado en el ejemplo
cp config.local.php.example config.local.php

# 3. Editar credenciales en config.local.php
#    La contraseña se hashea automáticamente

# 4. Asegurarse de que el directorio data/ sea escribible
chmod 750 data/
```

---

## Configuración

Editar `config.local.php`:

```php
return [
    'STATS_USERNAME' => 'admin',
    'STATS_PASSWORD' => 'tu_contraseña_segura',

    'MP3_STREAM_URL' => 'https://tuservidor.com:8000/stream',
    'HLS_STREAM_URL' => 'https://tuservidor.com/live/stream.m3u8',
];
```

**Nota:** La contraseña en texto plano se hashea automáticamente con `PASSWORD_BCRYPT` cuando se carga la configuración.

Parámetros disponibles en `config.php`:

```php
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
https://tusitio.com/?format=mp3&referer=ejemplo.com

# Video HLS
https://tusitio.com/?format=hls&ref=android

# Parámetros aceptados
?format=   mp3 | hls | m3u8 (alias de hls)
?referer= dominio (tunein.com) o nombre de app (android, ios, alexa)
?ref=      alias corto de ?referer=
```

El sistema detecta automáticamente si el origen es un dominio (contiene `.`) o una app.

### Dashboard de estadísticas

```
https://tusitio.com/stats
```

Requiere autenticación HTTP Basic.

### API JSON

```
https://tusitio.com/stats?api=1&from=2026-01-01&to=2026-01-31
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
    ├── Database              Singleton PDO + creación de tablas
    ├── GeoLocator            Geolocalización vía ip-api.com con cache SQLite
    └── GeoResult             DTO inmutable de datos geográficos
```

**Sin Composer** — autoloader PSR-4 manual en `autoload.php`. No hay dependencias externas.

---

## Base de datos

SQLite en `data/stream_stats.sqlite`. Las tablas se crean automáticamente.

| Tabla         | Descripción                           |
|---------------|---------------------------------------|
| `stream_hits` | Registro de cada petición al stream   |
| `ip_geo`      | Cache de geolocalización por IP       |
| `rate_limits` | Control de peticiones por IP/minuto   |

---

## Geolocalización

Usa [ip-api.com](http://ip-api.com) (gratuito, 45 req/min sin API key). Los resultados se cachean en SQLite.

---

## Zona horaria

Todos los timestamps se guardan en hora local `America/Mazatlan`.

---

## Estructura de archivos

```
/
├── .htaccess               Reglas de rewrite y CORS
├── index.php               Entry point del redirector
├── stats.php               Dashboard + API JSON
├── autoload.php            Autoloader PSR-4 sin Composer
├── config.php              Carga configuración (no versionar)
├── config.local.php        Configuración local con credenciales (no versionar)
├── config.local.php.example Plantilla de configuración
├── migrate.php             Script de migración de BD
├── data/
│   ├── stream_stats.sqlite Base de datos SQLite
│   └── access.log          Log de accesos
└── src/                    Código fuente
```

---

## Seguridad

- `data/` está protegido con `.htaccess`
- `config.local.php` está en `.gitignore`
- Rate limiting activo: 60 req/min por IP
- Contraseña hasheada con `PASSWORD_BCRYPT`
- Autenticación HTTP Basic en el dashboard

---

## Licencia

Uso interno.
