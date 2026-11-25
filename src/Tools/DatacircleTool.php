<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tools;

use Datacircle\OpggMcp\Client;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;
use RuntimeException;

/**
 * A Laravel MCP Server tool on Datacircle's open API. Its result is a JSON object, which tools/call gives as text and
 * as structured content. Every failure (an answer of 4xx in the API's own words, a 5xx, a failed network) is an error
 * result in words, so that the model reads them and can call again: the server would answer a thrown one with
 * INTERNAL_ERROR alone. Each tool is read-only, idempotent and open-world.
 */
abstract class DatacircleTool implements ToolInterface
{
    protected Client $client;

    /**
     * @param  Client|null  $client  The API's client: the container's Client when the server makes the tool.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client;
    }

    abstract public function title(): string;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    abstract protected function call(array $arguments): array;

    public function annotations(): array
    {
        return [
            'title' => $this->title(),
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true,
        ];
    }

    /**
     * The server gives a result of tools/call as structured content and as its JSON text.
     */
    public function autoStructuredOutput(): bool
    {
        return true;
    }

    public function execute(array $arguments): mixed
    {
        try {
            return $this->call($arguments);
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * @return array{isError: true, content: list<array{type: string, text: string}>}
     */
    protected static function error(string $message): array
    {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $message]]];
    }

    /**
     * A string argument as the model sent it: trimmed, '' when it sent none or something else.
     */
    protected static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    protected static function schema(array $properties, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties] + ($required === [] ? [] : ['required' => $required]);
    }
}
