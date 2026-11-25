<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tests;

use Datacircle\OpggMcp\Datacircle;
use OPGG\LaravelMcpServer\LaravelMcpServerServiceProvider;
use OPGG\LaravelMcpServer\Routing\McpRouteBuilder;
use OPGG\LaravelMcpServer\Services\ToolService\Examples\HelloWorldTool;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [LaravelMcpServerServiceProvider::class];
    }

    /**
     * An endpoint as an app registers one: Datacircle's tools beside its own, and its tool API where the server has
     * one (2.0.5 and later).
     */
    protected function defineRoutes($router): void
    {
        $endpoint = $router->mcp('/mcp')->setServerInfo(name: 'Test', version: '1.0.0');
        if (method_exists(McpRouteBuilder::class, 'enabledApi')) {
            $endpoint = $endpoint->enabledApi();
        }
        $endpoint->tools([HelloWorldTool::class, ...Datacircle::TOOLS]);
    }

    /**
     * A JSON-RPC request to the endpoint, its result or error.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function rpc(string $method, array $params = []): array
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params])
            ->assertOk()->json();
    }

    /**
     * tools/call of one tool, its result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function callTool(string $name, array $arguments = []): array
    {
        $answer = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
        $this->assertArrayHasKey('result', $answer, json_encode($answer) ?: '');

        return $answer['result'];
    }

    /**
     * A result that is no error: its structured content, which its text holds as JSON.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function structured(array $result): array
    {
        $this->assertFalse($result['isError'] ?? false, json_encode($result) ?: '');
        $this->assertSame($result['structuredContent'], json_decode($result['content'][0]['text'], true));

        return $result['structuredContent'];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function assertError(string $words, array $result): void
    {
        $this->assertTrue($result['isError'] ?? false, json_encode($result) ?: '');
        $this->assertStringContainsString($words, $result['content'][0]['text']);
    }
}
