<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ContentTemplate;
use App\Models\Project;
use App\Models\ProjectModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return view('projects.index', ['projects' => $request->user()->projects()->withCount('modules')->latest('updated_at')->paginate(12)]);
    }

    public function create(Request $request)
    {
        $template = $request->filled('template') ? ContentTemplate::where('slug', $request->template)->where('status', 'published')->first() : null;
        return view('projects.create', compact('template'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'template_id' => ['nullable', 'exists:templates,id'],
            'asin' => ['nullable', 'regex:/^[A-Za-z0-9]{10}$/'], 'marketplace' => ['required', 'string', 'max:30'],
            'author_name' => ['nullable', 'string', 'max:120'], 'genre' => ['nullable', 'string', 'max:100'],
            'audience' => ['nullable', 'string', 'max:200'], 'tone' => ['nullable', 'string', 'max:100'], 'brand_notes' => ['nullable', 'string', 'max:3000'],
            'product_snapshot' => ['nullable', 'json'],
        ]);

        $project = DB::transaction(function () use ($request, $data) {
            $template = ! empty($data['template_id']) ? ContentTemplate::where('status', 'published')->with('modules')->findOrFail($data['template_id']) : null;
            $project = Project::create([
                ...collect($data)->except(['template_id', 'product_snapshot'])->all(),
                'uuid' => (string) Str::uuid(), 'user_id' => $request->user()->id,
                'source_template_id' => $template?->id, 'asin' => isset($data['asin']) ? strtoupper($data['asin']) : null,
                'product_snapshot' => ! empty($data['product_snapshot']) ? json_decode($data['product_snapshot'], true) : null,
            ]);
            $assetMap = [];
            foreach (collect($template?->modules)->take(Project::MAX_MODULES) as $module) {
                ProjectModule::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'module_type' => $module->module_type, 'position' => $module->position, 'content' => $this->cloneTemplateAssets($module->content, $project, $request->user()->id, $assetMap), 'settings' => $module->settings]);
            }
            return $project;
        });

        return redirect()->route('projects.builder', $project)->with('success', 'Your A+ project is ready.');
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'author_name' => ['nullable', 'string', 'max:120'], 'genre' => ['nullable', 'string', 'max:100'], 'audience' => ['nullable', 'string', 'max:200'], 'tone' => ['nullable', 'string', 'max:100'], 'brand_notes' => ['nullable', 'string', 'max:3000']]);
        $project->update($data + ['last_saved_at' => now()]);
        return $request->expectsJson() ? response()->json(['ok' => true, 'data' => $project]) : back()->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        DB::transaction(function () use ($project): void {
            $project->assets()->delete();
            $project->delete();
        });
        Storage::disk('public')->deleteDirectory('projects/'.$project->uuid);

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    private function cloneTemplateAssets(mixed $value, Project $project, int $userId, array &$assetMap): mixed
    {
        if (is_array($value)) {
            $cloned = [];
            foreach ($value as $key => $item) {
                $cloned[$key] = $this->cloneTemplateAssets($item, $project, $userId, $assetMap);
            }

            return $cloned;
        }
        if (! is_string($value) || (! str_starts_with($value, '/images/templates/') && ! str_starts_with($value, '/storage/template-assets/'))) {
            return $value;
        }
        if (isset($assetMap[$value])) {
            return $assetMap[$value];
        }
        $isUploadedTemplateAsset = str_starts_with($value, '/storage/template-assets/');
        $templateRoot = realpath($isUploadedTemplateAsset ? Storage::disk('public')->path('template-assets') : public_path('images/templates'));
        $relativePath = $isUploadedTemplateAsset ? substr($value, strlen('/storage/')) : ltrim($value, '/');
        $realSource = realpath($isUploadedTemplateAsset ? Storage::disk('public')->path($relativePath) : public_path($relativePath));
        abort_unless($templateRoot && $realSource && str_starts_with($realSource, $templateRoot.DIRECTORY_SEPARATOR), 422, 'Template image is unavailable.');
        $dimensions = getimagesize($realSource);
        abort_unless($dimensions !== false, 422, 'Template image is invalid.');
        $extension = strtolower(pathinfo($realSource, PATHINFO_EXTENSION));
        $path = 'projects/'.$project->uuid.'/template-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, file_get_contents($realSource));
        $sourceAsset = $isUploadedTemplateAsset ? Asset::query()->whereNull('project_id')->where('path', $relativePath)->first() : null;
        $asset = Asset::create([
            'user_id' => $userId, 'project_id' => $project->id, 'source' => 'template', 'disk' => 'public', 'path' => $path,
            'original_name' => basename($realSource), 'mime_type' => $dimensions['mime'], 'extension' => $extension,
            'size_bytes' => filesize($realSource), 'width' => $dimensions[0], 'height' => $dimensions[1],
            'alt_text' => $sourceAsset?->alt_text ?: Str::headline(pathinfo($realSource, PATHINFO_FILENAME)).' publishing campaign artwork',
            'checksum' => hash_file('sha256', $realSource), 'metadata' => ['template_path' => $value],
        ]);

        return $assetMap[$value] = $asset->id;
    }
}
