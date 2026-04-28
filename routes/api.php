<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FacebookAuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\SocialPostController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/auth/facebook/redirect', [FacebookAuthController::class, 'redirectToFacebook'])->name('api.auth.facebook.redirect');
});

// Session (web) middleware required so the browser is logged in after OAuth.
Route::middleware(['web', 'throttle:20,1'])->group(function () {
    Route::get('/auth/facebook/callback', [FacebookAuthController::class, 'handleCallback']);
});

Route::middleware(['auth:sanctum', 'workspace.context', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);

    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::get('/workspaces/{workspace}/publishing-accounts', [WorkspaceController::class, 'publishingAccounts'])->whereNumber('workspace');
    Route::get('/workspaces/{workspace}/social-accounts', [SocialAccountController::class, 'index'])->whereNumber('workspace');
    Route::post('/workspaces/{workspace}/social-accounts', [SocialAccountController::class, 'store'])->whereNumber('workspace');
    Route::put('/workspaces/{workspace}/social-accounts/{account}', [SocialAccountController::class, 'update'])->whereNumber('workspace')->whereNumber('account');
    Route::delete('/workspaces/{workspace}/social-accounts/{account}', [SocialAccountController::class, 'destroy'])->whereNumber('workspace')->whereNumber('account');
    Route::get('/workspaces/{workspace}/social-platforms', [SocialAccountController::class, 'socialPlatforms'])->whereNumber('workspace');
    Route::put('/workspaces/{workspace}/focused-platforms', [SocialAccountController::class, 'updateFocusedPlatforms'])->whereNumber('workspace');
    Route::post('/workspaces/{workspace}/social-platforms/{platformSlug}/connect', [SocialAccountController::class, 'connectPlatform'])->whereNumber('workspace');
    Route::delete('/workspaces/{workspace}/social-platforms/{platformSlug}/disconnect', [SocialAccountController::class, 'disconnectPlatform'])->whereNumber('workspace');
    Route::get('/workspaces/{workspace}/posts', [PostController::class, 'index'])->whereNumber('workspace');
    Route::post('/workspaces/{workspace}/posts', [PostController::class, 'store'])->whereNumber('workspace');
    Route::patch('/workspaces/{workspace}/posts/{post}', [PostController::class, 'update'])->whereNumber('workspace')->whereNumber('post');
    Route::delete('/workspaces/{workspace}/posts/{post}', [PostController::class, 'destroy'])->whereNumber('workspace')->whereNumber('post');
    Route::post('/posts/publish', [SocialPostController::class, 'publish']);
    Route::get('/social/accounts', [SocialPostController::class, 'getSocialAccounts']);
});
