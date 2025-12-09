<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'menus_' . ($request->category_id ??  'all');

        $menus = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Menu::with('category');

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            return $query->get();
        });

        // Convert to arrays to include appended attributes
        return response()->json($menus->map(fn($menu) => $menu->toArray())->all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_available' => 'nullable|in:0,1,true,false', // Accept string or boolean
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $request->only(['category_id', 'name', 'description', 'price']);

        // Convert is_available to boolean
        $data['is_available'] = in_array($request->input('is_available'), ['1', 1, true, 'true'], true);

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('menus', 'minio');
            $data['image_path'] = $imagePath;
        }

        $menu = Menu::create($data);

        Log::info('Menu created', ['menu_id' => $menu->id, 'data' => $data]);

        // Clear cache
        $this->clearMenuCache();

        // Load relations and return with appended attributes
        $menu->load('category');
        return response()->json($menu->toArray(), 201);
    }

    public function show(Menu $menu)
    {
        $menu->load('category');
        return response()->json($menu->toArray());
    }

    public function update(Request $request, Menu $menu)
    {
        \Log::info('Menu update request', [
            'menu_id' => $menu->id,
            'all_data' => $request->all(),
            'has_file' => $request->hasFile('image')
        ]);

        $request->validate([
            'category_id' => 'exists:categories,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'is_available' => 'nullable|string|in:0,1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = [];

        if ($request->has('category_id')) {
            $data['category_id'] = $request->input('category_id');
        }

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }

        if ($request->has('description')) {
            $data['description'] = $request->input('description');
        }

        if ($request->has('price')) {
            $data['price'] = $request->input('price');
        }

        if ($request->has('is_available')) {
            $data['is_available'] = $request->input('is_available', '1') === '1';
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            try {
                // Delete old image
                if ($menu->image_path) {
                    Storage::disk('minio')->delete($menu->image_path);
                    \Log::info('Old image deleted', ['path' => $menu->image_path]);
                }

                $image = $request->file('image');
                $imagePath = $image->store('menus', 'minio');
                $data['image_path'] = $imagePath;

                \Log::info('New image uploaded', ['path' => $imagePath]);
            } catch (\Exception $e) {
                \Log::error('Image upload failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Image upload failed',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        $menu->update($data);

        \Log::info('Menu updated successfully', ['menu_id' => $menu->id]);

        // Clear cache
        $this->clearMenuCache();

        // Load relations and return with appended attributes
        $menu->load('category');
        return response()->json($menu->toArray());
    }

    public function destroy(Menu $menu)
    {
        if ($menu->image_path) {
            Storage::disk('minio')->delete($menu->image_path);
        }

        $menu->delete();
        $this->clearMenuCache();

        return response()->json(['message' => 'Menu deleted successfully']);
    }

    private function clearMenuCache()
    {
        Cache::flush();
    }
}
