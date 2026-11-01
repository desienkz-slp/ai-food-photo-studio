<?php
namespace App\Http\Controllers;

use App\Models\EditHistory;
use App\Services\AiPhotoEditor;
use App\Services\AiVisionAnalyzer;
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
    private const RETENTION_DAYS = 7;

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

    /**
     * Use the vision model to turn selected template presets + the user's text
     * into one refined, concrete English edit instruction. Returns text only;
     * the user reviews/edits it before running the actual edit.
     */
    public function enhance(Request $request, AiVisionAnalyzer $analyzer): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.self::MAX_UPLOAD_KB],
            'instruction' => ['nullable', 'string', 'max:2000'],
            'templates' => ['nullable', 'array', 'max:20'],
            'templates.*' => ['string', 'max:200'],
        ]);

        $bytes = (string) file_get_contents($validated['image']->getRealPath());
        $info = @getimagesizefromstring($bytes);
        abort_unless($info && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true), 422, 'Unsupported image.');

        $templates = array_values(array_filter($validated['templates'] ?? []));
        $wishlist = trim(($templates ? ('Selected goals: '.implode('; ', $templates).'. ') : '')
            .'User note: '.(trim((string) ($validated['instruction'] ?? '')) ?: '(none)'));

        // Meta-prompt: ask the vision model to look at the photo and produce a
        // single, specific food-photography edit instruction in English.
        $meta = 'You are a food-photography editing assistant. Look at this photo and the '
            .'desired goals, then write ONE concise, concrete editing instruction (max 80 words, '
            .'English, no preamble, no bullet points) that preserves the original dish, plating and '
            .'composition while achieving the goals. '.$wishlist;

        try {
            $prompt = $analyzer->analyze($bytes, $info['mime'], $meta);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Prompt tidak dapat dikembangkan. Periksa model vision dan provider.'], 502);
        }

        return response()->json(['data' => ['instruction' => trim($prompt)]]);
    }

    /** List the current user's recent edit history (metadata only). */
    public function index(): JsonResponse
    {
        $this->pruneExpired();

        $items = EditHistory::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['id', 'instruction', 'mime', 'ext', 'bytes', 'created_at']);

        return response()->json(['data' => $items]);
    }

    /** Delete a single history entry (file + row), scoped to the owner. */
    public function destroy(EditHistory $history): JsonResponse
    {
        abort_unless($history->user_id === Auth::id(), 404);
        Storage::disk('edit_results')->delete($history->path);
        $history->delete();

        return response()->json(['data' => ['id' => $history->id]]);
    }

    /**
     * Remove history older than the retention window (files + rows). Runs lazily
     * on list to avoid a separate scheduler; bounded by RETENTION_DAYS.
     */
    private function pruneExpired(): void
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        EditHistory::query()
            ->where('user_id', Auth::id())
            ->where('created_at', '<', $cutoff)
            ->get(['id', 'path'])
            ->each(function (EditHistory $expired) {
                Storage::disk('edit_results')->delete($expired->path);
                $expired->delete();
            });
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
