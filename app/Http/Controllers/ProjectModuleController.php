<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectModule;
use App\Services\ModuleRegistry;
use App\Services\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectModuleController extends Controller
{
    public function store(Request $request, Project $project, ModuleRegistry $registry)
    {
        $this->authorize('update', $project);
        $type = $request->validate(['module_type' => ['required', 'string']])['module_type'];
        $registry->get($type);
        $module = ProjectModule::create([
            'uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'module_type' => $type,
            'position' => ($project->modules()->max('position') ?? 0) + 1, 'content' => $registry->defaults($type), 'settings' => [],
        ]);
        $project->update(['last_saved_at' => now()]);
        return response()->json(['ok' => true, 'data' => $module], 201);
    }

    public function update(Request $request, Project $project, ProjectModule $module, ModuleRegistry $registry, RichTextSanitizer $sanitizer)
    {
        $this->authorize('update', $project);
        abort_unless($module->project_id === $project->id, 404);
        $request->validate(['version' => ['required', 'integer']]);
        if ((int) $request->version !== $module->version) {
            return response()->json(['ok' => false, 'message' => 'This module changed in another session. Refresh before continuing.'], 409);
        }
        $data = $request->validate($registry->rules($module->module_type));
        $module->update(['content' => $sanitizer->cleanPayload($data['content']), 'version' => $module->version + 1]);
        $project->update(['last_saved_at' => now()]);
        return response()->json(['ok' => true, 'data' => $module->fresh()]);
    }

    public function destroy(Project $project, ProjectModule $module)
    {
        $this->authorize('update', $project);
        abort_unless($module->project_id === $project->id, 404);
        DB::transaction(function () use ($project, $module) {
            $position = $module->position;
            $module->delete();
            $project->modules()->where('position', '>', $position)->decrement('position');
        });
        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $ids = $request->validate(['modules' => ['required', 'array'], 'modules.*' => ['uuid']])['modules'];
        $actual = $project->modules()->pluck('uuid')->sort()->values()->all();
        $submitted = collect($ids)->unique()->sort()->values()->all();
        abort_unless($actual === $submitted, 422, 'The module order is incomplete.');
        DB::transaction(function () use ($project, $ids) {
            foreach ($ids as $index => $uuid) {
                $project->modules()->where('uuid', $uuid)->update(['position' => $index + 1001]);
            }
            $project->modules()->where('position', '>=', 1001)->decrement('position', 1000);
        });
        return response()->json(['ok' => true]);
    }
}
