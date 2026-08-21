<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCallLog;
use Illuminate\Http\Request;

class AiCallLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AiCallLog::query()->with(['user', 'project'])
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($query) use ($search) {
                    $query->where('model', 'like', $search)->orWhere('location', 'like', $search)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $search)->orWhere('email', 'like', $search));
                });
            })
            ->latest()->paginate(30)->withQueryString();

        $totals = AiCallLog::query()->where('status', 'succeeded')->selectRaw('COUNT(*) as calls, COALESCE(SUM(cost_usd), 0) as cost')->first();

        return view('admin.ai.logs', compact('logs', 'totals'));
    }
}
