<?php
/**
 * A small, polite client for the Transitous (MOTIS) API: geocode, stop times, trip planning.
 *
 * Designed for light fallback use: every answer is cached, the number of real requests per day is capped,
 * and every request carries a User-Agent with a contact address. No dependencies, PHP 7.4 or newer.
 *
 * @license MIT
 */
final class TransitousClient
{
    public const BASE = 'https://api.transitous.org';

    /** @var string */ private $userAgent;
    /** @var int */ private $dailyLimit;
    /** @var object|null */ private $cache;
    /** @var string */ private $base;
    /** @var callable|null */ private $http;

    /**
     * @param string        $userAgent  Product name and contact address, for example "my-app/1.0 (contact me@example.org)".
     * @param int           $dailyLimit Hard cap of real requests per calendar day (UTC). Cached answers do not count.
     * @param object|null   $cache      Anything with get(string $key): mixed|null and set(string $key, $value, int $ttlSeconds): void.
     * @param callable|null $http       fn(string $url, string $userAgent): array [int $status, string $body] (for tests).
     */
    public function __construct(string $userAgent, int $dailyLimit = 50, $cache = null, string $base = self::BASE, ?callable $http = null)
    {
        if (strpos($userAgent, '@') === false && stripos($userAgent, 'http') === false) {
            throw new InvalidArgumentException('The User-Agent must contain a contact address or URL.');
        }
        $this->userAgent = $userAgent;
        $this->dailyLimit = max(1, $dailyLimit);
        $this->cache = $cache;
        $this->base = rtrim($base, '/');
        $this->http = $http;
    }

    /** Address or place search. Returns the decoded MOTIS answer, or null with $error set. */
    public function geocode(string $text, ?string $nearLatLon = null, int $cacheSeconds = 604800, ?string &$error = null): ?array
    {
        $q = ['text' => $text, 'language' => 'de'];
        if ($nearLatLon !== null) $q['place'] = $nearLatLon;
        return $this->get('geocode', $q, $cacheSeconds, $error);
    }

    /** Next departures at a stop. */
    public function stopTimes(string $stopId, int $count = 14, int $cacheSeconds = 60, ?string &$error = null): ?array
    {
        return $this->get('stoptimes', ['stopId' => $stopId, 'n' => $count], $cacheSeconds, $error);
    }

    /** Trip plan between two places ("lat,lon" or stop ids). $timeUtc like 2026-10-19T07:30:00Z. */
    public function plan(string $from, string $to, ?string $timeUtc = null, bool $arriveBy = false, int $itineraries = 4, int $cacheSeconds = 604800, ?string &$error = null): ?array
    {
        $q = ['fromPlace' => $from, 'toPlace' => $to, 'numItineraries' => $itineraries];
        if ($timeUtc !== null) $q['time'] = $timeUtc;
        if ($arriveBy) $q['arriveBy'] = 'true';
        return $this->get('plan', $q, $cacheSeconds, $error);
    }

    /** Real requests made today (UTC). */
    public function requestsToday(): int
    {
        $n = $this->cache ? $this->cache->get($this->counterKey()) : null;
        return is_numeric($n) ? (int)$n : 0;
    }

    private function counterKey(): string
    {
        return 'transitous_requests_' . gmdate('Ymd');
    }

    private function get(string $endpoint, array $query, int $cacheSeconds, ?string &$error): ?array
    {
        $error = null;
        ksort($query);
        $qs = http_build_query($query);
        $key = 'transitous_' . md5($endpoint . '?' . $qs);
        if ($this->cache && $cacheSeconds > 0) {
            $hit = $this->cache->get($key);
            if (is_array($hit)) return $hit;
        }
        // A broken counter must never block users: if the cache cannot be read, the limit does not apply.
        if ($this->cache && $this->requestsToday() >= $this->dailyLimit) {
            $error = 'daily limit reached';
            return null;
        }
        if ($this->cache) $this->cache->set($this->counterKey(), $this->requestsToday() + 1, 172800);
        [$status, $body] = $this->fetch($this->base . '/api/v1/' . $endpoint . '?' . $qs);
        if ($status === 0) { $error = 'connection failed'; return null; }
        if ($status >= 400) { $error = 'service answered with status ' . $status; return null; }
        $data = json_decode($body, true);
        if (!is_array($data)) { $error = 'answer not readable'; return null; }
        if ($this->cache && $cacheSeconds > 0) $this->cache->set($key, $data, $cacheSeconds);
        return $data;
    }

    /** @return array{0:int,1:string} */
    private function fetch(string $url): array
    {
        if ($this->http) return ($this->http)($url, $this->userAgent);
        if (!function_exists('curl_init')) return [0, ''];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ' . $this->userAgent],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $failed = $body === false || curl_errno($ch) !== 0;
        if ($failed) return [0, ''];
        return [$status, (string)$body];
    }
}

/** Minimal in-memory cache with the interface the client expects. Replace it with APCu, Redis or a database. */
final class ArrayCache
{
    private $d = [];
    public function get(string $key) { return isset($this->d[$key]) && $this->d[$key][1] > time() ? $this->d[$key][0] : null; }
    public function set(string $key, $value, int $ttl): void { $this->d[$key] = [$value, time() + $ttl]; }
}
