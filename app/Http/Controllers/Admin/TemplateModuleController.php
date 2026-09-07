<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentTemplate;
use App\Models\Project;
use App\Models\TemplateModule;
use App\Services\ModuleRegistry;
use App\Services\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TemplateModuleController extends Controller
{
    public function store(Request $request, ContentTemplate $template, ModuleRegistry $registry)
    {
        $type = $request->validate(['module_type' => ['required', 'string']])['module_type'];
        $registry->get($type);

        $module = DB::transaction(function () use ($template, $registry, $type) {
            $lockedTemplate = ContentTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            if ($lockedTemplate->modules()->count() >= Project::MAX_MODULES) {
                throw ValidationException::withMessages(['modules' => 'Amazon limits A+ Content to 5 modules.']);
            }

            return TemplateModule::create([
                'template_id' => $lockedTemplate->id,
                'module_type' => $type,
                'position' => ($lockedTemplate->modules()->max('position') ?? 0) + 1,
                'content' => $registry->defaults($type),
                'settings' => [],
            ]);
        });

        return response()->json(['ok' => true, 'data' => $this->editorModule($module)], 201);
    }

    public function update(Request $request, ContentTemplate $template, TemplateModule $module, ModuleRegistry $registry, RichTextSanitizer $sanitizer)
    {
        abort_unless($module->template_id === $template->id, 404);
        $data = $request->validate($this->rules($registry->get($module->module_type)));
        $module->update(['content' => $sanitizer->cleanPayload($data['content'])]);

        return response()->json(['ok' => true, 'data' => $this->editorModule($module->fresh())]);
    }

    public function destroy(ContentTemplate $template, TemplateModule $module)
    {
        abort_unless($module->template_id === $template->id, 404);
        DB::transaction(function () use ($template, $module) {
            $position = $module->position;
            $module->delete();
            $template->modules()->where('position', '>', $position)->decrement('position');
        });

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, ContentTemplate $template)
    {
        $ids = $request->validate(['modules' => ['required', 'array'], 'modules.*' => ['integer']])['modules'];
        $actual = $template->modules()->pluck('id')->sort()->values()->all();
        $submitted = collect($ids)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        abort_unless($actual === $submitted, 422, 'The module order is incomplete.');

        DB::transaction(function () use ($template, $ids) {
            foreach ($ids as $index => $id) {
                $template->modules()->whereKey($id)->update(['position' => $index + 1001]);
            }
            $template->modules()->where('position', '>=', 1001)->decrement('position', 1000);
        });

        return response()->json(['ok' => true]);
    }

    private function rules(array $definition): array
    {
        $rules = ['content' => ['required', 'array']];
        foreach ($definition['fields'] ?? [] as $field) {
            $rules['content.'.$field['key']] = $this->fieldRules($field);
        }
        foreach ($definition['repeaters'] ?? [] as $repeater) {
            $rules['content.'.$repeater['key']] = ['array', 'min:'.($repeater['min'] ?? 0), 'max:'.$repeater['max']];
            foreach ($repeater['fields'] as $field) {
                $rules['content.'.$repeater['key'].'.*.'.$field['key']] = $this->fieldRules($field);
            }
        }
        return $rules;
    }

    private function fieldRules(array $field): array
    {
        return match ($field['type']) {
            'checkbox' => ['nullable', 'boolean'],
            'image' => ['nullable', 'string', 'max:2048', 'regex:#^/(?:images/templates|storage/template-assets)/[A-Za-z0-9_./-]+$#'],
            'select' => ['nullable', Rule::in(array_keys($field['options'] ?? []))],
            default => ['nullable', 'string', 'max:10000'],
        };
    }

    private function editorModule(TemplateModule $module): array
    {
        return [
            'uuid' => $module->id,
            'module_type' => $module->module_type,
            'position' => $module->position,
            'content' => $module->content,
            'settings' => $module->settings,
        ];
    }
}
