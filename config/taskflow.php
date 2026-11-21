<?php

return [
    'heartbeat_ttl' => 15,
    'lease_seconds' => 90,
    'retry_delays' => [5, 15, 30],
    'management_url' => env('RABBITMQ_MANAGEMENT_URL', 'http://rabbitmq:15672'),
];
