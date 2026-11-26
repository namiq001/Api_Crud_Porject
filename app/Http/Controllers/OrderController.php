<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // User
    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product.category', 'items.product.tags'])
            ->latest()
            ->get();

        return response()->json(['orders' => $orders], 200);
    }

    public function show(Request $request, $id)
    {
        $order = $request->user()
            ->orders()
            ->with(['items.product.category', 'items.product.tags'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'status' => 404,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json(['order' => $order], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = collect($data['items'])
            ->groupBy('product_id')
            ->map(fn($rows) => $rows->sum('quantity'));

        $order = DB::transaction(function () use ($request, $items) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total' => 0,
                'status' => 'pending',
            ]);

            $total = 0;

            foreach ($items as $productId => $quantity) {
                $product = Product::lockForUpdate()->find($productId);

                if (!$product->status) {
                    throw ValidationException::withMessages([
                        'items' => ["{$product->name} stok da yoxdur"],
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["{$product->name} istediyin qeder stok yoxdur (qalib: {$product->stock})"],
                    ]);
                }

                $product->decrement('stock', $quantity);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $product->price,
                ]);

                $total += $product->price * $quantity;
            }

            $order->update(['total' => $total]);

            return $order;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Order created successfully',
            'order' => $order->load(['items.product.category', 'items.product.tags']),
        ], 201);
    }

    public function cancel(Request $request, $id)
    {
        $order = DB::transaction(function () use ($request, $id) {
            $order = $request->user()
                ->orders()
                ->with('items')
                ->lockForUpdate()
                ->find($id);

            if (!$order) {
                return null;
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ["Yalniz 'pending' sifarisi legv etmek olar (hazirki status: {$order->status})"],
                ]);
            }

            // Stoku geri qaytar
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);

            return $order;
        });

        if (!$order) {
            return response()->json([
                'status' => 404,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Order cancelled successfully',
            'order' => $order->load('items.product'),
        ], 200);
    }

    // Admin 
    public function adminIndex(Request $request)
    {
        $orders = Order::with(['user:id,name,email', 'items.product.category', 'items.product.tags'])
            ->when($request->user_id, fn($q, $id) => $q->where('user_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->get();

        return response()->json([
            'count' => $orders->count(),
            'orders' => $orders,
        ], 200);
    }

    public function adminShow($id)
    {
        $order = Order::with(['user:id,name,email', 'items.product.category', 'items.product.tags'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'status' => 404,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json(['order' => $order], 200);
    }

    // sifarisin statusunu deyismek 
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,shipped,delivered,cancelled',
        ]);

        $allowed = [
            'pending' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];

        $order = DB::transaction(function () use ($id, $data, $allowed) {
            $order = Order::with('items')->lockForUpdate()->find($id);

            if (!$order) {
                return null;
            }

            if (!in_array($data['status'], $allowed[$order->status] ?? [])) {
                throw ValidationException::withMessages([
                    'status' => ["'{$order->status}' statusundan '{$data['status']}' statusuna kecid mumkun deyil"],
                ]);
            }

            // Legv olunanda stoku geri qaytar
            if ($data['status'] === 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)
                            ->increment('stock', $item->quantity);
                    }
                }
            }

            $order->update(['status' => $data['status']]);

            return $order;
        });

        if (!$order) {
            return response()->json([
                'status' => 404,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Order status updated successfully',
            'order' => $order->load(['user:id,name,email', 'items.product']),
        ], 200);
    }
}