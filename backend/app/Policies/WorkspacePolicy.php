<?php
namespace App\Policies;
use App\Models\User;
use App\Models\Workspace;
class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool { return $workspace->users()->whereKey($user->getKey())->exists(); }

    public function upload(User $user, Workspace $workspace): bool
    {
        return $workspace->users()->whereKey($user->getKey())
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }
}
