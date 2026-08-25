<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MXTask;
use Illuminate\Http\Request;

class MXTaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:PIT,MATCH',
            'status' => 'required|string|in:IN_PROGRESS',
            'assigned_user' => 'required|exists:users,id',
            'started_at' => 'nullable|date_format:Y-m-d H:i:s',
            'completed_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        $mxtask = MXTask::create($validated);

        return response()->json($mxtask, 201);
    }

    public function index()
    {
        $mxtasks = MXTask::all();
        return response()->json($mxtasks);
    }

    public function show($id)
    {
        $mxtask = MXTask::find($id);

        if (!$mxtask) {
            return response()->json(['message' => 'MXTask not found'], 404);
        }

        return response()->json($mxtask);
    }

    public function update(Request $request, $id)
    {
        $mxtask = MXTask::find($id);

        if (!$mxtask) {
            return response()->json(['message' => 'MXTask not found'], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:PIT,MATCH',
            'status' => 'sometimes|required|string|in:IN_PROGRESS',
            'assigned_user' => 'sometimes|required|exists:users,id',
            'started_at' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
            'completed_at' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
        ]);

        $mxtask->update($validated);

        return response()->json($mxtask);
    }

    public function destroy($id)
    {
        $mxtask = MXTask::find($id);

        if (!$mxtask) {
            return response()->json(['message' => 'MXTask not found'], 404);
        }

        $mxtask->delete();

        return response()->json(['message' => 'MXTask deleted successfully']);
    }
}