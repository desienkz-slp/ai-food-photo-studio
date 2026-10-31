<?php
namespace App\Http\Controllers;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()->workspaces()->get(['workspaces.id', 'workspaces.name'])
            ->map(fn (Workspace $workspace) => ['id' => $workspace->id, 'name' => $workspace->name]);

        return response()->json(['data' => $workspaces]);
    }

    public function show(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);
        return response()->json(['data' => ['id' => $workspace->id, 'name' => $workspace->name]]);
    }
}
