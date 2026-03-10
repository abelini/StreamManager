<?php
declare(strict_types=1);

namespace Stream\Enum;

/** Clasifica el origen de una petición de stream. */
enum RefererType: string
{
    case Domain  = 'domain';  // ej: tunein.com, uas.edu.mx
    case App     = 'app';     // ej: android, ios, alexa
    case Unknown = 'unknown'; // sin parámetro referer
}
