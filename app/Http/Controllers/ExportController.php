<?php

namespace App\Http\Controllers;

use App\Models\ContentExport;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    public function store(Project $project)
    {
        $this->authorize('view', $project);
        $project->load(['modules', 'assets']);
        $manifest = [
            'format' => 'a-plus-content-maker/v1', 'created_at' => now()->toIso8601String(),
            'project' => $project->only(['uuid', 'name', 'asin', 'marketplace', 'author_name', 'genre', 'product_snapshot', 'image_filters']),
            'modules' => $project->modules->map->only(['uuid', 'module_type', 'position', 'content', 'settings']),
            'assets' => $project->assets->map->only(['id', 'original_name', 'path', 'width', 'height', 'alt_text']),
            'transfer_note' => 'Use this package as a copy-and-asset checklist. Content must be entered manually in KDP.',
        ];
        $path = 'exports/'.$project->uuid.'-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $export = ContentExport::create(['user_id' => auth()->id(), 'project_id' => $project->id, 'disk' => 'local', 'path' => $path, 'size_bytes' => Storage::disk('local')->size($path), 'manifest' => ['module_count' => $project->modules->count()], 'expires_at' => now()->addDays(7)]);
        return response()->download(Storage::disk('local')->path($path), Str::slug($project->name).'-kdp-transfer.json');
    }
}
