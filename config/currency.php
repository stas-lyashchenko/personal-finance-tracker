<?php

return [
    'nbu' => [
        'exchange_url' => env('NBU_EXCHANGE_URL', 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json'),
        'ca_bundle' => env('NBU_CA_BUNDLE'),
    ],
];
