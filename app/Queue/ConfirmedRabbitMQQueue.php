<?php

namespace App\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class ConfirmedRabbitMQQueue extends RabbitMQQueue
{
    private bool $needsReconnect = false;

    private ?AMQPChannel $confirmChannel = null;

    public function resetAfterFailure(): void
    {
        $this->needsReconnect = true;
        $this->channel = null;
        $this->queues = $this->exchanges = $this->boundQueues = [];
    }

    public function getChannel($forceNew = false): AMQPChannel
    {
        if ($this->needsReconnect) {
            $this->getConnection()->reconnect();
            $this->needsReconnect = false;
        }

        return parent::getChannel($forceNew);
    }

    protected function publishBasic($msg, $exchange = '', $destination = '', $mandatory = false, $immediate = false, $ticket = null): void
    {
        $channel = $this->getChannel();
        if ($this->confirmChannel !== $channel) {
            $channel->confirm_select();
            $this->confirmChannel = $channel;
        }
        $rejected = false;
        $channel->set_nack_handler(function () use (&$rejected): void {
            $rejected = true;
        });
        $channel->set_return_listener(function () use (&$rejected): void {
            $rejected = true;
        });
        parent::publishBasic($msg, $exchange, $destination, true, $immediate, $ticket);
        $channel->wait_for_pending_acks_returns(5);
        if ($rejected) {
            throw new RuntimeException('RabbitMQ отклонил публикацию.');
        }
    }
}
