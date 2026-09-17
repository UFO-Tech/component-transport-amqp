# UFO Asynchronous AMQP Transport

`ufo-tech/component-transport-amqp` — a library for sending messages over AMQP, built on Symfony Messenger and `ufo-tech/component-transport-contracts`.

## Requirements

- PHP >=8.3 and the `amqp` extension.
- Symfony Messenger and AMQP Messenger ^7.2.
- `ufo-tech/component-transport-contracts` ^1.
- An AMQP broker for sending messages.

## Installation

Once the package and its dependencies are published:

```sh
composer require ufo-tech/component-transport-amqp:^1.0
```

## Usage

```php
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Ufo\Component\AsyncAmqpTransport\AMQPTransportResolver;
use Ufo\Component\TransportContracts\AsyncStampDTO;
use Ufo\Component\TransportContracts\AsyncTransportFactory;

$factory = new AsyncTransportFactory([
    new AMQPTransportResolver(new AmqpTransportFactory()),
]);

$dsn = 'amqp://guest:guest@localhost:5672/%2f/jobs';
$resolver = $factory->getTransportResolver($dsn);
$message = (object) ['task' => 'send-email', 'recipient' => 'user@example.com'];

$resolver->getTransport($dsn)->send(new Envelope($message, [
    $resolver->createAsyncStamp(new AsyncStampDTO($dsn)),
]));
```

You can pass a custom `SerializerInterface` implementation as the second argument to `AMQPTransportResolver`. The default is `PhpSerializer`; the message consumer must use compatible serialization and message classes.

## Transport Configuration

- Supports the `amqp` and `amqps` schemes.
- Uses the `queue_exchange` exchange of type `direct`.
- The last DSN segment defines the queue name, routing key, and binding key. Use an explicit queue name without a trailing slash or query parameters.
- `AsyncStampDTO::highPriority` is enabled by default: it sets `delivery_mode = 2` for persistent messages, not AMQP message priority.
- `AsyncStampDTO::extra` is currently unused.

## Development



Tests use mocks: RabbitMQ is not required, but the `amqp` extension is.

## License

MIT.
