<?php

namespace Ufo\Component\AsyncAmqpTransport\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Ufo\Component\AsyncAmqpTransport\AMQPTransportConfigBuilder;
use Ufo\Component\AsyncAmqpTransport\AMQPTransportResolver;
use Ufo\Component\TransportContracts\AsyncStampDTO;
use Ufo\Component\TransportContracts\AsyncTransportConfigDTO;

class AMQPTransportConfigBuilderTest extends TestCase
{
    public function testTypeMatchesAsyncConfig(): void
    {
        self::assertSame('amqp', (new AMQPTransportConfigBuilder())->getType());
    }

    public function testUnderscoresBecomeDashesInQueueName(): void
    {
        self::assertSame(
            'rpc-async.internal-dev.v1',
            (new AMQPTransportConfigBuilder())->getQueueName('rpc_async.internal_dev.v1'),
        );
    }

    public function testQueueIsBoundToTheExchangeTheResolverPublishesTo(): void
    {
        $config = (new AMQPTransportConfigBuilder())->build(
            new AsyncTransportConfigDTO('rpc_async.general.v1', '%env(resolve:RPC_ASYNC_DSN)%'),
        );

        self::assertSame('rpc_async.general.v1', $config->name);
        self::assertSame('%env(resolve:RPC_ASYNC_DSN)%', $config->dsn);
        self::assertSame([
            'exchange' => [
                'name' => AMQPTransportResolver::EXCHANGE_NAME,
                'type' => AMQPTransportResolver::EXCHANGE_TYPE,
            ],
            'queues' => [
                'rpc-async.general.v1' => [
                    'binding_keys' => ['rpc-async.general.v1'],
                ],
            ],
        ], $config->options);
    }

    public function testQueueMatchesRoutingKeyOfTheProducer(): void
    {
        $queue = (new AMQPTransportConfigBuilder())->getQueueName('rpc_async.general.v1');
        $resolver = new AMQPTransportResolver($this->createMock(AmqpTransportFactory::class));

        $stamp = $resolver->createAsyncStamp(new AsyncStampDTO('amqp://guest:guest@localhost:5672/%2f/'.$queue));

        self::assertSame($queue, $stamp->getRoutingKey());
    }

    public function testExplicitOptionsOverrideDefaults(): void
    {
        $config = (new AMQPTransportConfigBuilder())->build(new AsyncTransportConfigDTO(
            'rpc_async.general.v1',
            'amqp://localhost/%2f/',
            ['exchange' => ['name' => 'own_exchange'], 'auto_setup' => false],
        ));

        self::assertSame('own_exchange', $config->options['exchange']['name']);
        self::assertSame(AMQPTransportResolver::EXCHANGE_TYPE, $config->options['exchange']['type']);
        self::assertFalse($config->options['auto_setup']);
    }

    public function testMessengerAcceptsTheOptions(): void
    {
        $config = (new AMQPTransportConfigBuilder())->build(
            new AsyncTransportConfigDTO('rpc_async.general.v1', 'amqp://guest:guest@localhost:5672/%2f/'),
        );

        $transport = (new AmqpTransportFactory())->createTransport($config->dsn, $config->options, new PhpSerializer());

        self::assertInstanceOf(AmqpTransport::class, $transport);
    }
}
