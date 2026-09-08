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

    public function download(\Illuminate\Http\Request $request, Project $project, Asset $asset, \App\Services\ImageFilters $filters, ModuleRegistry $registry)
    {
        $this->authorize('view', $project);
        abort_unless($asset->project_id === $project->id, 404);
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404, 'This image is no longer available.');
        $name = Str::slug(pathinfo($asset->original_name, PATHINFO_FILENAME)) ?: 'image';
        $stack = $project->image_filters ?? [];
        if ($request->filled('module') || $request->filled('slot')) {
            $input = $request->validate(['module' => ['required', 'uuid'], 'slot' => ['required', 'string', 'max:150']]);
            $module = $project->modules()->where('uuid', $input['module'])->firstOrFail();
            $definition = $registry->get($module->module_type);
            $slots = [];
            foreach ($definition['fields'] ?? [] as $field) {
                if ($field['type'] === 'image') $slots[] = $field['key'];
            }
            foreach ($definition['repeaters'] ?? [] as $repeater) {
                foreach ($module->content[$repeater['key']] ?? [] as $index => $row) {
                    foreach ($repeater['fields'] as $field) {
                        if ($field['type'] === 'image') $slots[] = $repeater['key'].'.'.$index.'.'.$field['key'];
                    }
                }
            }
            abort_unless(in_array($input['slot'], $slots, true) && (string) data_get($module->content, $input['slot']) === (string) $asset->id, 404);
            if (data_get($module->content, $input['slot'].'_apply_filters', true) === false) $stack = [];
        }
        if ($stack) {
            $bytes = $disk->get($asset->path);
            $key = 'filtered-images/'.hash('sha256', $bytes.json_encode($filters->operations($stack)).'v2').'.png';
            $cache = Storage::disk('local');
            if (! $cache->exists($key)) $cache->put($key, $filters->render($bytes, $stack));
            $headers = ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-cache'];
            if ($request->boolean('preview')) return response($cache->get($key), 200, $headers);
            return $cache->download($key, $name.'-'.$asset->id.'-filtered.png', $headers);
        }
        if ($request->boolean('preview')) return response($disk->get($asset->path), 200, ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, no-cache']);
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
