<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp\Tools;

use Datacircle\OpggMcp\ApiError;
use Datacircle\OpggMcp\Client;

/**
 * datacircle_best_plans: GET /apis/{slug}/best.
 */
class BestPlans extends DatacircleTool
{
    public const NAME = 'datacircle_best_plans';

    public function name(): string
    {
        return self::NAME;
    }

    public function title(): string
    {
        return 'Who is best for an API';
    }

    public function description(): string
    {
        return "Gives an API's plans best first by their score out of 100 on Datacircle's benchmark (https://datacircle.dev), each with its rank, provider, plan, $ per 1,000 calls and its page. perThousand is a plan's price of 1,000 items in dollars, pay the plan's whole price in its currency.";
    }

    public function inputSchema(): array
    {
        return self::schema([
            'slug' => ['type' => 'string', 'description' => "The API's slug, like email-finder or linkedin-profile, as datacircle_list_apis gives it"],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'How many plans to give, 1 to 50. 10 when left out'],
        ], ['slug']);
    }

    protected function call(array $arguments): array
    {
        $slug = Client::slugify(self::text($arguments['slug'] ?? null));
        if ($slug === '') {
            return self::error("Give an API's slug, like email-finder: datacircle_list_apis gives every slug");
        }
        $limit = Client::number($arguments['limit'] ?? null, 1, 50);

        try {
            $answer = $this->client->get('/apis/'.$slug.'/best', $limit === null ? [] : ['limit' => $limit]);
        } catch (ApiError $e) {
            // A provider's name sent as the API's slug: the API's words, and where a provider's plans are.
            return self::error($e->getMessage().($e->status === 404
                ? '. datacircle_list_apis gives every API\'s slug, and datacircle_search_plans finds a provider\'s plans by its name as query.'
                : ''));
        }
        $answer['plans'] = Client::briefPlans($answer['plans'] ?? []);

        return $answer;
    }
}
