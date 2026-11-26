<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        return response()->json([
            'tags' => Tag::withCount('products')->latest()->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name',
        ]);

        $tag = Tag::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Tag created successfully',
            'tag' => $tag,
        ], 201);
    }

    public function show($id)
    {
        $tag = Tag::withCount('products')->find($id);

        if (!$tag) {
            return response()->json([
                'status' => 404,
                'message' => 'Tag not found',
            ], 404);
        }

        return response()->json([
            'tag' => $tag
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json([
                'status' => 404,
                'message' => 'Tag not found',
            ], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name,' . $tag->id,
        ]);

        $tag->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Tag updated successfully',
            'tag' => $tag,
        ], 200);
    }

    public function destroy($id)
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json([
                'status' => 404,
                'message' => 'Tag not found',
            ], 404);
        }

        $tag->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tag deleted successfully',
        ], 200);
    }
}