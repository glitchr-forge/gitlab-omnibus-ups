<?php

namespace Omnibus\Ups\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;
use Omnibus\Ups\Api;
use Omnibus\Ups\Mapping;

/** The Tracking API: the parcel's activities, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call('GET', '/api/track/v1/details/'.rawurlencode($request->trackingNumber), null, ['locale' => str_replace('-', '_', $request->locale).(str_contains($request->locale, '_') || str_contains($request->locale, '-') ? '' : '_'.strtoupper($request->locale))]);
        $events = [];
        $status = TrackingStatus::UNKNOWN;
        foreach ($data['trackResponse']['shipment'][0]['package'] ?? [] as $package) {
            foreach (array_reverse($package['activity'] ?? []) as $activity) {
                $at = \DateTimeImmutable::createFromFormat('YmdHis', ($activity['date'] ?? '19700101').($activity['time'] ?? '000000')) ?: new \DateTimeImmutable();
                $address = $activity['location']['address'] ?? [];
                $events[] = new TrackingEvent($at, Mapping::status($activity['status']['type'] ?? null), (string) ($activity['status']['description'] ?? ''), trim(implode(' ', array_filter([$address['city'] ?? null, $address['countryCode'] ?? null]))) ?: null, $activity['status']['code'] ?? null);
            }
            if ($events) {
                $status = $events[array_key_last($events)]->status;
            }
        }
        $request->setResult(new TrackingModel('ups', $request->trackingNumber, $status, $events));
    }
}
