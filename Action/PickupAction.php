<?php

namespace Omnibus\Ups\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Address;
use Omnibus\Model\PickupPoint;
use Omnibus\Request\Pickup;
use Omnibus\Request\Request;
use Omnibus\Ups\Api;

/** The Locator API: UPS Access Points near an address, nearest first. */
final class PickupAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Pickup;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Pickup);
        $near = $request->near;
        $data = $this->api->call('POST', '/api/locations/v2/search/availabilities/64', ['LocatorRequest' => [
            'Request' => ['RequestAction' => 'Locator', 'RequestOption' => '64'],
            'OriginAddress' => ['AddressKeyFormat' => array_filter(['AddressLine' => $near->line(0) ?: null, 'PoliticalDivision2' => $near->city, 'PostcodePrimaryLow' => $near->postcode, 'CountryCode' => strtoupper($near->country)])],
            'Translate' => ['Locale' => 'en_US'],
            'UnitOfMeasurement' => ['Code' => 'KM'],
            'LocationSearchCriteria' => ['MaximumListSize' => (string) min(50, max(1, $request->limit)), 'SearchRadius' => '25', 'AccessPointSearch' => ['AccessPointStatus' => '01']],
        ]], ['Locale' => 'en_US']);
        $points = [];
        foreach ($data['LocatorResponse']['SearchResults']['DropLocation'] ?? [] as $location) {
            $key = $location['AddressKeyFormat'] ?? [];
            $hours = [];
            foreach ($location['OperatingHours']['StandardHours']['DayOfWeek'] ?? [] as $day) {
                $hours[(int) ($day['Day'] ?? 0)] = [[$day['OpenHours'] ?? '', $day['CloseHours'] ?? '']];
            }
            $points[] = new PickupPoint('ups', (string) ($location['AccessPointInformation']['PublicAccessPointID'] ?? $location['LocationID'] ?? ''), (string) ($key['ConsigneeName'] ?? 'UPS Access Point'),
                new Address((string) ($key['ConsigneeName'] ?? ''), [(string) ($key['AddressLine'] ?? '')], (string) ($key['PostcodePrimaryLow'] ?? ''), (string) ($key['PoliticalDivision2'] ?? ''), (string) ($key['CountryCode'] ?? $near->country)),
                isset($location['Geocode']['Latitude']) ? (float) $location['Geocode']['Latitude'] : null, isset($location['Geocode']['Longitude']) ? (float) $location['Geocode']['Longitude'] : null,
                $hours, isset($location['Distance']['Value']) ? (int) round(((float) $location['Distance']['Value']) * 1000) : null);
        }
        $request->setResult(\array_slice($points, 0, $request->limit));
    }
}
