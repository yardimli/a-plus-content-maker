<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentTemplate;
use App\Models\TemplateModule;
use App\Services\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index()
    {
        return view('admin.templates.index', ['templates' => ContentTemplate::withCount('modules')->latest()->paginate(20)]);
    }

    public function create(ModuleRegistry $registry)
    {
        return view('admin.templates.form', ['template' => new ContentTemplate(), 'registry' => $registry->all()]);
    }

    public function store(Request $request, ModuleRegistry $registry)
    {
        $data = $this->validated($request);
        $template = ContentTemplate::create([...$data, 'created_by' => $request->user()->id, 'slug' => $this->uniqueSlug($data['name']), 'published_at' => $data['status'] === 'published' ? now() : null]);
        $this->syncModules($request, $template, $registry);
        return redirect()->route('admin.templates.edit', $template)->with('success', 'Template created.');
    }

    public function edit(ContentTemplate $template, ModuleRegistry $registry)
    {
        return view('admin.templates.form', ['template' => $template->load('modules'), 'registry' => $registry->all()]);
    }

    public function update(Request $request, ContentTemplate $template, ModuleRegistry $registry)
    {
        $data = $this->validated($request);
        $template->update([...$data, 'published_at' => $data['status'] === 'published' ? ($template->published_at ?: now()) : null]);
        $this->syncModules($request, $template, $registry);
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

    private function syncModules(Request $request, ContentTemplate $template, ModuleRegistry $registry): void
    {
        $types = array_values(array_filter($request->input('modules', [])));
        foreach ($types as $type) { $registry->get($type); }
        $template->modules()->delete();
        foreach ($types as $index => $type) {
            TemplateModule::create(['template_id' => $template->id, 'module_type' => $type, 'position' => $index + 1, 'content' => $registry->defaults($type), 'settings' => []]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template'; $slug = $base; $suffix = 2;
        while (ContentTemplate::where('slug', $slug)->exists()) { $slug = $base.'-'.$suffix++; }
        return $slug;
    }
}
