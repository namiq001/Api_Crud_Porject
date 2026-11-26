<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days = (int) $request->input('days', 7);

        // umumi gosterici
        $revenue = Order::where('status', '!=', 'cancelled')->sum('total');

        $ordersByStatus = Order::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // En cox satilmis mehsul
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', '!=', 'cancelled')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // Gunluk sifaris sayi nece gun isteyirik onu gosterir
        $daily = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'total_revenue'   => (float) $revenue,
            'total_orders'    => Order::count(),
            'orders_by_status' => $ordersByStatus,
            'total_users'     => User::count(),
            'total_products'  => Product::count(),
            'low_stock'       => Product::where('stock', '<=', 5)->select('id', 'name', 'stock')->get(),
            'top_products'    => $topProducts,
            'daily'           => $daily,
        ], 200);
    }
}