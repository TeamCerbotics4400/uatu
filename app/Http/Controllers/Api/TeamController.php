<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'priority' => 'required|string|in:1,2,3,4,5,6,7',
            'required_service' => 'required|string|in:MECHANICAL,PROGRAMMING,BOTH',
            'current_service_status' => 'required|string|in:IN_PROGRESS,DONE,NOT_HELPED,PAUSE',
        ]);

        $team = Team::create($validated);

        return response()->json($team, 201);
    }

    public function index()
    {
        $teams = Team::all();
        return response()->json($teams);
    }

    public function show($id)
    {
        $team = Team::find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        return response()->json($team);
    }

    public function update(Request $request, $id)
    {
        $team = Team::find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'priority' => 'sometimes|required|string|in:1,2,3,4,5,6,7',
            'required_service' => 'sometimes|required|string|in:MECHANICAL,PROGRAMMING,BOTH',
            'current_service_status' => 'sometimes|required|string|in:IN_PROGRESS,DONE,NOT_HELPED,PAUSE',
        ]);

        $team->update($validated);

        return response()->json($team);
    }

    public function destroy($id)
    {
        $team = Team::find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $team->delete();

        return response()->json(['message' => 'Team deleted successfully']);
    }
}