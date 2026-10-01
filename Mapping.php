<?php

namespace Omnibus\Ups;

use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\TrackingStatus;

/** UPS's shapes for ours. */
final class Mapping
{
    public const SERVICES = ['01' => 'Next Day Air', '02' => '2nd Day Air', '03' => 'Ground', '07' => 'Worldwide Express', '08' => 'Worldwide Expedited', '11' => 'Standard', '12' => '3 Day Select', '54' => 'Worldwide Express Plus', '65' => 'Worldwide Saver'];

    public static function address(Address $a, bool $withAttention = true): array
    {
        return array_filter([
            'Name' => mb_substr($a->company ?? $a->name, 0, 35),
            'AttentionName' => $withAttention ? mb_substr($a->name, 0, 35) : null,
            'Phone' => $a->phone ? ['Number' => preg_replace('/\D+/', '', $a->phone)] : null,
            'EMailAddress' => $a->email,
            'Address' => array_filter([
                'AddressLine' => array_values(array_filter($a->street)),
                'City' => $a->city,
                'PostalCode' => $a->postcode,
                'CountryCode' => strtoupper($a->country),
            ]),
        ]);
    }

    public static function package(Parcel $p): array
    {
        $package = [
            'Packaging' => ['Code' => '02'],
            'PackageWeight' => ['UnitOfMeasurement' => ['Code' => 'KGS'], 'Weight' => number_format(max(0.1, $p->weight / 1000), 1, '.', '')],
        ];
        if ($p->length && $p->width && $p->height) {
            $package['Dimensions'] = ['UnitOfMeasurement' => ['Code' => 'CM'], 'Length' => (string) $p->length, 'Width' => (string) $p->width, 'Height' => (string) $p->height];
        }

        return $package;
    }

    public static function status(?string $type): TrackingStatus
    {
        return match (strtoupper((string) $type)) {
            'D', 'DD', 'DO' => TrackingStatus::DELIVERED,
            'O' => TrackingStatus::OUT_FOR_DELIVERY,
            'I', 'P', 'W' => TrackingStatus::IN_TRANSIT,
            'M' => TrackingStatus::PENDING,
            'X' => TrackingStatus::EXCEPTION,
            'RS' => TrackingStatus::RETURNED,
            default => TrackingStatus::UNKNOWN,
        };
    }
}
