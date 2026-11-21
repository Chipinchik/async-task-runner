<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class BrokerMetrics
{
    public function read(): array
    {
        return Cache::remember('taskflow.broker-metrics', 2, function (): array {
            try {
                $host = config('queue.connections.rabbitmq.hosts.0');
                $url = rtrim(config('taskflow.management_url'), '/').'/api/queues/'.rawurlencode($host['vhost']).'/'.rawurlencode(config('queue.connections.rabbitmq.queue'));
                $data = Http::withBasicAuth($host['user'], $host['password'] ?? '')->connectTimeout(1)->timeout(2)->get($url)->throw()->json();

                return ['available' => true, 'messages_ready' => $data['messages_ready'] ?? null, 'messages_unacknowledged' => $data['messages_unacknowledged'] ?? null, 'consumers' => $data['consumers'] ?? null, 'sampled_at' => now()->toIso8601String()];
            } catch (Throwable) {
                return ['available' => false, 'messages_ready' => null, 'messages_unacknowledged' => null, 'consumers' => null, 'sampled_at' => now()->toIso8601String()];
            }
        });
    }
}
