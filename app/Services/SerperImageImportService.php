<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SerperImageImportService
{
    public function import(array $result, User $user, ?Project $project = null): Asset
    {
        $storedPaths = [];

        try {
            $image = $this->downloadImage($result['image_url']);
            $thumbnail = $this->downloadImage($result['thumbnail_url']);
            $baseName = Str::slug(pathinfo((string) ($result['title'] ?? ''), PATHINFO_FILENAME)) ?: 'stock-image';
            $folder = $project ? 'projects/'.$project->uuid.'/serper' : 'template-assets/'.$user->id.'/serper';
            $imagePath = $folder.'/'.Str::uuid().'-'.$baseName.'.'.$image['extension'];
            $thumbnailPath = $folder.'/thumbnails/'.Str::uuid().'-'.$baseName.'.'.$thumbnail['extension'];

            Storage::disk('public')->put($imagePath, $image['body']);
            $storedPaths[] = $imagePath;
            Storage::disk('public')->put($thumbnailPath, $thumbnail['body']);
            $storedPaths[] = $thumbnailPath;

            return DB::transaction(fn () => Asset::create([
                'user_id' => $user->id,
                'project_id' => $project?->id,
                'source' => $project ? 'serper' : 'template',
                'disk' => 'public',
                'path' => $imagePath,
                'original_name' => $baseName.'.'.$image['extension'],
                'mime_type' => $image['mime_type'],
                'extension' => $image['extension'],
                'size_bytes' => strlen($image['body']),
                'width' => $image['width'],
                'height' => $image['height'],
                'alt_text' => Str::limit(trim((string) ($result['title'] ?? 'Stock image')), 250, ''),
                'checksum' => hash('sha256', $image['body']),
                'metadata' => [
                    'provider' => 'serper',
                    'source' => $result['source'] ?? null,
                    'source_page' => $result['link'] ?? null,
                    'remote_image_url' => $result['image_url'],
                    'remote_thumbnail_url' => $result['thumbnail_url'],
                    'thumbnail_path' => $thumbnailPath,
                ],
            ]));
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }
    }

    private function downloadImage(string $url): array
    {
        $response = Http::accept('image/jpeg, image/png, image/webp')->timeout(30)->retry(2, 300)->get($url)->throw();
        $body = $response->body();
        if ($body === '' || strlen($body) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['token' => 'The selected image is empty or larger than 20 MB.']);
        }

        $dimensions = @getimagesizefromstring($body);
        if ($dimensions === false) {
            throw ValidationException::withMessages(['token' => 'The selected URL did not return a valid image.']);
        }

        $mimeType = $dimensions['mime'] ?? $response->header('Content-Type');
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['token' => 'Only JPG, PNG, and WebP search images can be imported.']),
        };

        return ['body' => $body, 'mime_type' => $mimeType, 'extension' => $extension, 'width' => $dimensions[0], 'height' => $dimensions[1]];
    }
}
