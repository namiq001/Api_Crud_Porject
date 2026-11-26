<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Hami mehsulun reyin fore bilir
    public function index($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => 404,
                'message' => 'Product not found',
            ], 404);
        }

        $reviews = $product->reviews()
            ->with('user:id,name')
            ->latest()
            ->get();

        return response()->json([
            'product' => $product->name,
            'average_rating' => round($reviews->avg('rating'), 1),
            'count' => $reviews->count(),
            'reviews' => $reviews,
        ], 200);
    }

    // user rey yazir
    public function store(Request $request, $productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => 404,
                'message' => 'Product not found',
            ], 404);
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Ancaq catdirilmis statusda olan mehsula rey yazmaq olur
        $purchased = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn($q) => $q
                ->where('user_id', $user->id)
                ->where('status', 'delivered'))
            ->exists();

        if (!$purchased) {
            return response()->json([
                'status' => 403,
                'message' => 'Yalniz catdirilmis olan mehsula rey yaza bilersiz',
            ], 403);
        }

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'status' => 422,
                'message' => 'Bu mehsula rey yazmisisz',
            ], 422);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Review created successfully',
            'review' => $review->load('user:id,name'),
        ], 201);
    }

    // User reyi deyisir
    public function update(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'status' => 404,
                'message' => 'Review not found',
            ], 404);
        }

        if ($review->user_id !== $request->user()->id) {
            return response()->json([
                'status' => 403,
                'message' => 'Yalniz oz reyinizi deyise bilersiz',
            ], 403);
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Review updated successfully',
            'review' => $review->load('user:id,name'),
        ], 200);
    }

    // User oz reyini Admin ise istediyi reyi sile bilir
    public function destroy(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'status' => 404,
                'message' => 'Review not found',
            ], 404);
        }

        $user = $request->user();

        if ($review->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'status' => 403,
                'message' => 'Bu reyi silmeye icazeniz yoxdur',
            ], 403);
        }

        $review->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Review deleted successfully',
        ], 200);
    }
}