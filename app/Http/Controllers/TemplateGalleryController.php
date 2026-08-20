<?php

namespace App\Http\Controllers;

use App\Models\ContentTemplate;
use Illuminate\Http\Request;

class TemplateGalleryController extends Controller
{
    public function index(Request $request)
    {
        $templates = ContentTemplate::where('status', 'published')->withCount('modules')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->q.'%')->orWhere('summary', 'like', '%'.$request->q.'%')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->category))
            ->orderByDesc('is_featured')->latest('published_at')->paginate(12)->withQueryString();

        return view('templates.index', ['templates' => $templates, 'categories' => ContentTemplate::where('status', 'published')->whereNotNull('category')->distinct()->pluck('category')]);
    }

    public function show(ContentTemplate $template)
    {
        abort_unless($template->status === 'published' || auth()->user()?->isAdmin(), 404);
        return view('templates.show', ['template' => $template->load('modules')]);
    }
}
