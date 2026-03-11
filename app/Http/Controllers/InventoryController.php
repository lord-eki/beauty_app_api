<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\InventoryMovement;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventoryMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Overview of all inventory across all locations
     * GET /api/business/inventory
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $inventory = ProductInventory::whereHas(
            'product', fn($q) => $q->where('business_profile_id', $profile->id)
        )
        ->with([
            'product:id,name,sku,price,discounted_price,images,is_active',
            'businessLocation:id,name,city',
        ])
        ->when($request->filled('location_id'), fn($q) => $q->where('business_location_id', $request->location_id))
        ->when($request->filled('product_id'),  fn($q) => $q->where('product_id', $request->product_id))
        ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => InventoryResource::collection($inventory),
            'meta'    => [
                'current_page' => $inventory->currentPage(),
                'per_page'     => $inventory->perPage(),
                'total'        => $inventory->total(),
                'last_page'    => $inventory->lastPage(),
            ],
        ]);
    }

    /**
     * Inventory for a specific product across all locations
     * GET /api/business/inventory/{product}
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $inventory = ProductInventory::where('product_id', $product->id)
            ->with('businessLocation:id,name,city,is_primary')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'product' => [
                    'id'    => $product->id,
                    'name'  => $product->name,
                    'sku'   => $product->sku,
                    'price' => $product->price,
                ],
                'locations' => InventoryResource::collection($inventory),
                'totals'    => [
                    'total_available' => $inventory->sum('quantity_available'),
                    'total_reserved'  => $inventory->sum('quantity_reserved'),
                ],
            ],
        ]);
    }

    /**
     * Update stock settings (thresholds, cost price) for a product at a location
     * PUT /api/business/inventory/{product}
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $data = $request->validate([
            'business_location_id' => 'required|integer|exists:business_locations,id',
            'minimum_stock_level'  => 'nullable|integer|min:0',
            'maximum_stock_level'  => 'nullable|integer|min:1',
            'cost_price'           => 'nullable|numeric|min:0',
        ]);

        $inventory = ProductInventory::firstOrCreate(
            [
                'product_id'           => $product->id,
                'business_location_id' => $data['business_location_id'],
            ],
            ['quantity_available' => 0, 'quantity_reserved' => 0]
        );

        $inventory->update([
            'minimum_stock_level' => $data['minimum_stock_level'] ?? $inventory->minimum_stock_level,
            'maximum_stock_level' => $data['maximum_stock_level'] ?? $inventory->maximum_stock_level,
            'cost_price'          => $data['cost_price'] ?? $inventory->cost_price,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory settings updated.',
            'data'    => new InventoryResource($inventory->load('businessLocation:id,name,city')),
        ]);
    }

    /**
     * Products at or below minimum stock level
     * GET /api/business/inventory/low-stock
     */
    public function lowStock(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $lowStock = ProductInventory::whereHas(
            'product', fn($q) => $q->where('business_profile_id', $profile->id)->where('is_active', true)
        )
        ->whereColumn('quantity_available', '<=', 'minimum_stock_level')
        ->with([
            'product:id,name,sku,price,images',
            'businessLocation:id,name,city',
        ])
        ->when($request->filled('location_id'), fn($q) => $q->where('business_location_id', $request->location_id))
        ->orderBy('quantity_available')
        ->get();

        return response()->json([
            'success' => true,
            'data'    => InventoryResource::collection($lowStock),
            'meta'    => ['count' => $lowStock->count()],
        ]);
    }

    /**
     * Full movement history for the business
     * GET /api/business/inventory/movements
     */
    public function movements(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $movements = InventoryMovement::whereHas(
            'productInventory.product', fn($q) => $q->where('business_profile_id', $profile->id)
        )
        ->with([
            'productInventory.product:id,name,sku',
            'productInventory.businessLocation:id,name,city',
            'performedBy:id,first_name,last_name',
        ])
        ->when($request->filled('type'),        fn($q) => $q->where('movement_type', $request->type))
        ->when($request->filled('product_id'),  fn($q) => $q->whereHas('productInventory', fn($q2) => $q2->where('product_id', $request->product_id)))
        ->when($request->filled('location_id'), fn($q) => $q->whereHas('productInventory', fn($q2) => $q2->where('business_location_id', $request->location_id)))
        ->when($request->filled('from'),        fn($q) => $q->whereDate('created_at', '>=', $request->from))
        ->when($request->filled('to'),          fn($q) => $q->whereDate('created_at', '<=', $request->to))
        ->latest()
        ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => InventoryMovementResource::collection($movements),
            'meta'    => [
                'current_page' => $movements->currentPage(),
                'per_page'     => $movements->perPage(),
                'total'        => $movements->total(),
                'last_page'    => $movements->lastPage(),
            ],
        ]);
    }

    /**
     * Record a manual stock movement
     * POST /api/business/inventory/movement
     */
    public function recordMovement(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $data = $request->validate([
            'product_id'           => 'required|integer|exists:products,id',
            'business_location_id' => 'required|integer|exists:business_locations,id',
            'movement_type'        => 'required|in:stock_in,stock_out,adjustment,damaged,expired,returned',
            'quantity'             => 'required|integer|min:1',
            'reference_type'       => 'nullable|in:purchase,sale,adjustment,return',
            'reference_id'         => 'nullable|integer',
            'notes'                => 'nullable|string|max:500',
        ]);

        // Ensure the product belongs to this business
        $product = Product::where('id', $data['product_id'])
            ->where('business_profile_id', $profile->id)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        return DB::transaction(function () use ($data, $product, $request): JsonResponse {

            $inventory = ProductInventory::lockForUpdate()->firstOrCreate(
                [
                    'product_id'           => $product->id,
                    'business_location_id' => $data['business_location_id'],
                ],
                ['quantity_available' => 0, 'quantity_reserved' => 0]
            );

            $outTypes = ['stock_out', 'damaged', 'expired'];
            $signed   = in_array($data['movement_type'], $outTypes)
                ? -abs($data['quantity'])
                :  abs($data['quantity']);

            $newQty = $inventory->quantity_available + $signed;

            if ($newQty < 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock. Available: {$inventory->quantity_available}.",
                ], 422);
            }

            $inventory->update(['quantity_available' => $newQty]);

            $movement = InventoryMovement::create([
                'product_inventory_id' => $inventory->id,
                'movement_type'        => $data['movement_type'],
                'quantity'             => $signed,
                'reference_type'       => $data['reference_type'] ?? null,
                'reference_id'         => $data['reference_id'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'performed_by'         => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Movement recorded successfully.',
                'data'    => [
                    'movement'           => new InventoryMovementResource($movement),
                    'quantity_available' => $inventory->quantity_available,
                ],
            ], 201);
        });
    }

    /**
     * Bulk restock: add stock to multiple products at a location
     * POST /api/business/inventory/restock
     */
    public function restock(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $data = $request->validate([
            'business_location_id'   => 'required|integer|exists:business_locations,id',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|integer|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.cost_price'     => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string|max:500',
        ]);

        $userId     = $request->user()->id;
        $locationId = $data['business_location_id'];
        $results    = [];

        DB::transaction(function () use ($profile, $data, $locationId, $userId, &$results) {
            foreach ($data['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('business_profile_id', $profile->id)
                    ->first();

                if (!$product) {
                    continue; // skip products not owned by this business
                }

                $inventory = ProductInventory::lockForUpdate()->firstOrCreate(
                    ['product_id' => $product->id, 'business_location_id' => $locationId],
                    ['quantity_available' => 0, 'quantity_reserved' => 0]
                );

                $inventory->update([
                    'quantity_available' => $inventory->quantity_available + $item['quantity'],
                    'last_restocked_at'  => now(),
                    'cost_price'         => $item['cost_price'] ?? $inventory->cost_price,
                ]);

                InventoryMovement::create([
                    'product_inventory_id' => $inventory->id,
                    'movement_type'        => 'stock_in',
                    'quantity'             => $item['quantity'],
                    'reference_type'       => 'purchase',
                    'notes'                => $data['notes'] ?? null,
                    'performed_by'         => $userId,
                ]);

                $results[] = [
                    'product_id'         => $product->id,
                    'product_name'       => $product->name,
                    'quantity_added'     => $item['quantity'],
                    'quantity_available' => $inventory->quantity_available,
                ];
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Restock completed.',
            'data'    => $results,
        ], 201);
    }

    // -------------------------------------------------------------------------

    private function authorizeProduct(Request $request, Product $product): void
    {
        $profile = $request->user()->businessProfile;
        abort_unless(
            $profile && $product->business_profile_id === $profile->id,
            403,
            'Unauthorized.'
        );
    }
}