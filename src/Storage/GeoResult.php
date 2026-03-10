<?php
declare(strict_types=1);

namespace Stream\Storage;

/** DTO inmutable con los datos de geolocalización de una IP. */
readonly class GeoResult
{
    public function __construct(
        public string     $country,
        public string     $countryCode,
        public string     $city,
        public string     $zip,
        public float|null $lat,
        public float|null $lon,
    ) {}

    /** Devuelve una instancia vacía para cuando la geo no está disponible. */
    public static function empty(): self
    {
        return new self(
            country:     '',
            countryCode: '',
            city:        '',
            zip:         '',
            lat:         null,
            lon:         null,
        );
    }

    /** Serializa los campos para insertarlos directamente en la BD. */
    public function toArray(): array
    {
        return [
            'country'      => $this->country,
            'country_code' => $this->countryCode,
            'city'         => $this->city,
            'zip'          => $this->zip,
            'lat'          => $this->lat,
            'lon'          => $this->lon,
        ];
    }
}
