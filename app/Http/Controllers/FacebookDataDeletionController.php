<?php

namespace App\Http\Controllers;

use App\Http\Requests\FacebookDataDeletionCallbackRequest;
use App\Services\FacebookDataDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacebookDataDeletionController extends Controller
{
    public function __construct(
        private readonly FacebookDataDeletionService $facebookDataDeletion,
    ) {}

    public function show(Request $request): View
    {
        return view('privacy.data-deletion', [
            'code' => $request->query('code'),
            'metaCallbackUrl' => route('api.auth.facebook.data-deletion'),
        ]);
    }

    public function callback(FacebookDataDeletionCallbackRequest $request): JsonResponse
    {
        $payload = $this->facebookDataDeletion->parseSignedRequest(
            (string) $request->validated('signed_request')
        );

        if ($payload === null) {
            return response()->json(['error' => 'Invalid signed_request'], 400);
        }

        $facebookUserId = (string) ($payload['user_id'] ?? '');
        if ($facebookUserId === '') {
            return response()->json(['error' => 'Missing user_id'], 400);
        }

        $confirmationCode = $this->facebookDataDeletion->purgeForFacebookUserId($facebookUserId);

        $statusUrl = route('privacy.data-deletion', [
            'code' => $confirmationCode,
        ], true);

        return response()->json([
            'url' => $statusUrl,
            'confirmation_code' => $confirmationCode,
        ]);
    }
}
