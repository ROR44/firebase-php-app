<?php

declare(strict_types=1);

namespace Kreait\Firebase\Tests\Unit\Messaging;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AppInstanceApiClient;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * @internal
 */
class AppInstanceApiClientTest extends TestCase
{
    private $client;

    /** @var AppInstanceApiClient */
    private $sut;

    protected function setUp()
    {
        $this->client = $this->prophesize(ClientInterface::class);
        $this->sut = new AppInstanceApiClient($this->client->reveal());
    }

    public function testRequestExceptionIsConvertedToMessagingException()
    {
        $e = new RequestException('Foo', new Request('POST', 'https://fake.org'));
        $this->client->request(Argument::cetera())->willThrow($e);

        $this->expectException(MessagingException::class);
        $this->sut->subscribeToTopic('foo', ['bar']);
    }

    public function testAnyThrowableIsConvertedToMessagingException()
    {
        $e = new \Exception('Foo', 999);
        $this->client->request(Argument::cetera())->willThrow($e);

        $this->expectException(MessagingException::class);
        $this->sut->subscribeToTopic('foo', ['bar']);
    }
}
