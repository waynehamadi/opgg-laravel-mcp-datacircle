<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp;

use Datacircle\OpggMcp\Tools\BestPlans;
use Datacircle\OpggMcp\Tools\ListApis;
use Datacircle\OpggMcp\Tools\Provider;
use Datacircle\OpggMcp\Tools\SearchPlans;

/**
 * Datacircle's four tools for OP.GG's Laravel MCP Server: find the best GTM APIs. https://datacircle.dev
 */
final class Datacircle
{
    /**
     * datacircle_list_apis, datacircle_best_plans, datacircle_search_plans and datacircle_provider, for an endpoint's
     * tools: Route::mcp('/mcp')->tools([...Datacircle::TOOLS]);
     */
    public const TOOLS = [
        ListApis::class,
        BestPlans::class,
        SearchPlans::class,
        Provider::class,
    ];
}
