<?php

namespace App\Support;

class SocialPublishErrorFormatter
{
    public static function format(string $rawError): string
    {
        $rawError = trim($rawError);
        if ($rawError === '') {
            return 'An unknown error occurred while publishing.';
        }

        $decoded = json_decode($rawError, true);

        if (isset($decoded['error']['code']) && (int) $decoded['error']['code'] === 1) {
            return 'Facebook rejected the request — image may be too large. Will retry.';
        }

        if (isset($decoded['error']['error_subcode']) && (int) $decoded['error']['error_subcode'] === 99) {
            return 'Facebook permission error — please reconnect your Facebook account.';
        }

        if (str_contains($rawError, 'cURL error 28') || str_contains($rawError, 'Operation timed out')) {
            return 'Request timed out while uploading to Facebook. Will retry.';
        }

        if (str_contains($rawError, 'Could not read Instagram media status')) {
            return 'Instagram is still processing the media. Will retry shortly.';
        }

        if (str_contains($rawError, 'Timeout waiting for Instagram media container')) {
            return 'Instagram took too long to process the media. Will retry shortly.';
        }

        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = (string) $decoded['error']['message'];
            $sub = isset($decoded['error']['error_user_msg']) ? (string) $decoded['error']['error_user_msg'] : null;
            $parts = array_filter([$msg, $sub]);

            return implode(' ', $parts);
        }

        return mb_strlen($rawError) > 500 ? mb_substr($rawError, 0, 500).'…' : $rawError;
    }
}
