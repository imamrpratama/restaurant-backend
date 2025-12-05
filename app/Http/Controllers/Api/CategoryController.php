<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Cache::remember('categories', 3600, function () {
            return Category::all();
        });

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->all());

        Cache::forget('categories');

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($request->all());

        Cache::forget('categories');

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        // ADDED: Check if category has menus
        $menuCount = $category->menus()->count();

        if ($menuCount > 0) {
            return response()->json([
                'message' => 'Cannot delete category',
                'error' => "This category has {$menuCount} menu item(s).  Please delete or reassign the menu items first."
            ], 422);
        }

        // ADDED: Check if category has orders through menus
        $hasOrders = $category->menus()
            ->whereHas('orderItems')
            ->exists();

        if ($hasOrders) {
            return response()->json([
                'message' => 'Cannot delete category',
                'error' => 'This category has associated orders. It cannot be deleted for record keeping purposes.'
            ], 422);
        }

        $category->delete();

        Cache::forget('categories');

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
