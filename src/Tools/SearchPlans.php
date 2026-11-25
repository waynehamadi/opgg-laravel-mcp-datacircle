<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tools;

use Datacircle\OpggMcp\ApiError;
use Datacircle\OpggMcp\Client;

/**
 * datacircle_search_plans: GET /plans.
 */
class SearchPlans extends DatacircleTool
{
    public const NAME = 'datacircle_search_plans';

    public function name(): string
    {
        return self::NAME;
    }

    public function title(): string
    {
        return 'Search the plans';
    }

    public function description(): string
    {
        return "Searches every plan on Datacircle's sheet (https://datacircle.dev), or one API's, by provider or plan name, sorted by score, provider or price. Gives how many were found and up to limit of them. perThousand is a plan's price of 1,000 items in dollars, pay the plan's whole price in its currency.";
    }

    public function inputSchema(): array
    {
        return self::schema([
            'api' => ['type' => 'string', 'description' => "Only this API's plans: its name or slug, like email-finder. A provider's name goes in query, never here. Every API when left out"],
            'query' => ['type' => 'string', 'description' => "Words that must all appear in the provider's or plan's name, like bright data"],
            'sort' => ['type' => 'string', 'enum' => ['score', 'provider', 'price'], 'description' => 'score (best first, the default), provider (A to Z) or price ($ per 1,000, lowest first)'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'How many plans to give, 1 to 50. 10 when left out'],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'How many found plans to skip first. 0 when left out'],
        ]);
    }

    protected function call(array $arguments): array
    {
        $api = self::text($arguments['api'] ?? null);
        $query = self::text($arguments['query'] ?? null);
        $sort = self::text($arguments['sort'] ?? null);
        $limit = $arguments['limit'] ?? null;
        $offset = $arguments['offset'] ?? null;
        $note = '';

        try {
            $answer = $this->search($api, $query, $sort, $limit, $offset);
        } catch (ApiError $e) {
            if ($e->status !== 400 || ! str_contains($e->getMessage(), 'api must be') || $query !== '') {
                throw $e;
            }
            // A provider's name sent as the API: the plans whose provider or plan name holds it.
            $note = 'No API is named "'.$api.'": these are the plans whose provider or plan name holds it.';
            [$api, $query] = ['', $api];
            $answer = $this->search($api, $query, $sort, $limit, $offset);
        }

        if (($answer['total'] ?? null) === 0 && str_contains($query, '-')) {
            // A slug sent as a name, like bright-data, which no name holds: its words, like bright data. A name with a
            // hyphen, like A-Leads, is found as it is first.
            $words = implode(' ', preg_split('/[\s-]+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            try {
                $answer = $this->search($api, $words, $sort, $limit, $offset);
                $note = trim($note.' No provider\'s or plan\'s name holds "'.$query.'": these are the plans whose name holds "'.$words.'".');
            } catch (ApiError) {
            }
        }
        if ($note !== '') {
            $answer['note'] = $note;
        }
        $answer['plans'] = Client::briefPlans($answer['plans'] ?? []);

        return $answer;
    }

    /**
     * @return array<mixed>
     */
    private function search(string $api, string $query, string $sort, mixed $limit, mixed $offset): array
    {
        $params = array_filter([
            'api' => $api,
            'query' => $query,
            'sort' => in_array($sort, ['score', 'provider', 'price'], true) ? $sort : null,
            'limit' => Client::number($limit, 1, 50),
            'offset' => Client::number($offset, 1, PHP_INT_MAX),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->client->get('/plans', $params);
    }
}
