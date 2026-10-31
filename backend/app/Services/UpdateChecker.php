<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class UpdateChecker
{
    private const RELEASE_URL = 'https://api.github.com/repos/desienkz-slp/ai-food-photo-studio/releases/latest';
    private const MAX_PAYLOAD = 65536;

    public function check(): ?array
    {
        $current = config('release.version');
        if (! is_string($current) || ! preg_match('/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $current, $version)) return null;
        $current = implode('.', [$version[1], $version[2], $version[3]]);

        try {
            $response = Http::withOptions(['allow_redirects' => false, 'stream' => true])
                ->timeout(3)->connectTimeout(2)->acceptJson()->get(self::RELEASE_URL);
            $stream = $response->toPsrResponse()->getBody();
            try {
                if (! $response->successful()) return null;
                $body = '';
                while (! $stream->eof() && strlen($body) <= self::MAX_PAYLOAD) {
                    $chunk = $stream->read(min(8192, self::MAX_PAYLOAD + 1 - strlen($body)));
                    if ($chunk === '' && ! $stream->eof()) return null;
                    $body .= $chunk;
                }
                if (strlen($body) > self::MAX_PAYLOAD) return null;
            } finally {
                $stream->close();
            }
            $data = json_decode($body, true);
            $tag = is_array($data) ? ($data['tag_name'] ?? null) : null;
            if (!is_string($tag) || !preg_match('/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $tag, $m)) return null;
            $latest = implode('.', [$m[1], $m[2], $m[3]]);
            return ['update_available' => version_compare($latest, $current, '>'), 'latest_version' => $latest];
        } catch (\Throwable) { return null; }
    }
}
