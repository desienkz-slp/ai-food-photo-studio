<?php

namespace App\Http\Controllers;

use App\Models\ProviderConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class ProviderConfigurationController extends Controller
{
    /**
     * Global provider list. Any authenticated tenant may read it (secrets are
     * never exposed); only the super admin may write it.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ProviderConfiguration::all()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_super_admin, 403);
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['openai-compatible', 'other']), 'max:100'],
            'communication_mode' => ['sometimes', 'string', Rule::in(['chat', 'message', 'response'])],
            'name' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = parse_url($value);
                $host = strtolower($parts['host'] ?? '');
                if (isset($parts['user']) || isset($parts['pass']) || filter_var($host, FILTER_VALIDATE_IP) || in_array($host, ['localhost', 'metadata.google.internal'], true) || str_ends_with($host, '.internal')) {
                    $fail('The endpoint must be a public HTTPS URL without credentials.');
                }
            }],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ]);
        $provider = ProviderConfiguration::firstOrNew(['type' => $data['type']]);
        if (! $provider->exists && empty($data['api_key'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['api_key' => 'The api key field is required.']);
        }
        $created = ! $provider->exists;
        $provider->type = $data['type'];
        $provider->communication_mode = $data['communication_mode'] ?? $provider->communication_mode ?? 'chat';
        $provider->model = $data['model'];
        $provider->name = $data['name'] ?? $provider->name;
        $provider->endpoint = $data['endpoint'] ?? $provider->endpoint;
        if (isset($data['api_key']) && $data['api_key'] !== '') {
            $provider->api_key = $data['api_key'];
        }
        try {
            $provider->save();
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                return response()->json(['message' => 'Provider configuration conflict. Retry the request.'], 409);
            }

            throw $exception;
        }

        return response()->json(['data' => $provider], $created ? 201 : 200);
    }
}
