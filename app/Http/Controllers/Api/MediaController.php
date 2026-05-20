<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class MediaController extends Controller
{
    /**
     * Serve public storage files through Laravel so CORS headers are applied.
     */
    public function show(Request $request, string $path)
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $storageRoot = storage_path('app/public');
        $fullPath = realpath($storageRoot . DIRECTORY_SEPARATOR . $path);

        if (!$fullPath || !str_starts_with($fullPath, realpath($storageRoot)) || !File::exists($fullPath)) {
            abort(404);
        }

        $requestOrigin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedOriginPatterns = config('cors.allowed_origins_patterns', []);
        $origin = '*';

        if ($requestOrigin && in_array('*', $allowedOrigins, true)) {
            $origin = $requestOrigin;
        } elseif ($requestOrigin && in_array($requestOrigin, $allowedOrigins, true)) {
            $origin = $requestOrigin;
        } elseif ($requestOrigin) {
            foreach ($allowedOriginPatterns as $pattern) {
                if (@preg_match($pattern, $requestOrigin)) {
                    $origin = $requestOrigin;
                    break;
                }
            }
        }

        return response()->file($fullPath, [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization',
            'Vary' => 'Origin',
        ]);
    }
}
