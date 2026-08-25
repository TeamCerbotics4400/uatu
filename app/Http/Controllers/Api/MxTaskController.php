<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MxTask;
use Illuminate\Http\Request;

class MxTaskController extends Controller
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

        $mxtask = MxTask::create($validated);

        return response()->json($mxtask, 201);
    }

    public function index()
    {
        $mxtasks = MxTask::all();
        return response()->json($mxtasks);
    }

    public function show($id)
    {
        $mxtask = MxTask::find($id);

        if (!$mxtask) {
            return response()->json(['message' => 'MxTask not found'], 404);
        }

        return response()->json($mxtask);
    }

    public function update(Request $request, $id)
    {
        $mxtask = MxTask::find($id);

        if (!$mxtask) {
            return response()->json(['message' => 'MxTask not found'], 404);
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
        $mxtask = MxTask::find($id);

        if (!$mxtask) {
            return response()->json(['message' => 'MxTask not found'], 404);
        }

        $mxtask->delete();

        return response()->json(['message' => 'MxTask deleted successfully']);
    }
}