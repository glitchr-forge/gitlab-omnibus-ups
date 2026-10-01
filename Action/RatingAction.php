<?php

namespace Omnibus\Ups\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Rate;
use Omnibus\Request\Rating;
use Omnibus\Request\Request;
use Omnibus\Ups\Api;
use Omnibus\Ups\Mapping;

/** The Rating API's Shop: every service UPS can carry the shipment with, priced. */
final class RatingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Rating;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Rating);
        $s = $request->shipment;
        $data = $this->api->call('POST', '/api/rating/'.Api::VERSION.'/Shop', ['RateRequest' => [
            'Request' => ['RequestOption' => 'Shop'],
            'Shipment' => [
                'Shipper' => Mapping::address($s->sender) + ['ShipperNumber' => $this->api->accountNumber],
                'ShipTo' => Mapping::address($s->recipient),
                'ShipFrom' => Mapping::address($s->sender),
                'PaymentDetails' => ['ShipmentCharge' => [['Type' => '01', 'BillShipper' => ['AccountNumber' => $this->api->accountNumber]]]],
                'Package' => array_map([Mapping::class, 'package'], $s->parcels),
            ],
        ]]);
        $rates = [];
        foreach ($data['RateResponse']['RatedShipment'] ?? [] as $rated) {
            $code = (string) ($rated['Service']['Code'] ?? '');
            $charge = $rated['NegotiatedRateCharges']['TotalCharge'] ?? $rated['TotalCharges'] ?? [];
            $days = $rated['GuaranteedDelivery']['BusinessDaysInTransit'] ?? null;
            $rates[] = new Rate('ups', $code, Mapping::SERVICES[$code] ?? 'UPS '.$code, (int) round(((float) ($charge['MonetaryValue'] ?? 0)) * 100), strtoupper((string) ($charge['CurrencyCode'] ?? 'EUR')), null === $days ? null : (int) $days);
        }
        usort($rates, static fn (Rate $a, Rate $b) => $a->amount <=> $b->amount);
        $request->setResult($rates);
    }
}
