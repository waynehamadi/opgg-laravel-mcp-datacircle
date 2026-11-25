<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tools;

use Datacircle\OpggMcp\Client;

/**
 * datacircle_list_apis: GET /apis.
 */
class ListApis extends DatacircleTool
{
    public const NAME = 'datacircle_list_apis';

    public function name(): string
    {
        return self::NAME;
    }

    public function title(): string
    {
        return 'List the GTM APIs';
    }

    public function description(): string
    {
        return "Lists the GTM APIs on Datacircle's sheet (https://datacircle.dev), with each API's slug, how many plans and providers it has and its best plan. Call it first to find an API's slug.";
    }

    public function inputSchema(): array
    {
        return self::schema([
            'query' => ['type' => 'string', 'description' => "Words of the API's name or slug, like email or linkedin profile: the APIs that hold the most of them come first. The sheet's order when left out"],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'description' => 'How many APIs to give, 1 to 200. 20 when left out: the sheet has about 170'],
        ]);
    }

    protected function call(array $arguments): array
    {
        $apis = Client::plans($this->client->get('/apis'));

        $words = preg_split('/\s+/', strtolower(str_replace('-', ' ', self::text($arguments['query'] ?? null))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $found = [];
        foreach ($apis as $index => $api) {
            $name = strtolower((string) ($api['api'] ?? '').' '.str_replace('-', ' ', (string) ($api['slug'] ?? '')));
            $matched = count(array_filter($words, fn (string $word): bool => str_contains($name, $word)));
            if ($matched > 0 || $words === []) {
                $found[] = [$matched, $index, Client::brief($api, ['api', 'slug', 'plans', 'providers', 'best', 'bestPage', 'sheetPage'])];
            }
        }
        // The most words matched first, the sheet's order within each.
        usort($found, fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return [
            'total' => count($found),
            'apis' => array_column(array_slice($found, 0, Client::number($arguments['limit'] ?? null, 1, 200) ?? 20), 2),
        ];
    }
}
