<?php

namespace App\Http\Controllers;

use App\Models\ContentTemplate;

class LandingController extends Controller
{
    public function __invoke()
    {
        return view('welcome', ['templates' => ContentTemplate::where('status', 'published')->where('is_featured', true)->withCount('modules')->take(3)->get()]);
    }
}
