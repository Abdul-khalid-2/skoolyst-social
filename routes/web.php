<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreatePostController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookDataDeletionController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PostsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/data-deletion', [FacebookDataDeletionController::class, 'show'])->name('data-deletion');
Route::redirect('/privacy/data-deletion', '/data-deletion', 301);

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware(['auth', 'workspace.context'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('create', [CreatePostController::class, 'index'])->name('create');
    Route::get('posts', [PostsController::class, 'index'])->name('posts.index');
    Route::get('scheduled', [PostsController::class, 'scheduled'])->name('posts.scheduled');
    Route::get('scheduled/{post}/edit', [PostsController::class, 'editScheduled'])->name('posts.scheduled.edit');
    Route::post('scheduled/{post}', [PostsController::class, 'updateScheduled'])->name('posts.scheduled.update');
    Route::delete('posts/{post}', [PostsController::class, 'destroy'])->name('posts.destroy');
    Route::get('accounts', [AccountsController::class, 'index'])->name('accounts');
    Route::delete('accounts/connections/{account}', [AccountsController::class, 'destroyConnection'])->name('accounts.connections.destroy');
    Route::get('analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('settings/workspace', [SettingsController::class, 'updateWorkspace'])->name('settings.workspace');
    Route::post('settings/facebook-data', [SettingsController::class, 'destroyFacebookData'])->name('settings.facebook-data.destroy');
    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('switch-account', [\App\Http\Controllers\WorkspaceSwitchController::class, 'index'])->name('workspace.switch');
    Route::post('switch-account', [\App\Http\Controllers\WorkspaceSwitchController::class, 'store'])->name('workspace.store');
    Route::post('switch-account/{workspace}', [\App\Http\Controllers\WorkspaceSwitchController::class, 'switch'])->name('workspace.switch.set');
    Route::get('notifications', [\App\Http\Controllers\NotificationsController::class, 'index'])->name('notifications');
    Route::get('activity', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity');

    // Role manager (owner + superadmin)
    Route::prefix('settings/roles')->name('settings.roles.')->group(function () {
        Route::post('/',                [\App\Http\Controllers\RoleManagerController::class, 'storeRole'])       ->name('store');
        Route::put('/{roleName}',       [\App\Http\Controllers\RoleManagerController::class, 'updateRole'])      ->name('update');
        Route::delete('/{roleName}',    [\App\Http\Controllers\RoleManagerController::class, 'destroyRole'])     ->name('destroy');
        Route::post('/{roleName}/sync', [\App\Http\Controllers\RoleManagerController::class, 'syncPermissions']) ->name('sync');
        Route::post('/repair',          [\App\Http\Controllers\RoleManagerController::class, 'repair'])          ->name('repair');
    });

    // Superadmin panel
    Route::prefix('settings/superadmin')->name('settings.superadmin.')->group(function () {
        Route::post('/make-superadmin',              [\App\Http\Controllers\RoleManagerController::class, 'makeSuperadmin'])   ->name('promote');
        Route::delete('/remove-superadmin/{user}',   [\App\Http\Controllers\RoleManagerController::class, 'removeSuperadmin'])->name('demote');
        Route::post('/toggle-user/{user}',           [\App\Http\Controllers\RoleManagerController::class, 'toggleUser'])      ->name('toggle-user');
        Route::post('/toggle-workspace/{workspace}', [\App\Http\Controllers\RoleManagerController::class, 'toggleWorkspace']) ->name('toggle-workspace');
        Route::post('/plan/{workspace}',             [\App\Http\Controllers\RoleManagerController::class, 'updatePlan'])      ->name('plan');
    });
});

// Temporary testing route: allows logout via direct URL hit.
Route::get('logout', function (Request $request): \Illuminate\Http\RedirectResponse {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout.get');
