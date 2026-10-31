<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuditPendingUploads extends Command
{
    protected $signature = 'uploads:audit-pending';

    protected $description = 'Report pending uploads without changing records or files';

    public function handle(): int
    {
        $now = now();
        $disk = Storage::disk('asset_originals');

        $this->line('id | status | age_seconds | exists');
        DB::table('assets')->where('status', 'pending')->orderBy('id')
            ->select(['id', 'status', 'created_at', 'path'])
            ->chunkById(500, function ($assets) use ($disk, $now): void {
                foreach ($assets as $asset) {
                    $age = max(0, (int) Carbon::parse($asset->created_at)->diffInSeconds($now, false));
                    $exists = $disk->exists($asset->path) ? 'yes' : 'no';
                    $this->line("{$asset->id} | pending | {$age} | {$exists}");
                }
            });

        return self::SUCCESS;
    }
}
