<?php

namespace Ufo\Component\AsyncAmqpTransport\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Ufo\Component\AsyncAmqpTransport\AMQPTransportResolver;
use Ufo\Component\TransportContracts\AsyncStampDTO;
use Ufo\Component\TransportContracts\AsyncTransportFactory;
use Ufo\Component\TransportContracts\Exceptions\TransportNotFoundException;

class AMQPTransportResolverTest extends TestCase
{
    public static function routingCases(): iterable
    {
        yield 'AMQP' => ['amqp://guest:guest@localhost:5672/%2f/jobs', 'jobs'];
        yield 'TLS' => ['amqps://guest:guest@localhost:5671/vhost/tasks', 'tasks'];
        yield 'legacy fallback' => ['invalid', 'messages'];
        yield 'legacy trailing slash' => ['amqp://localhost/', ''];
        yield 'legacy query handling' => ['amqp://localhost/%2f/jobs?heartbeat=30', 'jobs?heartbeat=30'];
    }

    #[DataProvider('routingCases')]
    public function testRoutingAndPersistentDelivery(string $dsn, string $queue): void
    {
        $resolver = new AMQPTransportResolver($this->createMock(AmqpTransportFactory::class));
        $stamp = $resolver->createAsyncStamp(new AsyncStampDTO($dsn));

        self::assertInstanceOf(AmqpStamp::class, $stamp);
        self::assertSame($queue, $stamp->getRoutingKey());
        self::assertSame(['delivery_mode' => 2], $stamp->getAttributes());
        self::assertSame(AMQP_NOPARAM, $stamp->getFlags());
    }

    public function testNonPersistentStampAndUnusedExtra(): void
    {
        $resolver = new AMQPTransportResolver($this->createMock(AmqpTransportFactory::class));
        $stamp = $resolver->createAsyncStamp(new AsyncStampDTO(
            'amqp://localhost/%2f/jobs',
            highPriority: false,
            extra: ['priority' => 9],
        ));

        self::assertSame([], $stamp->getAttributes());
    }

    public function testFactoryOptionsDefaultSerializerAndCachePerDsn(): void
    {
        $factory = $this->createMock(AmqpTransportFactory::class);
        $first = $this->createMock(TransportInterface::class);
        $second = $this->createMock(TransportInterface::class);
        $dsns = ['amqp://localhost/%2f/jobs', 'amqps://localhost/%2f/jobs'];
        $calls = 0;
        $factory->expects(self::exactly(2))->method('createTransport')->willReturnCallback(
            function (string $dsn, array $options, SerializerInterface $serializer) use ($dsns, &$calls, $first, $second): TransportInterface {
                self::assertSame($dsns[$calls], $dsn);
                self::assertSame([
                    'exchange' => ['name' => 'queue_exchange', 'type' => 'direct'],
                    'queues' => ['jobs' => ['binding_keys' => ['jobs']]],
                ], $options);
                self::assertInstanceOf(PhpSerializer::class, $serializer);
                return $calls++ === 0 ? $first : $second;
            }
        );

        $resolver = new AMQPTransportResolver($factory);
        self::assertSame($factory, $resolver->getTransportFactory());
        self::assertSame($first, $resolver->getTransport($dsns[0]));
        self::assertSame($first, $resolver->getTransport($dsns[0]));
        self::assertSame($second, $resolver->getTransport($dsns[1]));
        self::assertSame($second, $resolver->getTransport($dsns[1]));
    }

    public function testCustomSerializerIsPassedThrough(): void
    {
        $factory = $this->createMock(AmqpTransportFactory::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $transport = $this->createMock(TransportInterface::class);
        $dsn = 'amqp://localhost/%2f/jobs';
        $factory->expects(self::once())->method('createTransport')
            ->with($dsn, self::isType('array'), self::identicalTo($serializer))
            ->willReturn($transport);

        self::assertSame($transport, (new AMQPTransportResolver($factory, $serializer))->getTransport($dsn));
    }

    public function testRegistrationWithSdkFactory(): void
    {
        $resolver = new AMQPTransportResolver($this->createMock(AmqpTransportFactory::class));
        $factory = new AsyncTransportFactory([$resolver]);

        self::assertSame(['amqp', 'amqps'], $resolver->getSupportSchemes());
        self::assertSame($resolver, $factory->getTransportResolver('amqp://localhost/%2f/jobs'));
        self::assertSame($resolver, $factory->getTransportResolver('amqps://localhost/%2f/jobs'));
        $this->expectException(TransportNotFoundException::class);
        $factory->getTransportResolver('redis://localhost/messages');
    }
}
