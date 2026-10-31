<?php

namespace App\Http\Controllers;

use App\Models\ProviderConfiguration;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class ProviderConfigurationController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeManagement($request, $workspace);

        return response()->json(['data' => ProviderConfiguration::where('workspace_id', $workspace->id)->get()]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeManagement($request, $workspace);
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['openai-compatible', 'other']), 'max:100'],
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
        $provider = ProviderConfiguration::firstOrNew(['workspace_id' => $workspace->id, 'type' => $data['type']]);
        if (! $provider->exists && empty($data['api_key'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['api_key' => 'The api key field is required.']);
        }
        $created = ! $provider->exists;
        $provider->workspace_id = $workspace->id;
        $provider->type = $data['type'];
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

    private function authorizeManagement(Request $request, Workspace $workspace): void
    {
        $membership = $workspace->users()->whereKey($request->user()->getKey())->first();
        abort_unless($membership && in_array($membership->pivot->role, ['owner', 'admin'], true), 403);
    }
}
