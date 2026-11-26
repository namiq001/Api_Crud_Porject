<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // Userin oz wishlis-i
    public function index(Request $request)
    {
        $products = $request->user()
            ->favorites()
            ->with('category', 'tags')
            ->latest('favorites.created_at')
            ->get();

        return response()->json([
            'count' => $products->count(),
            'favorites' => $products,
        ], 200);
    }

    // Wishlist-e elave et 
    public function add(Request $request, $productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['status' => 404, 'message' => 'Product not found'], 404);
        }

        $request->user()->favorites()->syncWithoutDetaching([$product->id]);

        return response()->json(['status' => 'success', 'message' => 'Added to favorites', 'is_favorite' => true], 200);
    }
    
    // Wishlist sil
    public function remove(Request $request, $productId)
    {
        $request->user()->favorites()->detach($productId);

        return response()->json(['status' => 'success', 'message' => 'Removed from favorites', 'is_favorite' => false], 200);
    }
}