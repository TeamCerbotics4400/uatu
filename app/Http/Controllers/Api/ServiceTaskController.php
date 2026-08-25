<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceTask;
use Illuminate\Http\Request;

class ServiceTaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:PENDING,ASSIGNED,IN_PROGRESS,COMPLETED',
            'assigned_team' => 'required|exists:teams,id',
            'assigned_user' => 'required|exists:users,id',
            'started_at' => 'nullable|date_format:Y-m-d H:i:s',
            'completed_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        $servicetask = ServiceTask::create($validated);

        return response()->json($servicetask, 201);
    }

    public function index()
    {
        $servicetasks = ServiceTask::all();
        return response()->json($servicetasks);
    }

    public function show($id)
    {
        $servicetask = ServiceTask::find($id);

        if (!$servicetask) {
            return response()->json(['message' => 'ServiceTask not found'], 404);
        }

        return response()->json($servicetask);
    }

    public function update(Request $request, $id)
    {
        $servicetask = ServiceTask::find($id);

        if (!$servicetask) {
            return response()->json(['message' => 'ServiceTask not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:PENDING,ASSIGNED,IN_PROGRESS,COMPLETED',
            'assigned_team' => 'sometimes|required|exists:teams,id',
            'assigned_user' => 'sometimes|required|exists:users,id',
            'started_at' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
            'completed_at' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
        ]);

        $servicetask->update($validated);

        return response()->json($servicetask);
    }

    public function destroy($id)
    {
        $servicetask = ServiceTask::find($id);

        if (!$servicetask) {
            return response()->json(['message' => 'ServiceTask not found'], 404);
        }

        $servicetask->delete();

        return response()->json(['message' => 'ServiceTask deleted successfully']);
    }
}