<?php
/**
 * Worker para envío de hits asíncronos vía CLI.
 * Puede recibir datos por archivo (--file) o como argumento directo.
 */

// Solo permitir ejecución desde consola
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit('Solo ejecutable desde CLI.');
}

// Obtener datos del archivo o argumento
$data = null;
$logFile = __DIR__ . '/hit_sender.log';

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--file' && $i + 1 < $argc) {
        $filePath = $argv[$i + 1];
        if (file_exists($filePath)) {
            $data = file_get_contents($filePath);
            // Limpiar archivo temporal después de leer
            unlink($filePath);
        }
        break;
    } elseif (!str_starts_with($argv[$i], '--')) {
        $rawArg = $argv[$i];
        // En Windows, las comillas vienen incluidas
        if (PHP_OS_FAMILY === 'Windows') {
            $data = trim($rawArg, '"');
        } else {
            $data = trim($rawArg, "'");
        }
    }
}

if (!$data) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | No data provided\n", FILE_APPEND);
    exit(1);
}

// Validar JSON
$decoded = json_decode($data, true);
if (!$decoded) {
    $jsonError = json_last_error_msg();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | JSON ERROR: $jsonError\n", FILE_APPEND);
    exit(1);
}

$apiUrl = 'https://spc.radiouas.org/api/hits/add';

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Loguear resultado
$logEntry = date('Y-m-d H:i:s') . " | HTTP:$httpCode | Error:$error\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Exit con código de error si falló
exit($httpCode !== 201 ? 1 : 0);