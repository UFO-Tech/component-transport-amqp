# Repository guidance

This package extracts the AMQP resolver from `ufo-tech/json-rpc-client-sdk` 5.x.
Use PHP >=8.3, Symfony Messenger 7.x and the `Ufo\RpcSdk\AsyncAmqpTransport\` PSR-4 namespace.

Keep the SDK interfaces, abstract resolver, DTO and transport selection factory in the SDK.
Preserve the extracted resolver's constructor, routing, exchange options, stamps and serialization behavior unless explicitly changing them.
The sibling json-rpc-client-sdk checkout is compatibility context; preserve its unrelated work.

Run `composer validate --strict` and `composer test` after runtime changes.
Tests mock the AMQP factory and transport, so RabbitMQ is not required; ext-amqp is required.
Before SDK 5.0 is published, follow the README local path-repository instructions.
Do not commit vendor, Composer lock files or PHPUnit caches. Versions come from Git tags.
