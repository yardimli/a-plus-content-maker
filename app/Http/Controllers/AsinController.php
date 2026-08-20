<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Services\AmazonProductDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AsinController extends Controller
{
    public function lookup(Request $request, AmazonProductDataService $service)
    {
        $data = $request->validate(['asin' => ['required', 'string', 'size:10']]);
        return response()->json(['ok' => true, 'data' => $service->lookup($data['asin'], $request->user())]);
    }

    public function import(Request $request, Project $project, AmazonProductDataService $service)
    {
        $this->authorize('update', $project);
        $data = $request->validate(['asin' => ['required', 'string', 'size:10']]);
        $snapshot = $service->lookup($data['asin'], $request->user());
        $url = (string) ($snapshot['image_url'] ?? '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = $host === 'm.media-amazon.com' || $host === 'images-na.ssl-images-amazon.com' || str_ends_with($host, '.media-amazon.com');
        if (! $allowed || ! str_starts_with($url, 'https://')) {
            throw ValidationException::withMessages(['asin' => 'The book cover URL returned by the provider could not be imported safely.']);
        }

        $response = Http::timeout(15)->connectTimeout(5)->retry(2, 200)->get($url);
        if (! $response->successful() || strlen($response->body()) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['asin' => 'The book cover could not be downloaded.']);
        }
        $dimensions = @getimagesizefromstring($response->body());
        if ($dimensions === false || ! in_array($dimensions['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['asin' => 'The downloaded cover was not a supported image.']);
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$dimensions['mime']];
        $path = 'projects/'.$project->uuid.'/asin-'.strtoupper($snapshot['asin']).'-'.Str::lower(Str::random(8)).'.'.$extension;
        Storage::disk('public')->put($path, $response->body());
        $asset = Asset::create([
            'user_id' => $request->user()->id, 'project_id' => $project->id, 'source' => 'asin', 'disk' => 'public', 'path' => $path,
            'original_name' => strtoupper($snapshot['asin']).'-cover.'.$extension, 'mime_type' => $dimensions['mime'], 'extension' => $extension,
            'size_bytes' => strlen($response->body()), 'width' => $dimensions[0], 'height' => $dimensions[1],
            'alt_text' => ($snapshot['title'] ?? 'Book').' cover', 'checksum' => hash('sha256', $response->body()),
            'metadata' => ['asin' => $snapshot['asin'], 'product_url' => $snapshot['product_url'] ?? null],
        ]);

        return response()->json(['ok' => true, 'data' => ['product' => $snapshot, 'asset' => $asset]], 201);
    }
}
