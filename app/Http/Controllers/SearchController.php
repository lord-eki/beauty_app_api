<?php

namespace App\Http\Controllers;

use App\Http\Resources\BusinessProfileResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Models\BusinessProfile;
use App\Models\Product;
use App\Models\Service;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // -------------------------------------------------------------------------
    // Haversine formula fragment — reused across all three geo searches
    // 6371 = Earth radius in km
    // -------------------------------------------------------------------------
    private const GEO_SELECT = '(6371 * acos(
        cos(radians(?)) * cos(radians(business_locations.latitude))
        * cos(radians(business_locations.longitude) - radians(?))
        + sin(radians(?)) * sin(radians(business_locations.latitude))
    )) AS distance';

    // =========================================================================
    // GET /api/search/services
    // =========================================================================

    /**
     * Search for services.
     *
     * Query params:
     *   query        - text search (uses FULLTEXT index)
     *   category     - category_id
     *   min_price    - price_min >=
     *   max_price    - price_max <=
     *   home_service - boolean
     *   lat, lng     - enable geo-filter
     *   radius       - km, default 10
     *   sort_by      - created_at | price | relevance (default: relevance when query present, else created_at)
     *   sort_order   - asc | desc
     *   per_page     - max 20
     */
    public function services(Request $request): JsonResponse
    {
        $request->validate([
            'min_price'    => 'nullable|numeric|min:0',
            'max_price'    => 'nullable|numeric|min:0',
            'lat'          => 'nullable|numeric|between:-90,90',
            'lng'          => 'nullable|numeric|between:-180,180',
            'radius'       => 'nullable|numeric|min:1|max:200',
            'home_service' => 'nullable|boolean',
            'per_page'     => 'nullable|integer|min:1|max:20',
            'sort_order'   => 'nullable|in:asc,desc',
        ]);

        $params = $request->only([
            'query','category','min_price','max_price',
            'home_service','lat','lng','radius',
            'sort_by','sort_order','per_page','page',
        ]);

        $results = CacheService::rememberSearchResults('svc', $params, function () use ($request) {

            $hasGeo   = $request->filled('lat') && $request->filled('lng');
            $hasQuery = $request->filled('query');

            // ------------------------------------------------------------------
            // Base query — select only columns actually used in ServiceResource
            // ------------------------------------------------------------------
            $query = Service::query()
                ->active()
                ->select([
                    'services.id',
                    'services.business_profile_id',
                    'services.category_id',
                    'services.name',
                    'services.description',
                    'services.price_min',
                    'services.price_max',
                    'services.duration_minutes',
                    'services.images',
                    'services.is_home_service',
                    'services.created_at',
                ])
                ->with([
                    'businessProfile:id,business_name,average_rating,is_verified',
                    'category:id,name,slug',
                ]);

            // ------------------------------------------------------------------
            // FULLTEXT search — uses idx_service_search (name, description)
            // Falls back to LIKE only if search term is too short for FULLTEXT
            // ------------------------------------------------------------------
            if ($hasQuery) {
                $term = trim($request->query);
                if (strlen($term) >= 3) {
                    $query->whereRaw(
                        'MATCH(services.name, services.description) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    );
                } else {
                    // Short terms (1-2 chars) — FULLTEXT won't match, use LIKE
                    $query->where(function ($q) use ($term) {
                        $q->where('services.name', 'like', "{$term}%")
                          ->orWhere('services.description', 'like', "{$term}%");
                    });
                }
            }

            // ------------------------------------------------------------------
            // Filters
            // ------------------------------------------------------------------
            if ($request->filled('category')) {
                $query->where('services.category_id', (int) $request->category);
            }

            if ($request->filled('min_price')) {
                $query->where('services.price_min', '>=', (float) $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('services.price_max', '<=', (float) $request->max_price);
            }

            if ($request->filled('home_service')) {
                $query->where('services.is_home_service', $request->boolean('home_service'));
            }

            // ------------------------------------------------------------------
            // Geo filter — join business_locations, compute distance, filter
            // Uses: services -> business_profiles -> business_locations
            // ------------------------------------------------------------------
            if ($hasGeo) {
                $lat    = (float) $request->lat;
                $lng    = (float) $request->lng;
                $radius = (float) $request->input('radius', 10);

                $query
                    ->join('business_profiles as bp',
                        'services.business_profile_id', '=', 'bp.id')
                    ->join('business_locations',
                        'business_locations.business_profile_id', '=', 'bp.id')
                    ->where('business_locations.is_active', true)
                    ->addSelect(\DB::raw(
                        str_replace('?', '?', self::GEO_SELECT)
                    ))
                    ->addBinding([$lat, $lng, $lat], 'select')
                    ->having('distance', '<=', $radius)
                    ->orderBy('distance');
            }

            // ------------------------------------------------------------------
            // Sorting
            // ------------------------------------------------------------------
            $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
            $sortBy    = $request->input('sort_by', $hasQuery ? 'relevance' : 'created_at');

            if ($sortBy === 'price') {
                $query->orderBy('services.price_min', $sortOrder);
            } elseif ($sortBy !== 'relevance') {
                // relevance is already ordered by MySQL FULLTEXT score above
                $query->orderBy('services.created_at', $sortOrder);
            }

            return $query->paginate((int) $request->input('per_page', 15));
        });

        return $this->paginatedResponse(ServiceResource::collection($results), $results);
    }

    // =========================================================================
    // GET /api/search/products
    // =========================================================================

    /**
     * Search for products.
     *
     * Query params:
     *   query      - text search (FULLTEXT across name, description, brand)
     *   category   - category_id
     *   brand      - exact brand match
     *   min_price  - price >=
     *   max_price  - price <=
     *   in_stock   - boolean
     *   lat, lng   - geo-filter
     *   radius     - km
     *   sort_by    - created_at | price | relevance
     *   per_page   - max 20
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'min_price'  => 'nullable|numeric|min:0',
            'max_price'  => 'nullable|numeric|min:0',
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
            'radius'     => 'nullable|numeric|min:1|max:200',
            'in_stock'   => 'nullable|boolean',
            'per_page'   => 'nullable|integer|min:1|max:20',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $params = $request->only([
            'query','category','brand','min_price','max_price',
            'in_stock','lat','lng','radius',
            'sort_by','sort_order','per_page','page',
        ]);

        $results = CacheService::rememberSearchResults('prd', $params, function () use ($request) {

            $hasGeo   = $request->filled('lat') && $request->filled('lng');
            $hasQuery = $request->filled('query');

            $query = Product::query()
                ->active()
                ->select([
                    'products.id',
                    'products.business_profile_id',
                    'products.category_id',
                    'products.name',
                    'products.description',
                    'products.brand',
                    'products.price',
                    'products.discounted_price',
                    'products.stock_quantity',
                    'products.images',
                    'products.created_at',
                ])
                ->with([
                    'businessProfile:id,business_name,average_rating,is_verified',
                    'category:id,name,slug',
                ]);

       
            if ($hasQuery) {
                $term = trim($request->query);
                if (strlen($term) >= 3) {
                    $query->whereRaw(
                        'MATCH(products.name, products.description, products.brand) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    );
                } else {
                    $query->where(function ($q) use ($term) {
                        $q->where('products.name', 'like', "{$term}%")
                          ->orWhere('products.brand', 'like', "{$term}%");
                    });
                }
            }

            // ------------------------------------------------------------------
            // Filters
            // ------------------------------------------------------------------
            if ($request->filled('category')) {
                $query->where('products.category_id', (int) $request->category);
            }

            if ($request->filled('brand')) {
                $query->where('products.brand', $request->brand);
            }

            if ($request->filled('min_price')) {
                $query->where('products.price', '>=', (float) $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('products.price', '<=', (float) $request->max_price);
            }

            if ($request->boolean('in_stock')) {
                $query->inStock(); // uses your existing scope
            }

            // ------------------------------------------------------------------
            // Geo filter — products -> business_profiles -> business_locations
            // ------------------------------------------------------------------
            if ($hasGeo) {
                $lat    = (float) $request->lat;
                $lng    = (float) $request->lng;
                $radius = (float) $request->input('radius', 10);

                $query
                    ->join('business_profiles as bp',
                        'products.business_profile_id', '=', 'bp.id')
                    ->join('business_locations',
                        'business_locations.business_profile_id', '=', 'bp.id')
                    ->where('business_locations.is_active', true)
                    ->addSelect(\DB::raw(self::GEO_SELECT))
                    ->addBinding([$lat, $lng, $lat], 'select')
                    ->having('distance', '<=', $radius)
                    ->orderBy('distance');
            }

            // ------------------------------------------------------------------
            // Sorting
            // ------------------------------------------------------------------
            $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
            $sortBy    = $request->input('sort_by', $hasQuery ? 'relevance' : 'created_at');

            if ($sortBy === 'price') {
                $query->orderBy('products.price', $sortOrder);
            } elseif ($sortBy !== 'relevance') {
                $query->orderBy('products.created_at', $sortOrder);
            }

            return $query->paginate((int) $request->input('per_page', 15));
        });

        return $this->paginatedResponse(ProductResource::collection($results), $results);
    }

    // =========================================================================
    // GET /api/search/businesses
    // =========================================================================

    /**
     * Search for businesses.
     *
     * Query params:
     *   query      - text search (FULLTEXT across business_name, description)
     *   type       - business_type (services | products | both)
     *   verified   - boolean
     *   min_rating - average_rating >=
     *   lat, lng   - geo-filter
     *   radius     - km
     *   sort_by    - created_at | rating | reviews | distance
     *   per_page   - max 20
     */
    public function businesses(Request $request): JsonResponse
    {
        $request->validate([
            'type'       => 'nullable|in:services,products,both',
            'min_rating' => 'nullable|numeric|between:0,5',
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
            'radius'     => 'nullable|numeric|min:1|max:200',
            'per_page'   => 'nullable|integer|min:1|max:20',
            'sort_by'    => 'nullable|in:created_at,rating,reviews,distance',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $params = $request->only([
            'query','type','verified','min_rating',
            'lat','lng','radius',
            'sort_by','sort_order','per_page','page',
        ]);

        $results = CacheService::rememberSearchResults('biz', $params, function () use ($request) {

            $hasGeo   = $request->filled('lat') && $request->filled('lng');
            $hasQuery = $request->filled('query');

            // ------------------------------------------------------------------
            // Select only what BusinessProfileResource actually needs.
            // NOTE: reviews relation removed — we already store average_rating
            //       and total_reviews as denormalised columns. Loading the full
            //       reviews relation was fetching thousands of rows per page.
            // ------------------------------------------------------------------
            $query = BusinessProfile::query()
                ->select([
                    'business_profiles.id',
                    'business_profiles.user_id',
                    'business_profiles.business_name',
                    'business_profiles.business_type',
                    'business_profiles.description',
                    'business_profiles.average_rating',
                    'business_profiles.total_reviews',
                    'business_profiles.is_verified',
                    'business_profiles.instagram',
                    'business_profiles.facebook',
                    'business_profiles.whatsapp',
                    'business_profiles.created_at',
                ])
                ->with([
                    // Only pull columns the Flutter app renders in the list view
                    'locations:id,business_profile_id,city,county,latitude,longitude,is_primary,is_active',
                ])
                ->where('business_profiles.is_verified', '!=', false); // only show active businesses

   
            if ($hasQuery) {
                $term = trim($request->query);
                if (strlen($term) >= 3) {
                    $query->whereRaw(
                        'MATCH(business_profiles.business_name, business_profiles.description) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    );
                } else {
                    $query->where(function ($q) use ($term) {
                        $q->where('business_profiles.business_name', 'like', "{$term}%");
                    });
                }
            }

            // ------------------------------------------------------------------
            // Filters
            // ------------------------------------------------------------------
            if ($request->filled('type')) {
                $query->where('business_profiles.business_type', $request->type);
            }

            if ($request->boolean('verified')) {
                $query->where('business_profiles.is_verified', true);
            }

            if ($request->filled('min_rating')) {
                $query->where('business_profiles.average_rating', '>=', (float) $request->min_rating);
            }

            // ------------------------------------------------------------------
            // Geo filter — business_profiles -> business_locations (primary location)
            //
            // Uses a JOIN (not whereHas) so we can SELECT the distance column
            // and ORDER BY it. whereHas with HAVING is a correlated subquery
            // that can't expose the distance value for sorting.
            // ------------------------------------------------------------------
            if ($hasGeo) {
                $lat    = (float) $request->lat;
                $lng    = (float) $request->lng;
                $radius = (float) $request->input('radius', 10);

                $query
                    ->join('business_locations as bl_geo',
                        'bl_geo.business_profile_id', '=', 'business_profiles.id')
                    ->where('bl_geo.is_active', true)
                    ->where('bl_geo.is_primary', true) // one location per business in results
                    ->addSelect(\DB::raw(
                        '(6371 * acos(
                            cos(radians(?)) * cos(radians(bl_geo.latitude))
                            * cos(radians(bl_geo.longitude) - radians(?))
                            + sin(radians(?)) * sin(radians(bl_geo.latitude))
                        )) AS distance'
                    ))
                    ->addBinding([$lat, $lng, $lat], 'select')
                    ->having('distance', '<=', $radius);
            }

            // ------------------------------------------------------------------
            // Sorting
            // ------------------------------------------------------------------
            $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
            $sortBy    = $request->input('sort_by', 'created_at');

            match ($sortBy) {
                'rating'   => $query->orderBy('business_profiles.average_rating', $sortOrder),
                'reviews'  => $query->orderBy('business_profiles.total_reviews', $sortOrder),
                'distance' => $hasGeo
                                ? $query->orderBy('distance', $sortOrder)
                                : $query->orderBy('business_profiles.created_at', 'desc'),
                default    => $query->orderBy('business_profiles.created_at', $sortOrder),
            };

            return $query->paginate((int) $request->input('per_page', 15));
        });

        return $this->paginatedResponse(BusinessProfileResource::collection($results), $results);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Standard paginated JSON envelope used by all three endpoints.
     */
    private function paginatedResponse(mixed $collection, mixed $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $collection,
            'meta'    => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'total_pages'  => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}