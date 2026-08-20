<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ModuleRegistry;

class BuilderController extends Controller
{
    public function show(Project $project, ModuleRegistry $registry)
    {
        $this->authorize('view', $project);
        $project->load(['modules', 'assets']);
        return view('builder.show', ['project' => $project, 'registry' => $registry->all()]);
    }
}
