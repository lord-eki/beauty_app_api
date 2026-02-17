<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get customer's orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::where('customer_id', $request->user()->id)
            ->with(['items.product', 'businessProfile']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
        ], 200);
    }

    /**
     * Get business orders (for providers)
     */
    public function businessOrders(Request $request): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'No business profile found',
            ], 404);
        }

        $query = Order::where('business_profile_id', $businessProfile->id)
            ->with(['items.product', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
        ], 200);
    }

    /**
     * Create new order
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $subtotal = 0;
            $businessProfileId = null;

            // Validate products and calculate subtotal
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                if (!$product->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$product->name} is not available",
                    ], 422);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}",
                    ], 422);
                }

                if (!$businessProfileId) {
                    $businessProfileId = $product->business_profile_id;
                } elseif ($businessProfileId !== $product->business_profile_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'All products must be from the same business',
                    ], 422);
                }

                $price = $product->discounted_price ?? $product->price;
                $subtotal += $price * $item['quantity'];
            }

            // Calculate totals
            $deliveryFee = $request->delivery_type === 'delivery' ? ($request->delivery_fee ?? 0) : 0;
            $taxAmount = $request->tax_amount ?? 0;
            $discountAmount = $request->discount_amount ?? 0;
            $totalAmount = $subtotal - $discountAmount + $taxAmount + $deliveryFee;

            // Create order
            $order = Order::create([
                'customer_id' => $request->user()->id,
                'business_profile_id' => $businessProfileId,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'delivery_type' => $request->delivery_type,
                'delivery_address' => $request->delivery_address,
                'pickup_location_id' => $request->pickup_location_id,
                'notes' => $request->notes,
            ]);

            // Create order items
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $price = $product->discounted_price ?? $product->price;

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $price,
                    'discount_amount' => 0,
                ]);

                // Reduce stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => new OrderResource($order->load(['items.product', 'businessProfile'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order details
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id && 
            $order->businessProfile->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order->load(['items.product', 'businessProfile', 'customer'])),
        ], 200);
    }

    /**
     * Update order status (business only)
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;

        if (!$businessProfile || $order->business_profile_id !== $businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:confirmed,processing,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $request->status]);

        if ($request->status === 'delivered') {
            $order->update(['delivered_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => new OrderResource($order->fresh()),
        ], 200);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$order->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be cancelled',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Restore stock
            foreach ($order->items as $item) {
                $item->product->increment('stock_quantity', $item->quantity);
            }

            $order->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
            ], 500);
        }
    }
}