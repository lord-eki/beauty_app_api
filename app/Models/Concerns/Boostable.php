<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Boostable
{

    public function scopeBoostedFirst(Builder $query, int $seed = 0): Builder
    {
        $t = $this->getTable();

        $isBoosted = "EXISTS ( SELECT 1 FROM subscriptions sub WHERE sub.business_profile_id = {$t}.business_profile_id AND sub.status = 'active' AND sub.end_date >= CURDATE())";

        return $query
            ->addSelect(DB::raw("{$isBoosted} AS is_boosted"))
            ->orderByDesc('is_boosted')
            ->orderByRaw("CASE WHEN {$isBoosted} THEN MD5(CONCAT(?, {$t}.id)) ELSE '' END", [$seed])
            ->orderByDesc("{$t}.created_at");
    }
}
