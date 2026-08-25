<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Match;
use Illuminate\Http\Request;

class MatchesController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|integer|unsigned',
            'blue_1' => 'nullable|exists:teams,id',
            'blue_2' => 'nullable|exists:teams,id',
            'blue_3' => 'nullable|exists:teams,id',
            'red_1' => 'nullable|exists:teams,id',
            'red_2' => 'nullable|exists:teams,id',
            'red_3' => 'nullable|exists:teams,id',
        ]);

        $matches = Matches::create($validated);

        return response()->json($match, 201);
    }

    public function index()
    {
        $matches = Matches::all();
        return response()->json($matches);
    }

    public function show($id)
    {
        $matches = Matches::find($id);

        if (!$matches) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($matches);
    }

    public function update(Request $request, $id)
    {
        $matches = Matches::find($id);

        if (!$matches) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        $validated = $request->validate([
            'number' => 'sometimes|required|integer|unsigned',
            'blue_1' => 'sometimes|nullable|exists:teams,id',
            'blue_2' => 'sometimes|nullable|exists:teams,id',
            'blue_3' => 'sometimes|nullable|exists:teams,id',
            'red_1' => 'sometimes|nullable|exists:teams,id',
            'red_2' => 'sometimes|nullable|exists:teams,id',
            'red_3' => 'sometimes|nullable|exists:teams,id',
        ]);

        $matches->update($validated);

        return response()->json($matches);
    }

    public function destroy($id)
    {
        $matches = Matches::find($id);

        if (!$match) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        $match->delete();

        return response()->json(['message' => 'Match deleted successfully']);
    }
}