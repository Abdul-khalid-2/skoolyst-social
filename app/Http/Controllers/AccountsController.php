<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebDisconnectSocialAccountRequest;
use App\Models\SocialAccount;
use App\Services\AccountListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountsController extends Controller
{
    public function __construct(
        private readonly AccountListingService $accountListing,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->accountListing->getIndexData($request->user());

        return view('accounts.index', array_merge($data, [
            'title' => 'Social Accounts',
            'description' => 'Connect and manage your social media accounts.',
        ]));
    }

    public function destroyConnection(
        WebDisconnectSocialAccountRequest $request,
        SocialAccount $account,
    ): RedirectResponse {
        $this->accountListing->deleteAccount($request->user(), $account);

        return redirect()
            ->route('accounts')
            ->with('success', __('Connection removed.'));
    }
}
