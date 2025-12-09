<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class ImageController extends Controller
{
    /**
     * Stream image from MinIO
     * Usage: /api/images/menus/filename.jpg
     */
    public function serve($path)
    {
        try {
            // Construct full path
            $fullPath = urldecode($path);

            // Security: Only allow menus directory
            if (!str_starts_with($fullPath, 'menus/')) {
                return response()->json(['error' => 'Unauthorized path'], 403);
            }

            // Check if file exists in MinIO
            if (!Storage::disk('minio')->exists($fullPath)) {
                return response()->json(['error' => 'Image not found'], 404);
            }

            // Get file contents
            $contents = Storage::disk('minio')->get($fullPath);

            // Determine mime type
            $mimeType = Storage::disk('minio')->mimeType($fullPath) ?? 'image/jpeg';

            // Return file with proper headers
            return response($contents, 200)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=31536000')
                ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

        } catch (\Exception $e) {
            \Log::error('Image serve error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to serve image'], 500);
        }
    }
}
