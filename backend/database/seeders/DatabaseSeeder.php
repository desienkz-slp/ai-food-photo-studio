<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Idempotent: safe to run repeatedly (e.g. on every deploy) without
     * creating duplicates or clobbering an existing admin password.
     */
    public function run(): void
    {
        // Default super admin. Email is the login identifier; "superadmin" is
        // the display name and "photosuper" the initial password.
        $admin = User::firstOrCreate(
            ['email' => 'superadmin@photo.netora.id'],
            ['name' => 'superadmin', 'password' => Hash::make('photosuper'), 'is_super_admin' => true],
        );
        // Ensure the flag is set even if the row already existed before this column.
        if (! $admin->is_super_admin) {
            $admin->forceFill(['is_super_admin' => true])->save();
        }

        // Default workspace, linked to the admin as owner (idempotent).
        $workspace = Workspace::firstOrCreate(['name' => 'Dapur Utama']);
        $admin->workspaces()->syncWithoutDetaching([$workspace->id => ['role' => 'owner']]);
    }
}
