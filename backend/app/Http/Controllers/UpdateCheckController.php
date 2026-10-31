<?php
namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\UpdateChecker;
use Illuminate\Http\JsonResponse;

class UpdateCheckController extends Controller
{
    public function __invoke(Workspace $workspace, UpdateChecker $checker): JsonResponse
    {
        $role = $workspace->users()->whereKey(auth()->id())->value('role');
        abort_unless(in_array($role, ['owner', 'admin'], true), 403);
        $result = $checker->check();
        return $result === null
            ? response()->json(['update_available' => false], 503)
            : response()->json($result);
    }
}
