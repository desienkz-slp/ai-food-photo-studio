<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UpdateCheckController;

Route::get('/csrf-cookie', fn () => response()->noContent());
Route::middleware('throttle:5,1')->post('/login', [SessionController::class, 'store']);
Route::middleware('auth')->post('/logout', [SessionController::class, 'destroy']);
Route::middleware('auth')->get('/session', [SessionController::class, 'show']);

Route::middleware('auth')->get('/workspaces', [WorkspaceController::class, 'index']);
Route::middleware('auth')->get('/workspaces/{workspace}', [WorkspaceController::class, 'show']);
Route::middleware(['auth', 'throttle:10,1'])->get('/workspaces/{workspace}/updates', UpdateCheckController::class);
// Provider configuration is global (managed by the super admin, shared by all
// tenants). Reading is allowed for any authenticated user; writing is gated to
// the super admin inside the controller.
Route::middleware('auth')->get('/providers', [\App\Http\Controllers\ProviderConfigurationController::class, 'index']);
Route::middleware(['auth', 'throttle:10,1'])->get('/providers/{type}/models', [\App\Http\Controllers\ProviderConfigurationController::class, 'models'])->where('type', 'openai-compatible|other');
Route::middleware('auth')->post('/providers', [\App\Http\Controllers\ProviderConfigurationController::class, 'store']);
Route::middleware(['auth', 'throttle:10,1'])->post('/workspaces/{workspace}/assets', [\App\Http\Controllers\AssetController::class, 'store']);

Route::get('/', function () {
    return response()->json([
        'service' => 'ai-food-photo-studio-backend',
        'status' => 'ok',
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'ai-food-photo-studio-backend',
        'timestamp' => Carbon::now()->toISOString(),
    ]);
});
Route::middleware('auth')->get('/workspaces/{workspace}/assets', [\App\Http\Controllers\AssetVersionController::class, 'index']);
Route::middleware('auth')->get('/workspaces/{workspace}/assets/{asset}/image', [\App\Http\Controllers\ImageServeController::class, 'original']);
Route::middleware('auth')->get('/workspaces/{workspace}/assets/{asset}/processing-jobs/{job}/output', [\App\Http\Controllers\ImageServeController::class, 'output']);
Route::middleware('auth')->post('/workspaces/{workspace}/assets/{asset}/versions', [\App\Http\Controllers\AssetVersionController::class, 'store']);
Route::middleware('auth')->post('/workspaces/{workspace}/assets/{asset}/versions/{version}/approval', [\App\Http\Controllers\ApprovalExportController::class, 'approve']);
Route::middleware('auth')->post('/workspaces/{workspace}/assets/{asset}/versions/{version}/export', [\App\Http\Controllers\ApprovalExportController::class, 'export']);
Route::middleware('auth')->get('/workspaces/{workspace}/assets/{asset}/versions/{version}/processing-jobs', [\App\Http\Controllers\ProcessingJobController::class, 'index']);
Route::middleware('auth')->post('/workspaces/{workspace}/assets/{asset}/versions/{version}/processing-jobs', [\App\Http\Controllers\ProcessingJobController::class, 'store']);
Route::middleware('auth')->get('/workspaces/{workspace}/exports/{export}', [\App\Http\Controllers\ApprovalExportController::class, 'download']);
