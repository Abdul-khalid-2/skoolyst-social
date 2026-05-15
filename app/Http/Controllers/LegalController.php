<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy', $this->sharedViewData('Privacy Policy'));
    }

    public function terms(): View
    {
        return view('legal.terms', $this->sharedViewData('Terms of Service'));
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedViewData(string $pageTitle): array
    {
        return [
            'pageTitle' => $pageTitle,
            'lastUpdated' => 'May 15, 2026',
            'effectiveDate' => 'May 15, 2026',
            'businessName' => 'Skoolyst App',
            'productName' => 'Skoolyst Social',
            'contactEmail' => 'abdulkhalidmasood@gmail.com',
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
