<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tests;

use Datacircle\OpggMcp\Client;
use Datacircle\OpggMcp\Tools\BestPlans;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Routing\McpRouteBuilder;

class ToolsTest extends TestCase
{
    public function test_the_endpoint_lists_four_read_only_tools_beside_the_apps_own(): void
    {
        $tools = $this->rpc('tools/list')['result']['tools'];

        $this->assertSame(
            ['hello-world', 'datacircle_list_apis', 'datacircle_best_plans', 'datacircle_search_plans', 'datacircle_provider'],
            array_column($tools, 'name'),
        );
        foreach (array_slice($tools, 1) as $tool) {
            $this->assertSame(['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true],
                array_diff_key($tool['annotations'], ['title' => true]));
            $this->assertSame($tool['title'], $tool['annotations']['title']);
            $this->assertStringContainsString('https://datacircle.dev', $tool['description']);
            $this->assertSame('object', $tool['inputSchema']['type']);
        }
        $this->assertSame('Who is best for an API', $tools[2]['title']);
        $this->assertSame(['slug'], $tools[2]['inputSchema']['required']);
        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'How many plans to give, 1 to 50. 10 when left out'],
            $tools[2]['inputSchema']['properties']['limit']);
        $this->assertSame(['score', 'provider', 'price'], $tools[3]['inputSchema']['properties']['sort']['enum']);
        $this->assertArrayNotHasKey('required', $tools[3]['inputSchema']);
    }

    public function test_list_apis_puts_the_most_words_matched_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/apis' => Http::response([
            ['api' => 'Employee phone', 'slug' => 'phone-finder', 'plans' => 3, 'providers' => 2, 'terms' => 'dropped'],
            ['api' => 'Employee email', 'slug' => 'email-finder', 'plans' => 5, 'providers' => 4],
            ['api' => 'Email validation', 'slug' => 'email-verification', 'plans' => 2, 'providers' => 2],
        ])]);

        $result = $this->structured($this->callTool('datacircle_list_apis', ['query' => 'email finder']));

        $this->assertSame(3, $result['total']);
        $this->assertSame(['email-finder', 'phone-finder', 'email-verification'], array_column($result['apis'], 'slug'));
        $this->assertArrayNotHasKey('terms', $result['apis'][1]);

        $this->assertCount(1, $this->structured($this->callTool('datacircle_list_apis', ['limit' => 1]))['apis']);
    }

    public function test_best_plans_sends_the_slug_and_limit_and_briefs_each_plan(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response([
            'api' => 'Employee email', 'slug' => 'email-finder', 'total' => 1,
            'plans' => [['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5, 'aggregators' => ['x']]],
        ])]);

        $result = $this->structured($this->callTool('datacircle_best_plans', ['slug' => 'Email Finder', 'limit' => '70']));

        $this->assertSame(['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5], $result['plans'][0]);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/apis/email-finder/best?limit=50'
            && str_starts_with($request->header('User-Agent')[0], 'opgg-laravel-mcp-datacircle/'));
    }

    public function test_a_missing_slug_asks_for_one_without_a_call(): void
    {
        Http::fake();

        $this->assertError("Give an API's slug", $this->callTool('datacircle_best_plans'));
        $this->assertError("Give a provider's slug", $this->callTool('datacircle_provider', ['slug' => ['x']]));
        Http::assertNothingSent();
    }

    public function test_an_unknown_slug_is_an_error_in_the_apis_words(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response(['error' => 'no API has the slug nope', 'slugs' => ['email-finder']], 404)]);

        $result = $this->callTool('datacircle_best_plans', ['slug' => 'nope']);

        $this->assertError('no API has the slug nope. datacircle_list_apis gives every API', $result);
        $this->assertError('no API has the slug nope', $this->callTool('datacircle_provider', ['slug' => 'nope']));
    }

    public function test_search_plans_takes_a_provider_sent_as_the_api_and_a_slug_sent_as_a_name(): void
    {
        Http::fake(function (HttpRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if (($query['api'] ?? '') !== '') {
                return Http::response(['error' => 'api must be one of: Employee email'], 400);
            }

            return ($query['query'] ?? '') === 'bright data'
                ? Http::response(['total' => 1, 'plans' => [['provider' => 'Bright Data', 'plan' => 'Pay as you go', 'unused' => 1]]])
                : Http::response(['total' => 0, 'plans' => []]);
        });

        $result = $this->structured($this->callTool('datacircle_search_plans', ['api' => 'bright-data', 'sort' => 'price', 'limit' => 2]));

        $this->assertSame(1, $result['total']);
        $this->assertSame([['provider' => 'Bright Data', 'plan' => 'Pay as you go']], $result['plans']);
        $this->assertStringContainsString('No API is named "bright-data"', $result['note']);
        $this->assertStringContainsString('whose name holds "bright data"', $result['note']);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/plans?query=bright%20data&sort=price&limit=2');
    }

    public function test_provider_gives_its_best_rank_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/providers/bright-data' => Http::response([
            'provider' => 'Bright Data', 'slug' => 'bright-data', 'apis' => [
                ['api' => 'SERP', 'slug' => 'serp', 'rank' => null, 'plans' => [['plan' => 'A', 'rank' => null, 'score' => null]]],
                ['api' => 'Web scraping', 'slug' => 'web-scraping', 'rank' => 4, 'plans' => [
                    ['plan' => 'B', 'rank' => 9, 'score' => 50], ['plan' => 'C', 'rank' => 4, 'score' => 60, 'perThousand' => 1.5],
                ]],
            ],
        ])]);

        $result = $this->structured($this->callTool('datacircle_provider', ['slug' => 'Bright Data']));

        $this->assertSame([2, 1], [$result['total'], $result['ranked']]);
        $this->assertSame(['web-scraping', 'serp'], array_column($result['apis'], 'slug'));
        $this->assertSame(['plan' => 'C', 'score' => 60, 'perThousand' => 1.5], $result['apis'][0]['best']);
        $this->assertSame(2, $result['apis'][0]['plans']);
    }

    public function test_a_server_error_and_a_failed_network_are_error_results_in_words(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response('', 503)]);
        $this->assertError('datacircle: 503', $this->callTool('datacircle_list_apis'));

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host'));
        $this->assertError('datacircle: Could not resolve host', $this->callTool('datacircle_search_plans', ['query' => 'x']));
    }

    public function test_a_429_is_waited_for_once(): void
    {
        Http::fakeSequence('api.datacircle.dev/*')
            ->push(['error' => 'slow down'], 429, ['Retry-After' => '1'])
            ->push(['api' => 'x', 'slug' => 'serp', 'total' => 0, 'plans' => []]);

        $this->assertSame('serp', $this->structured($this->callTool('datacircle_best_plans', ['slug' => 'serp']))['slug']);
    }

    public function test_a_bound_client_is_the_one_the_tools_call(): void
    {
        $this->app->bind(Client::class, fn () => new Client('https://example.test/v1/', timeout: 5));
        Http::fake(['example.test/*' => Http::response(['total' => 0, 'plans' => []])]);

        $this->structured($this->callTool('datacircle_search_plans'));

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://example.test/v1/plans');
        $this->assertInstanceOf(BestPlans::class, $this->app->make(BestPlans::class));
    }

    public function test_the_tool_api_answers_with_the_result_alone(): void
    {
        if (! method_exists(McpRouteBuilder::class, 'enabledApi')) {
            $this->markTestSkipped('No tool API before laravel-mcp-server 2.0.5');
        }
        Http::fake(['api.datacircle.dev/*' => Http::response(['api' => 'x', 'slug' => 'serp', 'total' => 0, 'plans' => []])]);

        $this->postJson('/tools/datacircle_best_plans?slug=serp&limit=2')->assertOk()->assertJsonPath('slug', 'serp');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/apis/serp/best?limit=2');
    }
}
