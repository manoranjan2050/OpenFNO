<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Strategy;
use Illuminate\Http\Request;

class StrategyController extends Controller
{
    /** Built-in templates plus the user's own. */
    public function index(Request $request)
    {
        return response()->json(
            Strategy::whereNull('user_id')
                ->orWhere('user_id', $request->user()->id)
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'legs' => ['required', 'array', 'min:1'],
        ]);

        $strategy = $request->user()->strategies()->create($data);

        return response()->json($strategy, 201);
    }

    public function destroy(Request $request, Strategy $strategy)
    {
        abort_if($strategy->user_id !== $request->user()->id, 403);

        $strategy->delete();

        return response()->noContent();
    }
}
