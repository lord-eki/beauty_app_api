<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'description' => $this->description,
            'website' => $this->website,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'whatsapp' => $this->whatsapp,
            'business_hours' => is_array($this->business_hours) ? $this->business_hours : [],
            'average_rating' => (float) ($this->average_rating ?? 0),
            'total_reviews' => $this->total_reviews,
            'is_verified' => $this->is_verified,
            // Only present when the controller loaded `subscriptions`
            // (own profile) — avoids N+1 queries on public lists.
            $this->mergeWhen(
                $this->resource->relationLoaded('subscriptions'),
                fn () => $this->subscriptionPayload()
            ),
            $this->mergeWhen(
                $this->resource->relationLoaded('availabilities'),
                fn () => $this->openingPayload()
            ),
            'user' => new UserResource($this->whenLoaded('user')),
            'locations' => BusinessLocationResource::collection($this->whenLoaded('locations')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * "Open today 09:00–17:00" and "Mon–Sat 09:00–17:00" style summaries,
     * built from the provider's weekly schedule (day_of_week: 0 = Sunday).
     */
    private function openingPayload(): array
    {
        $fmt = fn ($t) => substr((string) $t, 0, 5);

        // day => "09:00–17:00" (first start to last end), only available days
        $byDay = [];
        foreach ($this->availabilities->where('is_available', true)->groupBy('day_of_week') as $day => $slots) {
            $start = $slots->min('start_time');
            $end   = $slots->max('end_time');
            $byDay[(int) $day] = $fmt($start) . '–' . $fmt($end);
        }

        $today = now('Africa/Nairobi')->dayOfWeek;

        // Compress runs of consecutive days that share the same hours.
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
        $order = [1, 2, 3, 4, 5, 6, 0];
        $parts = [];
        $i = 0;
        while ($i < 7) {
            $d = $order[$i];
            if (! isset($byDay[$d])) { $i++; continue; }
            $j = $i;
            while ($j + 1 < 7 && ($byDay[$order[$j + 1]] ?? null) === $byDay[$d]) { $j++; }
            $label = $i === $j ? $names[$d] : $names[$d] . '–' . $names[$order[$j]];
            $parts[] = $label . ' ' . $byDay[$d];
            $i = $j + 1;
        }

        return [
            'open_today'   => isset($byDay[$today]),
            'today_hours'  => $byDay[$today] ?? null,
            'weekly_hours' => $parts ? implode(', ', $parts) : null,
        ];
    }

    private function subscriptionPayload(): array
    {
        $subs   = $this->subscriptions;
        $active = $subs
            ->where('status', 'active')
            ->filter(fn ($s) => $s->end_date && $s->end_date->copy()->endOfDay()->isFuture())
            ->sortByDesc('end_date')
            ->first();
        $latest = $active ?? $subs->sortByDesc('end_date')->first();

        $status = 'none';
        if ($active) {
            $status = 'active';
        } elseif ($latest) {
            $status = $latest->status === 'active' ? 'expired' : $latest->status;
        }

        return [
            'subscription_status'     => $status,
            'subscription_end_date'   => $latest?->end_date?->toDateString(),
            'has_active_subscription' => (bool) $active,
        ];
    }
}