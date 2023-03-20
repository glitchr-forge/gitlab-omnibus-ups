<?php

namespace Omnibus\UPS;

use Omnibus\Core\GatewayFactory;

class UPSGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'ups',
            'omnibus.factory_title' => 'UPS'
        ]);
    }
}