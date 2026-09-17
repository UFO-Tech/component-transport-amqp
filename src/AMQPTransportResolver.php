<?php

namespace Ufo\Component\AsyncAmqpTransport;


use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Ufo\Component\TransportContracts\AbstractAsyncTransportResolver;
use Ufo\Component\TransportContracts\AsyncStampDTO;

use function count;
use function end;
use function explode;

class AMQPTransportResolver extends AbstractAsyncTransportResolver
{
    public function __construct(
        AmqpTransportFactory $transportFactory,
        ?SerializerInterface $serializer = null
    )
    {
        parent::__construct($transportFactory, $serializer);
    }

    public function createAsyncStamp(AsyncStampDTO $asyncStampData): StampInterface
    {
        return new AmqpStamp(
            routingKey: $this->getQueue($asyncStampData->asyncDSN),
            attributes: $asyncStampData->highPriority ? ['delivery_mode' => 2] : []
        );
    }

    protected function asyncOptions(string $dsn): array
    {
        return [
            'exchange' => [
                'name' => 'queue_exchange',
                'type' => 'direct',
            ],
            'queues' => [
                $this->getQueue($dsn) => [
                    'binding_keys' => [$this->getQueue($dsn)],
                ],
            ],
        ];
    }

    protected function getQueue(string $dsn): string
    {
        $parts = explode('/', $dsn);
        return count($parts) >= 3 ? end($parts) : 'messages';
    }

    public function getSupportSchemes(): array
    {
        return ['amqp', 'amqps'];
    }
}
