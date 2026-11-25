# Datacircle tools for OP.GG's Laravel MCP Server

Find the best GTM APIs. We benchmark all the GTM APIs and we put the results on our website.

https://datacircle.dev

This package gives an [OP.GG Laravel MCP Server](https://github.com/opgginc/laravel-mcp-server) endpoint four tools that call Datacircle's public API, https://api.datacircle.dev/v1. You need no key and no account.

| Tool | What the tool gives | Call |
| --- | --- | --- |
| `datacircle_list_apis` | Every GTM API on Datacircle, with the API's slug, number of plans, number of providers and best plan | `GET /apis` |
| `datacircle_best_plans` | One API's plans, sorted by score out of 100, best first. Each plan comes with a rank and a price per 1,000 calls | `GET /apis/{slug}/best` |
| `datacircle_search_plans` | Plans that match a provider name or plan name. Search all APIs or one API. Sort the plans by score, provider or price | `GET /plans` |
| `datacircle_provider` | The APIs a provider sells, and the provider's rank on each API | `GET /providers/{slug}` |

## Install

```bash
composer require datacircle/opgg-laravel-mcp-tools
```

The package needs PHP 8.2 or later, Laravel 12 or 13 and `opgginc/laravel-mcp-server` 2.0 or later.

## Add the tools to an endpoint

`Datacircle::TOOLS` lists the four tool classes. Put them in your endpoint's tools, next to your own:

```php
use App\MCP\Tools\MyCustomTool;
use Datacircle\OpggMcp\Datacircle;
use Illuminate\Support\Facades\Route;

Route::mcp('/mcp')
    ->setServerInfo(name: 'My MCP Server', version: '1.0.0')
    ->tools([
        MyCustomTool::class,
        ...Datacircle::TOOLS,
    ]);
```

To add some of the tools, list each one yourself:

```php
use Datacircle\OpggMcp\Tools\BestPlans;
use Datacircle\OpggMcp\Tools\Provider;

Route::mcp('/mcp/gtm')
    ->setServerInfo(name: 'GTM APIs', version: '1.0.0')
    ->tools([
        BestPlans::class,
        Provider::class,
    ]);
```

MCP clients send POST requests to the endpoint. Register the endpoint in a routes file without CSRF checks, like `routes/api.php`. In `routes/api.php`, the endpoint answers at `/api/mcp`.

## Test a tool

```bash
php artisan mcp:test-tool --list --endpoint=/mcp
php artisan mcp:test-tool datacircle_best_plans --input='{"slug":"email-finder","limit":3}'
```

You can also call each tool at `POST /api/tools/{tool_name}`. Add `->enabledApi()` to the endpoint for this. The tool API needs `opgginc/laravel-mcp-server` 2.0.5 or later. Call a tool like this:

```bash
curl -X POST "http://localhost:8000/api/tools/datacircle_best_plans?slug=email-finder&limit=3"
```

## Tool results

Each tool returns a JSON object, as structured content and as text. `datacircle_best_plans` and `datacircle_search_plans` return plans. Each plan has a rank, provider, plan name, score, price per 1,000 (`perThousand`, in dollars) and a page on datacircle.dev. The page on datacircle.dev lists every field.

An API error, like an unknown slug, gives an error result (`isError`) with the API's own message. A network failure gives an error result too. The model can then call again.

The tools are read-only and idempotent. They call through Laravel's HTTP client. In your tests, `Http::fake()` fakes the tool calls.

`Client` sets the API address and the timeout in seconds. The timeout is 30 seconds by default. To change the timeout, bind a client in a service provider:

```php
use Datacircle\OpggMcp\Client;

$this->app->bind(Client::class, fn () => new Client(timeout: 10));
```

## Network

Each tool call makes one HTTPS GET request to https://api.datacircle.dev/v1 and nothing else. The request holds only the tool's own arguments: no key and nothing from your app or your app's users. The API takes up to 60 calls a minute from one IP address. The tool waits once, for a minute at most, when the API asks the tool to wait. The package stores nothing.

## Links

- Datacircle: https://datacircle.dev
- The API: https://datacircle.dev/api
- The API's spec: https://api.datacircle.dev/v1/openapi.json
- What each key means: https://datacircle.dev/fields

MIT license.
