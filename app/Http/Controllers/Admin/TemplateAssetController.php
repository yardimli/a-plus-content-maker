<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;

class TemplateAssetController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'alt_text' => ['required', 'string', 'max:250'],
        ]);
        $file = $data['image'];
        $dimensions = getimagesize($file->getRealPath());
        abort_unless($dimensions !== false, 422, 'The uploaded file is not a valid image.');
        $path = $file->store('template-assets/'.$request->user()->id, 'public');
        $asset = Asset::create([
            'user_id' => $request->user()->id,
            'project_id' => null,
            'source' => 'template',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->extension(),
            'size_bytes' => $file->getSize(),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'alt_text' => $data['alt_text'],
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        return response()->json(['ok' => true, 'data' => $asset], 201);
    }
}
