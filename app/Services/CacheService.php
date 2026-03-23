<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    public static function rememberBusiness(int $id, callable $callback)
    {
        return Cache::remember("business:profile:{$id}", 3600, $callback);
    }

    public static function rememberBusinessServices(int $id, callable $callback)
    {
        return Cache::remember("business:{$id}:services", 1800, $callback);
    }

    public static function rememberBusinessProducts(int $id, callable $callback)
    {
        return Cache::remember("business:{$id}:products", 1800, $callback);
    }

    public static function rememberAvailabilitySlots(int $businessId, string $date, callable $callback)
    {
        return Cache::remember("business:{$businessId}:slots:{$date}", 900, $callback);
    }

    public static function rememberCategories(callable $callback)
    {
        return Cache::remember('categories:all', 86400, $callback);
    }

    public static function rememberStatistics(int $businessId, string $period, callable $callback)
    {
        return Cache::remember("business:{$businessId}:stats:{$period}", 600, $callback);
    }

    public static function rememberSearchResults(string $type, array $params, callable $callback)
    {
        $key = "search:{$type}:" . md5(serialize($params));
        return Cache::remember($key, 600, $callback);
    }

    public static function rememberNearbyLocations(float $lat, float $lng, int $radius, callable $callback)
    {
        $latR = round($lat, 3);
        $lngR = round($lng, 3);
        return Cache::remember("locations:nearby:{$latR}:{$lngR}:{$radius}", 900, $callback);
    }

    // Invalidation helpers
    public static function forgetBusiness(int $id): void
    {
        Cache::forget("business:profile:{$id}");
        Cache::forget("business:{$id}:services");
        Cache::forget("business:{$id}:products");
        Cache::forget("business:{$id}:staff:public");
        Cache::forget("business:{$id}:promotions:active");
    }

    public static function forgetBusinessStats(int $id): void
    {
        foreach (['today', 'week', 'month', 'year'] as $period) {
            Cache::forget("business:{$id}:stats:{$period}");
        }
    }

    public static function forgetAvailability(int $businessId, string $date): void
    {
        Cache::forget("business:{$businessId}:slots:{$date}");
    }

    public static function forgetSearchResults(): void
    {
        $redis = Cache::getRedis();
        $prefix = config('cache.prefix');
        $keys = $redis->keys("{$prefix}search:*");
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }
}