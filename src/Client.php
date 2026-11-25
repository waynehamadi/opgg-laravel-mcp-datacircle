<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Calls Datacircle's open API, https://api.datacircle.dev/v1 (its spec: https://api.datacircle.dev/v1/openapi.json).
 * It needs no key. More at https://datacircle.dev
 */
class Client
{
    public const BASE_URL = 'https://api.datacircle.dev/v1';

    /** This package's version, sent in each call's User-Agent. */
    public const VERSION = '0.1.0';

    /** A plan holds about 90 keys (its terms of use, its aggregators...): these choose a plan, the rest is on its page. */
    public const PLAN_KEYS = ['api', 'rank', 'provider', 'plan', 'score', 'perThousand', 'notFoundPerThousand', 'billing', 'pay',
        'currency', 'units', 'unit', 'strengths', 'latencyMs', 'page', 'pricingPage', 'docs'];

    public readonly string $baseUrl;

    /**
     * @param  string|null  $baseUrl  Where the tools call. BASE_URL when left out.
     * @param  int  $timeout  Seconds a call may take.
     */
    public function __construct(?string $baseUrl = null, public readonly int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl ?? self::BASE_URL, '/');
    }

    /**
     * Calls one path and gives its JSON. A 429 is waited for once, a minute at most.
     *
     * @param  array<string, string|int>  $query
     * @return array<mixed>
     *
     * @throws ApiError an answer of 4xx, with the API's own words
     * @throws RuntimeException any other failure
     */
    public function get(string $path, array $query = []): array
    {
        for ($try = 0; ; $try++) {
            try {
                $response = Http::acceptJson()
                    ->withUserAgent('opgg-laravel-mcp-datacircle/'.self::VERSION)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl.$path, $query);
            } catch (ConnectionException $e) {
                throw new RuntimeException('datacircle: '.$e->getMessage(), 0, $e);
            }

            $status = $response->status();
            if ($status === 429 && $try === 0) {
                $wait = (int) $response->header('Retry-After');
                sleep($wait >= 1 && $wait <= 60 ? $wait : 60);

                continue;
            }
            if ($status >= 400 && $status < 500) {
                $error = $response->json('error');

                throw new ApiError($status, is_string($error) && $error !== '' ? $error : $status.' '.$response->reason());
            }
            if ($status !== 200) {
                throw new RuntimeException('datacircle: '.$status.' '.$response->reason());
            }
            $json = $response->json();
            if (! is_array($json)) {
                throw new RuntimeException('datacircle: the answer is not JSON');
            }

            return $json;
        }
    }

    /**
     * The API's rule for a slug: the name in lowercase letters and digits, the rest a hyphen.
     */
    public static function slugify(?string $name): string
    {
        $ascii = strtolower(Str::ascii(trim((string) $name)));

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function brief(array $item, array $keys): array
    {
        $kept = [];
        foreach ($keys as $key) {
            if (isset($item[$key])) {
                $kept[$key] = $item[$key];
            }
        }

        return $kept;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function plans(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [], is_array(...)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function briefPlans(mixed $value): array
    {
        return array_map(fn (array $plan): array => self::brief($plan, self::PLAN_KEYS), self::plans($value));
    }

    /**
     * A number a model sent (10, 10.0 or "10"), between low and high; null when it sent none.
     */
    public static function number(mixed $value, int $low, int $high): ?int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return max($low, min($high, (int) $value));
    }
}
