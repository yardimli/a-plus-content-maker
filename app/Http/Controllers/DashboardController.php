<?php

namespace App\Http\Controllers;

use App\Models\ContentTemplate;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('dashboard', [
            'projects' => $request->user()->projects()->withCount('modules')->latest('updated_at')->take(6)->get(),
            'templates' => ContentTemplate::where('status', 'published')->where('is_featured', true)->withCount('modules')->take(3)->get(),
        ]);
    }
}
