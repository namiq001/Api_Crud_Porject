<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            "categories" => Category::withCount('products')->latest()->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $category = Category::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created succesfully',
            'category' => $category,
        ], 201);
    }

    public function show($id)
    {
        $category = Category::withCount('products')->find($id);
        if (!$category) {
            return response()->json([
                'status' => 404,
                'message' => 'Category not found',
            ], 404);
        }
        return response()->json([
            'category' => $category,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);
        $category = Category::findOrFail($id);

        if (!$category) {
            return response()->json([
                'status' => 404,
                'message' => 'Category not found',
            ], 404);
        }
        $category->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Category update successfully',
            'category' => $category,
        ], 200);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        if (!$category) {
            return response()->json([
                'status' => 404,
                'message' => 'Category not found',
            ], 404);
        }
        $category->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Category Delete successfully',
        ], 200);
    }


    public function products($id)
    {
        $category = Category::with('products')->find($id);

        if (!$category) {
            return response()->json([
                'status' => 404,
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'category' => $category->name,
            'products' => $category->products,
        ], 200);
    }
}