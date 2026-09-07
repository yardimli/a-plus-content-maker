<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentTemplate;
use App\Models\Asset;
use App\Models\TemplateModule;
use App\Services\ModuleRegistry;
use App\Services\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index()
    {
        return view('admin.templates.index', ['templates' => ContentTemplate::withCount('modules')->latest()->paginate(20)]);
    }

    public function create(ModuleRegistry $registry)
    {
        return view('builder.show', [
            'template' => new ContentTemplate(['status' => 'draft']),
            'registry' => $registry->all(),
            'assets' => $this->templateAssets(),
            'editorMode' => 'template-create',
        ]);
    }

    public function store(Request $request, ModuleRegistry $registry, RichTextSanitizer $sanitizer)
    {
        $data = $this->validated($request);
        $template = DB::transaction(function () use ($request, $registry, $sanitizer, $data) {
            $template = ContentTemplate::create([...$data, 'created_by' => $request->user()->id, 'slug' => $this->uniqueSlug($data['name']), 'published_at' => $data['status'] === 'published' ? now() : null]);
            $this->syncModules($request, $template, $registry, $sanitizer);
            return $template;
        });
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'data' => $template, 'redirect' => route('admin.templates.edit', $template)], 201);
        }
        return redirect()->route('admin.templates.edit', $template)->with('success', 'Template created.');
    }

    public function edit(ContentTemplate $template, ModuleRegistry $registry)
    {
        $template->load('modules');
        return view('builder.show', [
            'template' => $template,
            'registry' => $registry->all(),
            'assets' => $this->templateAssets($template),
            'editorMode' => 'template',
        ]);
    }

    public function update(Request $request, ContentTemplate $template, ModuleRegistry $registry, RichTextSanitizer $sanitizer)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $template, $registry, $sanitizer, $data) {
            $template->update([...$data, 'published_at' => $data['status'] === 'published' ? ($template->published_at ?: now()) : null]);
            if ($request->has('modules')) {
                $this->syncModules($request, $template, $registry, $sanitizer);
            }
        });
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'data' => $template->fresh()]);
        }
        return back()->with('success', 'Template updated.');
    }

    public function destroy(ContentTemplate $template)
    {
        $template->update(['status' => 'archived']);
        return back()->with('success', 'Template archived.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'summary' => ['nullable', 'string', 'max:240'], 'description' => ['nullable', 'string', 'max:3000'], 'category' => ['nullable', 'string', 'max:80'], 'status' => ['required', 'in:draft,published,archived'], 'is_featured' => ['nullable', 'boolean']]);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('tags')))));
        return $data;
    }

    private function syncModules(Request $request, ContentTemplate $template, ModuleRegistry $registry, RichTextSanitizer $sanitizer): void
    {
        $types = array_values(array_unique(array_filter($request->input('modules', []))));
        foreach ($types as $type) { $registry->get($type); }
        $existing = $template->modules()->get()->keyBy('module_type');
        $template->modules()->whereNotIn('module_type', $types)->delete();
        foreach ($types as $index => $type) {
            $current = $existing->get($type);
            $submitted = $request->input('module_content.'.$type);
            $content = is_array($submitted)
                ? $this->validatedModuleContent($type, $submitted, $registry, $sanitizer)
                : ($current?->content ?? $registry->defaults($type));
            TemplateModule::updateOrCreate(
                ['template_id' => $template->id, 'module_type' => $type],
                ['position' => $index + 1, 'content' => $content, 'settings' => $current?->settings ?? []]
            );
        }
    }

    private function validatedModuleContent(string $type, array $submitted, ModuleRegistry $registry, RichTextSanitizer $sanitizer): array
    {
        $definition = $registry->get($type);
        $rules = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $rules[$field['key']] = $this->templateFieldRules($field);
        }
        foreach ($definition['repeaters'] ?? [] as $repeater) {
            $rules[$repeater['key']] = ['array', 'min:'.($repeater['min'] ?? 0), 'max:'.$repeater['max']];
            foreach ($repeater['fields'] as $field) {
                $rules[$repeater['key'].'.*.'.$field['key']] = $this->templateFieldRules($field);
            }
        }

        $validated = validator($submitted, $rules, [], $this->moduleAttributeNames($definition))->validate();
        return $sanitizer->cleanPayload($validated);
    }

    private function templateFieldRules(array $field): array
    {
        return match ($field['type']) {
            'checkbox' => ['nullable', 'boolean'],
            'image' => ['nullable', 'string', 'max:2048', 'regex:#^/(?:images/templates|storage/template-assets)/[A-Za-z0-9_./-]+$#'],
            'select' => ['nullable', Rule::in(array_keys($field['options'] ?? []))],
            'asin' => ['nullable', 'string', 'max:20'],
            default => ['nullable', 'string', 'max:10000'],
        };
    }

    private function moduleAttributeNames(array $definition): array
    {
        $attributes = [];
        foreach ($definition['fields'] ?? [] as $field) $attributes[$field['key']] = $field['label'];
        foreach ($definition['repeaters'] ?? [] as $repeater) {
            $attributes[$repeater['key']] = $repeater['label'];
            foreach ($repeater['fields'] as $field) $attributes[$repeater['key'].'.*.'.$field['key']] = $repeater['label'].' '.$field['label'];
        }
        return $attributes;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template'; $slug = $base; $suffix = 2;
        while (ContentTemplate::where('slug', $slug)->exists()) { $slug = $base.'-'.$suffix++; }
        return $slug;
    }

    private function templateAssets(?ContentTemplate $template = null)
    {
        $assets = Asset::query()->whereNull('project_id')->where('source', 'template')->latest()->get()
            ->map(fn (Asset $asset) => $asset->toArray())->keyBy('template_path');
        $paths = [];
        $content = $template?->modules?->pluck('content')->all() ?? [];
        array_walk_recursive($content, function ($value) use (&$paths): void {
            if (is_string($value) && str_starts_with($value, '/images/templates/')) {
                $paths[] = $value;
            }
        });

        foreach (array_unique($paths) as $path) {
            if ($assets->has($path)) continue;
            $file = public_path(ltrim($path, '/'));
            $dimensions = is_file($file) ? getimagesize($file) : false;
            $assets->put($path, [
                'id' => $path,
                'path' => $path,
                'url' => $path,
                'thumbnail_url' => $path,
                'template_path' => $path,
                'original_name' => basename($path),
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'size_bytes' => is_file($file) ? filesize($file) : 0,
                'alt_text' => Str::headline(pathinfo($path, PATHINFO_FILENAME)),
                'read_only' => true,
            ]);
        }

        return $assets->values();
    }
}
