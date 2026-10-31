<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetController extends Controller
{
    private const MAX_IMAGE_PIXELS = 4_000_000;

    /** @var (callable(int): int)|null */
    private $assetStatusUpdater;

    public function setAssetStatusUpdater(callable $updater): self
    {
        $this->assetStatusUpdater = $updater;
        return $this;
    }

    private function commitAsset(int $id): int
    {
        return ($this->assetStatusUpdater ?? static function (int $id): int {
            return DB::table('assets')->where('id', $id)->update(['status' => 'committed', 'updated_at' => now()]);
        })($id);
    }

    public function store(Request $request, Workspace $workspace)
    {
        Gate::authorize('upload', $workspace);
        validator(['idempotency_key' => $request->header('Idempotency-Key')], ['idempotency_key' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9._~-]+\z/']])->validate();
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimetypes:image/png,image/jpeg']]);
        $file = $request->file('file');
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] > 10000 || $info[1] > 10000 || $info[0] * $info[1] > self::MAX_IMAGE_PIXELS || ! in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            abort(422, 'Invalid image');
        }
        $decoded = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if ($decoded === false) {
            abort(422, 'Invalid image');
        }
        imagedestroy($decoded);
        $path = 'originals/'.Str::uuid();
        $checksum = hash_file('sha256', $file->getRealPath());
        $key = $request->header('Idempotency-Key');
        try {
            $id = DB::table('assets')->insertGetId(['workspace_id' => $workspace->id, 'idempotency_key' => $key, 'path' => $path, 'sha256' => $checksum, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            $existing = DB::table('assets')->where('workspace_id', $workspace->id)->where('idempotency_key', $key)->first();
            if (! $existing) { throw $exception; }
            if (! hash_equals($existing->sha256, $checksum)) { abort(409, 'Idempotency key already used for a different payload'); }
            if ($existing->status !== 'committed') { abort(409, 'Upload is pending reconciliation'); }
            if (config('filesystems.disks.asset_originals.driver') !== 'local') { abort(503); }
            $original = Storage::disk('asset_originals')->path($existing->path);
            $storedChecksum = @hash_file('sha256', $original);
            if ($storedChecksum === false || ! hash_equals($existing->sha256, $storedChecksum)) {
                abort(409, 'Original is unavailable or corrupted; reconciliation required');
            }
            return response()->json(['data' => ['id' => $existing->id, 'sha256' => $existing->sha256]], 201);
        }
        $disk = Storage::disk('asset_originals');
        if (config('filesystems.disks.asset_originals.driver') !== 'local') {
            abort(503);
        }
        $absolute = $disk->path($path);
        if (! is_dir(dirname($absolute)) && ! @mkdir(dirname($absolute), 0700, true) && ! is_dir(dirname($absolute))) {
            abort(503);
        }
        $handle = @fopen($absolute, 'x');
        if ($handle === false) {
            abort(file_exists($absolute) ? 409 : 503);
        }
        try {
            if (! chmod($absolute, 0600) || fwrite($handle, file_get_contents($file->getRealPath())) !== $file->getSize()) {
                throw new \RuntimeException('Unable to store original');
            }
            fclose($handle);
            $handle = null;
        } catch (\Throwable $exception) {
            if (is_resource($handle)) { fclose($handle); }
            @unlink($absolute);
            throw $exception;
        }

        // The update result can be ambiguous (the DB may have committed before
        // throwing). Never unlink after entering this phase; reconciliation of
        // pending/orphaned assets is deliberately a separate concern.
        $updated = $this->commitAsset($id);
        if ($updated !== 1) {
            throw new \RuntimeException('Asset status update affected an unexpected number of rows');
        }
        return response()->json(['data' => ['id' => $id, 'sha256' => $checksum]], 201);
    }
}
