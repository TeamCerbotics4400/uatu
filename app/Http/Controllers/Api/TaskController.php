<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tasks;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:PIT,STRATEGY,MATCH,MECHANICAL_SERVICE,PROGRAMMING_SERVICE',
            'team_id' => 'required|exists:teams,id',
            'match_id' => 'required|exists:matches,id',
            'priority_index' => 'nullable|integer|min:1|max:7',
        ]);

        $task = Tasks::create(array_merge($validated, [
            'status' => 'PENDING',
            'created_at' => now(),
        ]));

        return response()->json($task, 201);    
    }

    public function index()
    {
        $tasks = Tasks::all();
        return response()->json($tasks);
    }

    public function show($id)
    {
        $task = Tasks::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        return response()->json($task);
    }

    public function update(Request $request, $id)
    {
        $task = Tasks::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:PIT,STRATEGY,MATCH,MECHANICAL_SERVICE,PROGRAMMING_SERVICE',
            'status' => 'sometimes|required|string|in:PENDING,IN_PROGRESS,COMPLETED',
            'priority_index' => 'sometimes|nullable|integer|min:1|max:7',
            'assigned_user_id' => 'sometimes|nullable|exists:users,id',
            'team_id' => 'sometimes|required|exists:teams,id',
            'match_id' => 'sometimes|required|exists:matches,id',
        ]);

        $task->update($validated);

        return response()->json($task);
    }

    public function destroy($id)
    {
        $task = Tasks::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
