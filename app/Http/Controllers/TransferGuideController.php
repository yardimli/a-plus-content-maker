<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Services\ModuleRegistry;
use App\Services\RichTextSanitizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransferGuideController extends Controller
{
    public function show(Project $project, ModuleRegistry $registry, RichTextSanitizer $sanitizer)
    {
        $this->authorize('view', $project);
        $project->load(['modules', 'assets']);
        return view('projects.transfer', compact('project', 'sanitizer') + ['registry' => $registry->all()]);
    }

    public function download(Project $project, Asset $asset)
    {
        $this->authorize('view', $project);
        abort_unless($asset->project_id === $project->id, 404);
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404, 'This image is no longer available.');
        $name = Str::slug(pathinfo($asset->original_name, PATHINFO_FILENAME)) ?: 'image';
        if ($asset->mime_type === 'image/webp') {
            $image = imagecreatefromstring($disk->get($asset->path));
            abort_unless($image, 422, 'This image could not be prepared.');
            imagesavealpha($image, true);
            return response()->streamDownload(function () use ($image) {
                imagepng($image);
                imagedestroy($image);
            }, $name.'-'.$asset->id.'.png', ['Content-Type' => 'image/png']);
        }
        return $disk->download($asset->path, $name.'-'.$asset->id.'.'.pathinfo($asset->path, PATHINFO_EXTENSION));
    }
}
