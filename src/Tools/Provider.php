<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tools;

use Datacircle\OpggMcp\Client;

/**
 * datacircle_provider: GET /providers/{slug}.
 */
class Provider extends DatacircleTool
{
    public const NAME = 'datacircle_provider';

    public function name(): string
    {
        return self::NAME;
    }

    public function title(): string
    {
        return 'One provider on every API';
    }

    public function description(): string
    {
        return "Gives each API one provider sells and its rank on each on Datacircle's benchmark (https://datacircle.dev), with its best plan there, its best rank first. total is how many APIs it sells, ranked on how many of them it has a rank.";
    }

    public function inputSchema(): array
    {
        return self::schema([
            'slug' => ['type' => 'string', 'description' => "The provider's slug, like bright-data, or its name, like Bright Data: the name is made a slug"],
        ], ['slug']);
    }

    protected function call(array $arguments): array
    {
        $slug = Client::slugify(self::text($arguments['slug'] ?? null));
        if ($slug === '') {
            return self::error("Give a provider's slug or name, like bright-data or Bright Data");
        }

        $answer = $this->client->get('/providers/'.$slug);

        $apis = [];
        foreach (Client::plans($answer['apis'] ?? []) as $index => $entry) {
            $plans = Client::plans($entry['plans'] ?? []);
            $kept = Client::brief($entry, ['api', 'slug', 'rank', 'bestPage']);
            $kept['plans'] = count($plans);
            $best = self::bestPlan($plans);
            if ($best !== null) {
                $kept['best'] = Client::brief($best, ['plan', 'score', 'perThousand', 'page']);
            }
            $apis[] = [$index, $kept];
        }
        // Its best rank first, the APIs where it has none last, in the API's order.
        usort($apis, function (array $a, array $b): int {
            $rankA = is_numeric($a[1]['rank'] ?? null) ? $a[1]['rank'] : INF;
            $rankB = is_numeric($b[1]['rank'] ?? null) ? $b[1]['rank'] : INF;

            return [$rankA, $a[0]] <=> [$rankB, $b[0]];
        });
        $apis = array_column($apis, 1);

        unset($answer['apis']);
        // How many it sells and is ranked on before the list, so that a model reads them first.
        $answer['total'] = count($apis);
        $answer['ranked'] = count(array_filter($apis, fn (array $api): bool => is_numeric($api['rank'] ?? null)));
        $answer['apis'] = $apis;

        return $answer;
    }

    /**
     * A provider's best plan on an API: its lowest rank, a plan with no rank having none.
     *
     * @param  list<array<string, mixed>>  $plans
     * @return array<string, mixed>|null
     */
    private static function bestPlan(array $plans): ?array
    {
        $best = null;
        foreach ($plans as $plan) {
            if (is_numeric($plan['rank'] ?? null) && ($best === null || $plan['rank'] < $best['rank'])) {
                $best = $plan;
            }
        }

        return $best ?? $plans[0] ?? null;
    }
}
