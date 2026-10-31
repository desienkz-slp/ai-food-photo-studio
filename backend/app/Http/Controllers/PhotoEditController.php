<?php
namespace App\Http\Controllers;

use App\Models\EditHistory;
use App\Services\AiPhotoEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ChatGPT-style photo editing. Accepts an uploaded image plus a text
 * instruction, returns the edited image inline as a data URI, and persists the
 * result to the `edit_results` disk with a row in `edit_histories` so the user
 * can browse and download past edits.
 */
class PhotoEditController extends Controller
{
    private const MAX_UPLOAD_KB = 8192;
    private const HISTORY_LIMIT = 50;

    public function edit(Request $request, AiPhotoEditor $editor): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.self::MAX_UPLOAD_KB],
            'instruction' => ['required', 'string', 'max:2000'],
        ]);

        $bytes = (string) file_get_contents($validated['image']->getRealPath());
        $info = @getimagesizefromstring($bytes);
        abort_unless($info && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true), 422, 'Unsupported image.');

        try {
            $result = $editor->edit($bytes, $info['mime'], $validated['instruction']);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Foto tidak dapat diedit. Periksa model edit gambar dan provider.'], 502);
        }

        $path = 'u'.Auth::id().'/'.date('Ymd_His').'_'.Str::random(8).'.'.$result->ext;
        Storage::disk('edit_results')->put($path, $result->bytes);
        $history = EditHistory::create([
            'user_id' => Auth::id(),
            'instruction' => $validated['instruction'],
            'path' => $path,
            'mime' => $result->mime,
            'ext' => $result->ext,
            'sha256' => hash('sha256', $result->bytes),
            'bytes' => strlen($result->bytes),
        ]);

        return response()->json(['data' => [
            'id' => $history->id,
            'instruction' => $history->instruction,
            'image' => 'data:'.$result->mime.';base64,'.base64_encode($result->bytes),
            'mime' => $result->mime,
            'ext' => $result->ext,
            'created_at' => $history->created_at,
        ]]);
    }

    /** List the current user's recent edit history (metadata only). */
    public function index(): JsonResponse
    {
        $items = EditHistory::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['id', 'instruction', 'mime', 'ext', 'bytes', 'created_at']);

        return response()->json(['data' => $items]);
    }

    /** Stream a single history image for download, scoped to the owner. */
    public function download(EditHistory $history): Response
    {
        abort_unless($history->user_id === Auth::id(), 404);
        abort_unless(Storage::disk('edit_results')->exists($history->path), 404);

        return response(Storage::disk('edit_results')->get($history->path), 200, [
            'Content-Type' => $history->mime,
            'Content-Disposition' => 'attachment; filename="edit-'.$history->id.'.'.$history->ext.'"',
        ]);
    }
}
