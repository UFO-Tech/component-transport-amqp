<?php

namespace Ufo\Component\AsyncAmqpTransport;

use Ufo\Component\TransportContracts\AsyncTransportConfigBuilderInterface;
use Ufo\Component\TransportContracts\AsyncTransportConfigDTO;

use function array_replace_recursive;
use function str_replace;

/** Черга з іменем транспорту, прив'язана до exchange, у який публікує AMQPTransportResolver. */
class AMQPTransportConfigBuilder implements AsyncTransportConfigBuilderInterface
{
    public const string TYPE = 'amqp';

    public function getType(): string
    {
        return static::TYPE;
    }

    public function getQueueName(string $transportName): string
    {
        return str_replace('_', '-', $transportName);
    }

    public function build(AsyncTransportConfigDTO $config): AsyncTransportConfigDTO
    {
        $queue = $this->getQueueName($config->name);

        return $config->applyTransportConfig([
            'exchange' => [
                'name' => AMQPTransportResolver::EXCHANGE_NAME,
                'type' => AMQPTransportResolver::EXCHANGE_TYPE,
            ],
            'queues' => [
                $queue => [
                    'binding_keys' => [$queue],
                ],
            ],
        ]);
    }
}
