<?php
namespace App\Services;

use Symfony\Component\Process\Process;
use RuntimeException;

/**
 * Identity editor: verifies pixels round-trip unchanged through the Python
 * worker, then returns the image re-encoded as PNG. Output is visually
 * identical to the input; it serves as the safe fallback when no AI provider
 * is configured.
 */
class LocalIdentityCompositor implements ImageEditor
{
    public function render(string $original, ?string $prompt = null): EditedImage
    {
        $info = @getimagesizefromstring($original);
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] > 2048 || $info[1] > 2048 || $info[0] * $info[1] > 1000000) {
            throw new RuntimeException('Unsupported image');
        }
        $image = @imagecreatefromstring($original);
        if ($image === false) { throw new RuntimeException('Image decode failed'); }
        $rgb = '';
        for ($y = 0; $y < $info[1]; $y++) {
            for ($x = 0; $x < $info[0]; $x++) {
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if ($color['alpha'] !== 0) { imagedestroy($image); throw new RuntimeException('Transparency unsupported'); }
                $rgb .= chr($color['red']).chr($color['green']).chr($color['blue']);
            }
        }
        $process = new Process(['/usr/bin/python3', __DIR__.'/identity_compositor.py']);
        $process->setTimeout(30);
        $process->setInput(json_encode(['width' => $info[0], 'height' => $info[1], 'rgb' => base64_encode($rgb)], JSON_THROW_ON_ERROR));
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, 8, JSON_THROW_ON_ERROR);
        $output = base64_decode($result['rgb'] ?? '', true);
        if (! is_string($output) || ! hash_equals($rgb, $output)) { imagedestroy($image); throw new RuntimeException('Worker output mismatch'); }

        // Re-encode the verified pixels as PNG so downstream storage/preview
        // deals with a real image rather than raw RGB.
        ob_start();
        $ok = imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        if (! $ok || ! is_string($png) || $png === '') { throw new RuntimeException('PNG encode failed'); }

        return new EditedImage($png, 'image/png', 'png');
    }
}
