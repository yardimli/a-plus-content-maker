<?php

namespace App\Services;

use App\Models\AsinLookup;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AmazonProductDataService
{
    public function lookup(string $asin, User $user): array
    {
        $asin = strtoupper(trim($asin));
        if (! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            throw ValidationException::withMessages(['asin' => 'Enter a valid 10-character ASIN.']);
        }

        $cached = AsinLookup::where('asin', $asin)->where('was_successful', true)->where('expires_at', '>', now())->latest()->first();
        if ($cached) {
            return $cached->response_snapshot;
        }

        if (! config('services.amazon_product_data.key')) {
            throw ValidationException::withMessages(['asin' => 'ASIN lookup is not configured.']);
        }

        try {
            $response = Http::acceptJson()->timeout(12)->connectTimeout(5)->retry(2, 250)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-rapidapi-host' => config('services.amazon_product_data.host'),
                    'x-rapidapi-key' => config('services.amazon_product_data.key'),
                ])->get(rtrim(config('services.amazon_product_data.base_url'), '/').'/product/'.$asin);
            $response->throw();
            $payload = $response->json('data');
            if (! is_array($payload) || empty($payload['title'])) {
                throw ValidationException::withMessages(['asin' => 'No book data was returned for that ASIN.']);
            }
            $snapshot = [
                'asin' => $asin,
                'title' => (string) $payload['title'],
                'price' => $payload['price'] ?? null,
                'rating' => $payload['rating'] ?? null,
                'review_count' => $payload['review_count'] ?? null,
                'features' => array_slice($payload['features'] ?? [], 0, 20),
                'image_url' => $payload['image_url'] ?? null,
                'product_url' => $payload['product_url'] ?? null,
                'looked_up_at' => now()->toIso8601String(),
            ];
            AsinLookup::create(['user_id' => $user->id, 'asin' => $asin, 'was_successful' => true, 'status_code' => $response->status(), 'response_snapshot' => $snapshot, 'expires_at' => now()->addDay()]);

            return $snapshot;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RequestException $exception) {
            AsinLookup::create(['user_id' => $user->id, 'asin' => $asin, 'was_successful' => false, 'status_code' => $exception->response?->status(), 'error_code' => 'provider_error', 'expires_at' => now()->addMinutes(5)]);
            throw ValidationException::withMessages(['asin' => 'Amazon product data is temporarily unavailable. Please try again.']);
        }
    }
}
