<?php

namespace Solic\Tests;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Solic\SolicService;

final class SolicServiceTest extends TestCase
{
    private array $history = [];
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_USER_AGENT'] = 'Browser from server';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testDefaultServiceUrl(): void
    {
        self::assertSame('https://solic.io/api/', (new SolicService('test-token'))->getServiceUrl());
    }

    public function testCustomServiceUrl(): void
    {
        self::assertSame('https://api.example.test/v2/', (new SolicService('test-token', 'https://api.example.test/v2/'))->getServiceUrl());
    }

    public function testListFormsSendsAuthenticatedGetAndReturnsAnUnreadStream(): void
    {
        $body = '{"data":[{"id":"form-123"}]}';
        $service = $this->service([new Response(200, [], $body)]);

        $result = $service->listForms();

        self::assertInstanceOf(StreamInterface::class, $result);
        self::assertSame(0, $result->tell());
        self::assertSame($body, $result->getContents());
        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://solic.io/api/forms', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
    }

    public function testGetFormIncludesTheIdAndReturnsTheResponseBody(): void
    {
        $body = '{"data":[{"id":"form-123","name":"Contact"}]}';
        $service = $this->service([new Response(200, [], $body)]);

        self::assertSame($body, $service->getForm('form-123')->getContents());
        self::assertCount(1, $this->history);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('https://solic.io/api/forms/show/form-123', (string) $this->history[0]['request']->getUri());
    }

    public function testRequestsUseTheCustomBaseUrl(): void
    {
        $service = $this->service([new Response(200, [], '{}')], 'https://api.example.test/v2/');

        $service->listForms();

        self::assertSame('https://api.example.test/v2/forms', (string) $this->history[0]['request']->getUri());
    }

    #[DataProvider('metadataCases')]
    public function testCheckFormPostsJsonWithTheSelectedMetadata(?string $ip, ?string $agent, string $expectedIp, string $expectedAgent): void
    {
        $service = $this->service([new Response(200, [], '{"spam":false}')]);
        $data = [
            'csrf_token' => 'fixture-csrf',
            'message' => 'Hello & welcome',
            'meta' => ['request_id' => 'fixture-request', 'ip_address' => 'old-ip', 'user_agent' => 'old-agent'],
        ];
        $originalData = $data;

        $result = $service->checkForm($data, $ip, $agent);

        self::assertSame('{"spam":false}', $result->getContents());
        self::assertSame($originalData, $data, 'The caller\'s input must not be mutated.');
        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://solic.io/api/forms/store', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        $expected = $originalData;
        $expected['meta']['ip_address'] = $expectedIp;
        $expected['meta']['user_agent'] = $expectedAgent;
        self::assertSame($expected, json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public static function metadataCases(): iterable
    {
        yield 'explicit values override server values' => ['198.51.100.20', 'Explicit agent', '198.51.100.20', 'Explicit agent'];
        yield 'null values use server values' => [null, null, '192.0.2.10', 'Browser from server'];
        yield 'only IP is explicit' => ['198.51.100.20', null, '198.51.100.20', 'Browser from server'];
        yield 'only agent is explicit' => [null, 'Explicit agent', '192.0.2.10', 'Explicit agent'];
        yield 'empty values use server values' => ['', '', '192.0.2.10', 'Browser from server'];
    }

    public function testCheckFormCreatesMetadataWhenItWasNotProvided(): void
    {
        $service = $this->service([new Response(200, [], '{}')]);

        $service->checkForm(['message' => 'hello'], '198.51.100.20', 'Explicit agent');

        self::assertSame([
            'message' => 'hello',
            'meta' => ['ip_address' => '198.51.100.20', 'user_agent' => 'Explicit agent'],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('httpErrorCases')]
    public function testHttpErrorsPropagateToTheCaller(string $method, array $arguments, int $status, string $exception): void
    {
        $service = $this->service([new Response($status, [], '{"error":"fixture failure"}')]);

        $this->expectException($exception);

        $service->{$method}(...$arguments);
    }

    public static function httpErrorCases(): iterable
    {
        yield 'list unauthorized' => ['listForms', [], 401, ClientException::class];
        yield 'form not found' => ['getForm', ['missing-form'], 404, ClientException::class];
        yield 'invalid submission' => ['checkForm', [[], '192.0.2.10', 'Agent'], 422, ClientException::class];
        yield 'list unavailable' => ['listForms', [], 503, ServerException::class];
        yield 'submission server error' => ['checkForm', [[], '192.0.2.10', 'Agent'], 500, ServerException::class];
    }

    #[DataProvider('serviceMethods')]
    public function testConnectionErrorsPropagateToTheCaller(string $method, array $arguments): void
    {
        $failure = new ConnectException('Fixture connection failure', new Request('GET', 'https://api.example.test/'));
        $service = $this->service([$failure]);

        $this->expectExceptionObject($failure);

        $service->{$method}(...$arguments);
    }

    public static function serviceMethods(): iterable
    {
        yield 'list' => ['listForms', []];
        yield 'get' => ['getForm', ['form-123']];
        yield 'check' => ['checkForm', [[], '192.0.2.10', 'Agent']];
    }

    public function testSequentialCallsReturnTheirOwnResponseStreams(): void
    {
        $service = $this->service([new Response(200, [], 'first response'), new Response(200, [], 'second response')]);

        self::assertSame('first response', $service->listForms()->getContents());
        self::assertSame('second response', $service->getForm('form-123')->getContents());
        self::assertCount(2, $this->history);
        self::assertSame('/api/forms', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('/api/forms/show/form-123', $this->history[1]['request']->getUri()->getPath());
    }

    private function service(array $responses, string $baseUrl = 'https://solic.io/api/'): SolicService
    {
        // Keep the real constructor, headers, request methods and Guzzle middleware.
        // Replace only the network handler; production code needs no test-only API.
        $service = new class ('test-token', $baseUrl) extends SolicService {
            public function intercept(MockHandler $mock, array &$history): void
            {
                $stack = $this->client->getConfig('handler');
                $stack->setHandler($mock);
                $stack->push(Middleware::history($history));
            }
        };
        $service->intercept(new MockHandler($responses), $this->history);

        return $service;
    }
}
