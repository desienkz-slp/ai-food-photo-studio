<?php
namespace App\Http\Controllers;

use App\Services\AiPhotoEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ChatGPT-style stateless photo editing. Accepts an uploaded image plus a text
 * instruction, returns the edited image inline as a data URI. Nothing is
 * persisted on the server.
 */
class PhotoEditController extends Controller
{
    private const MAX_UPLOAD_KB = 8192;

    public function edit(Request $request, AiPhotoEditor $editor): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.self::MAX_UPLOAD_KB],
            'instruction' => ['required', 'string', 'max:2000'],
        ]);

        $file = $validated['image'];
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        abort_unless($info && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true), 422, 'Unsupported image.');

        try {
            $result = $editor->edit($bytes, $info['mime'], $validated['instruction']);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Foto tidak dapat diedit. Periksa model edit gambar dan provider.'], 502);
        }

        return response()->json(['data' => [
            'image' => 'data:'.$result->mime.';base64,'.base64_encode($result->bytes),
            'mime' => $result->mime,
            'ext' => $result->ext,
        ]]);
    }
}
