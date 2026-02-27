<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryMovementRequest;
use App\Http\Requests\UpdateInventoryMovementRequest;
use App\Models\BusinessLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
   /*
    |--------------------------------------------------------------------------
    | GET /api/business/inventory
    |--------------------------------------------------------------------------
    | Overview of all inventory across all locations for the business.
    | Supports ?location_id= and ?low_stock=1 filters.
    */
    public function index(Request $request): JsonResponse
    {
        $business = $request->user()->businessProfile;

        // Get all location IDs owned by this business
        $locationIds = BusinessLocation::where('business_profile_id', $business->id)
            ->pluck('id');

        $query = ProductInventory::with([
            'product:id,name,sku,price,images',
            'businessLocation:id,name,city,address',
        ])->whereIn('business_location_id', $locationIds);

        // Filter by specific location
        if ($request->filled('location_id')) {
            $request->validate(['location_id' => 'integer|exists:business_locations,id']);
            $query->where('business_location_id', $request->location_id);
        }

        // Filter to only low-stock items
        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity_available', '<=', 'minimum_stock_level');
        }

        // Filter to only out-of-stock items
        if ($request->boolean('out_of_stock')) {
            $query->where('quantity_available', '<=', 0);
        }

        $inventory = $query->orderBy('quantity_available')->paginate(20);

        return $this->success($inventory->through(fn($i) => $this->formatInventory($i)));
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/business/inventory/{product}
    |--------------------------------------------------------------------------
    | Get inventory for a specific product across all locations.
    */
    public function show(Request $request, Product $product): JsonResponse
    {
        $business = $request->user()->businessProfile;

        // Ensure product belongs to this business
        if ($product->business_profile_id !== $business->id) {
            return $this->error('Product not found.', 404);
        }

        $inventory = ProductInventory::with('businessLocation:id,name,city,address')
            ->where('product_id', $product->id)
            ->get();

        return $this->success([
            'product'   => [
                'id'     => $product->id,
                'name'   => $product->name,
                'sku'    => $product->sku,
                'price'  => $product->price,
                'images' => $product->images,
            ],
            'locations' => $inventory->map(fn($i) => $this->formatInventory($i)),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PUT /api/business/inventory/{product}
    |--------------------------------------------------------------------------
    | Update stock levels / thresholds for a product at a specific location.
    | Body: { location_id, quantity_available?, minimum_stock_level?, maximum_stock_level?, cost_price? }
    */
    public function update(Request $request, Product $product): JsonResponse
    {
        $business = $request->user()->businessProfile;

        if ($product->business_profile_id !== $business->id) {
            return $this->error('Product not found.', 404);
        }

        $data = $request->validate([
            'location_id'          => ['required', 'integer', 'exists:business_locations,id'],
            'quantity_available'   => ['sometimes', 'integer', 'min:0'],
            'minimum_stock_level'  => ['sometimes', 'integer', 'min:0'],
            'maximum_stock_level'  => ['sometimes', 'integer', 'min:0'],
            'cost_price'           => ['sometimes', 'numeric', 'min:0'],
        ]);

        // Ensure location belongs to this business
        $location = BusinessLocation::where('id', $data['location_id'])
            ->where('business_profile_id', $business->id)
            ->firstOrFail();

        $inventory = ProductInventory::firstOrCreate(
            ['product_id' => $product->id, 'business_location_id' => $location->id],
            ['quantity_available' => 0, 'quantity_reserved' => 0]
        );

        $oldQty = $inventory->quantity_available;

        $inventory->update(array_filter([
            'quantity_available'  => $data['quantity_available'] ?? null,
            'minimum_stock_level' => $data['minimum_stock_level'] ?? null,
            'maximum_stock_level' => $data['maximum_stock_level'] ?? null,
            'cost_price'          => $data['cost_price'] ?? null,
        ], fn($v) => $v !== null));

        // Auto-record an adjustment movement if qty changed
        if (isset($data['quantity_available']) && $data['quantity_available'] !== $oldQty) {
            $diff = $data['quantity_available'] - $oldQty;
            InventoryMovement::create([
                'product_inventory_id' => $inventory->id,
                'movement_type'        => 'adjustment',
                'quantity'             => $diff,
                'reference_type'       => 'adjustment',
                'notes'                => 'Manual stock adjustment',
                'performed_by'         => $request->user()->id,
            ]);

            // Update last restocked timestamp if stock increased
            if ($diff > 0) {
                $inventory->update(['last_restocked_at' => now()]);
            }
        }

        return $this->success($this->formatInventory($inventory->fresh(['product', 'businessLocation'])));
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/business/inventory/movement
    |--------------------------------------------------------------------------
    | Record a stock movement (stock_in, damaged, expired, returned, etc).
    | Body: { product_id, location_id, movement_type, quantity, notes? }
    */
    public function recordMovement(Request $request): JsonResponse
    {
        $business = $request->user()->businessProfile;

        $data = $request->validate([
            'product_id'    => ['required', 'integer', 'exists:products,id'],
            'location_id'   => ['required', 'integer', 'exists:business_locations,id'],
            'movement_type' => ['required', 'in:stock_in,stock_out,adjustment,damaged,expired,returned'],
            'quantity'      => ['required', 'integer', 'min:1'],
            'notes'         => ['nullable', 'string', 'max:500'],
            'reference_type'=> ['nullable', 'in:purchase,sale,adjustment,return'],
            'reference_id'  => ['nullable', 'integer'],
        ]);

        // Verify ownership
        $product  = Product::where('id', $data['product_id'])
            ->where('business_profile_id', $business->id)
            ->firstOrFail();
        $location = BusinessLocation::where('id', $data['location_id'])
            ->where('business_profile_id', $business->id)
            ->firstOrFail();

        $inventory = ProductInventory::firstOrCreate(
            ['product_id' => $product->id, 'business_location_id' => $location->id],
            ['quantity_available' => 0, 'quantity_reserved' => 0]
        );

        // Determine signed quantity
        $outTypes  = ['stock_out', 'damaged', 'expired'];
        $signedQty = in_array($data['movement_type'], $outTypes) ? -$data['quantity'] : $data['quantity'];

        // Prevent stock going negative
        if ($inventory->quantity_available + $signedQty < 0) {
            return $this->error(
                "Insufficient stock. Available: {$inventory->quantity_available}, requested: {$data['quantity']}.",
                422
            );
        }

        DB::transaction(function () use ($inventory, $data, $signedQty, $request) {
            InventoryMovement::create([
                'product_inventory_id' => $inventory->id,
                'movement_type'        => $data['movement_type'],
                'quantity'             => $signedQty,
                'reference_type'       => $data['reference_type'] ?? null,
                'reference_id'         => $data['reference_id'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'performed_by'         => $request->user()->id,
            ]);

            $inventory->increment('quantity_available', $signedQty);

            if ($data['movement_type'] === 'stock_in') {
                $inventory->update(['last_restocked_at' => now()]);
            }
        });

        return $this->success(
            $this->formatInventory($inventory->fresh(['product', 'businessLocation'])),
            'Stock movement recorded.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/business/inventory/movements
    |--------------------------------------------------------------------------
    | Get movement history. Supports ?product_id= and ?location_id= filters.
    */
    public function movements(Request $request): JsonResponse
    {
        $business = $request->user()->businessProfile;

        $locationIds = BusinessLocation::where('business_profile_id', $business->id)->pluck('id');

        $inventoryIds = ProductInventory::whereIn('business_location_id', $locationIds)->pluck('id');

        $query = InventoryMovement::with([
            'productInventory.product:id,name,sku',
            'productInventory.businessLocation:id,name,city',
            'performedBy:id,first_name,last_name',
        ])->whereIn('product_inventory_id', $inventoryIds);

        if ($request->filled('product_id')) {
            $productInventoryIds = ProductInventory::where('product_id', $request->product_id)
                ->whereIn('business_location_id', $locationIds)
                ->pluck('id');
            $query->whereIn('product_inventory_id', $productInventoryIds);
        }

        $movements = $query->orderByDesc('created_at')->paginate(30);

        return $this->success($movements->through(fn($m) => $this->formatMovement($m)));
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/business/inventory/low-stock
    |--------------------------------------------------------------------------
    | Returns items at or below minimum_stock_level across all locations.
    */
    public function lowStock(Request $request): JsonResponse
    {
        $business    = $request->user()->businessProfile;
        $locationIds = BusinessLocation::where('business_profile_id', $business->id)->pluck('id');

        $items = ProductInventory::with([
            'product:id,name,sku,price',
            'businessLocation:id,name,city,address',
        ])
            ->whereIn('business_location_id', $locationIds)
            ->whereColumn('quantity_available', '<=', 'minimum_stock_level')
            ->orderBy('quantity_available')
            ->get();

        return $this->success([
            'count' => $items->count(),
            'items' => $items->map(fn($i) => array_merge($this->formatInventory($i), [
                'shortage' => max(0, $i->minimum_stock_level - $i->quantity_available),
            ])),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/business/inventory/restock
    |--------------------------------------------------------------------------
    | Bulk restock multiple products at a location.
    | Body: { location_id, items: [{ product_id, quantity, cost_price?, notes? }] }
    */
    public function restock(Request $request): JsonResponse
    {
        $business = $request->user()->businessProfile;

        $data = $request->validate([
            'location_id'          => ['required', 'integer', 'exists:business_locations,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.cost_price'   => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $location = BusinessLocation::where('id', $data['location_id'])
            ->where('business_profile_id', $business->id)
            ->firstOrFail();

        $results = [];

        DB::transaction(function () use ($data, $location, $request, &$results) {
            foreach ($data['items'] as $item) {
                // Verify product belongs to this business
                $product = Product::where('id', $item['product_id'])
                    ->where('business_profile_id', $request->user()->businessProfile->id)
                    ->first();

                if (! $product) continue;

                $inventory = ProductInventory::firstOrCreate(
                    ['product_id' => $product->id, 'business_location_id' => $location->id],
                    ['quantity_available' => 0, 'quantity_reserved' => 0]
                );

                InventoryMovement::create([
                    'product_inventory_id' => $inventory->id,
                    'movement_type'        => 'stock_in',
                    'quantity'             => $item['quantity'],
                    'reference_type'       => 'purchase',
                    'notes'                => $item['notes'] ?? 'Bulk restock',
                    'performed_by'         => $request->user()->id,
                ]);

                $updates = ['last_restocked_at' => now()];
                if (isset($item['cost_price'])) {
                    $updates['cost_price'] = $item['cost_price'];
                }

                $inventory->increment('quantity_available', $item['quantity']);
                $inventory->update($updates);

                $results[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'qty_added'    => $item['quantity'],
                    'qty_total'    => $inventory->fresh()->quantity_available,
                ];
            }
        });

        return $this->success([
            'location'  => ['id' => $location->id, 'name' => $location->name ?? $location->city],
            'restocked' => $results,
        ], count($results) . ' product(s) restocked successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function formatInventory(ProductInventory $i): array
    {
        return [
            'id'                  => $i->id,
            'product'             => [
                'id'     => $i->product?->id,
                'name'   => $i->product?->name,
                'sku'    => $i->product?->sku,
                'price'  => $i->product?->price,
                'image'  => is_array($i->product?->images) ? ($i->product->images[0] ?? null) : null,
            ],
            'location'            => [
                'id'      => $i->businessLocation?->id,
                'name'    => $i->businessLocation?->name ?? $i->businessLocation?->city,
                'address' => $i->businessLocation?->address,
                'city'    => $i->businessLocation?->city,
            ],
            'quantity_available'  => $i->quantity_available,
            'quantity_reserved'   => $i->quantity_reserved,
            'quantity_net'        => $i->quantity_available - $i->quantity_reserved,
            'minimum_stock_level' => $i->minimum_stock_level,
            'maximum_stock_level' => $i->maximum_stock_level,
            'cost_price'          => $i->cost_price,
            'is_low_stock'        => $i->isLowStock(),
            'is_out_of_stock'     => $i->isOutOfStock(),
            'last_restocked_at'   => $i->last_restocked_at?->toIso8601String(),
        ];
    }

    private function formatMovement(InventoryMovement $m): array
    {
        return [
            'id'            => $m->id,
            'product'       => $m->productInventory?->product?->only(['id', 'name', 'sku']),
            'location'      => $m->productInventory?->businessLocation?->only(['id', 'name', 'city']),
            'movement_type' => $m->movement_type,
            'quantity'      => $m->quantity,
            'reference_type'=> $m->reference_type,
            'reference_id'  => $m->reference_id,
            'notes'         => $m->notes,
            'performed_by'  => $m->performedBy
                ? trim($m->performedBy->first_name . ' ' . $m->performedBy->last_name)
                : null,
            'created_at'    => $m->created_at?->toIso8601String(),
        ];
    }

    private function success(mixed $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
