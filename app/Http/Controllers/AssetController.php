<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Services\SerperImageImportService;
use App\Services\SerperImageSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function search(Request $request, SerperImageSearchService $search)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:150'],
            'source' => ['required', Rule::in(SerperImageSearchService::SOURCES)],
        ]);

        return response()->json(['ok' => true, 'data' => $search->search($data['query'], $data['source'])]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'alt_text' => ['required', 'string', 'max:250']]);
        $file = $data['image'];
        $dimensions = getimagesize($file->getRealPath());
        abort_unless($dimensions !== false, 422, 'The uploaded file is not a valid image.');
        $path = $file->store('projects/'.$project->uuid, 'public');
        $asset = Asset::create([
            'user_id' => $request->user()->id, 'project_id' => $project->id, 'path' => $path, 'disk' => 'public',
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'extension' => $file->extension(),
            'size_bytes' => $file->getSize(), 'width' => $dimensions[0], 'height' => $dimensions[1], 'alt_text' => $data['alt_text'],
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);
        return response()->json(['ok' => true, 'data' => $asset], 201);
    }

    public function update(Request $request, Asset $asset)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->id === $asset->user_id, 403);
        $data = $request->validate(['alt_text' => ['required', 'string', 'max:250']]);
        $asset->update($data);

        return response()->json(['ok' => true, 'data' => $asset->fresh()]);
    }

    public function import(Request $request, Project $project, SerperImageSearchService $search, SerperImageImportService $importer)
    {
        $this->authorize('update', $project);
        $data = $request->validate(['token' => ['required', 'string']]);
        $result = $search->resultFromToken($data['token']);
        $asset = $importer->import($result, $request->user(), $project);

        return response()->json(['ok' => true, 'data' => $asset], 201);
    }

    public function destroy(Request $request, Asset $asset)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->id === $asset->user_id, 403);
        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();
        return response()->json(['ok' => true]);
    }
}
