<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function reply(Request $request): JsonResponse
    {
        // Validate incoming message
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            return response()->json([
                'reply' => 'Maglagay muna ng tanong.',
            ], 422);
        }

        // Read chatbot configuration
        $url = config('chatbot.url');
        $key = config('chatbot.key');

        // Check API URL
        if (
            ! is_string($url) ||
            ! filter_var($url, FILTER_VALIDATE_URL) ||
            ! in_array(
                parse_url($url, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            )
        ) {
            Log::error('Chatbot API URL missing or invalid');

            return $this->unavailable();
        }

        // Check API key
        if (! is_string($key) || trim($key) === '') {
            Log::error('Chatbot API key missing');

            return $this->unavailable();
        }

        // Build API endpoint
        $endpoint = rtrim($url, '/') . '/chat';

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-API-Key' => $key,
                ])
                ->connectTimeout(10)
                ->timeout(75)
                ->post($endpoint, [
                    'message' => $message,
                ]);

        } catch (ConnectionException $exception) {

            Log::error('Chatbot connection failed', [
                'error_type' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return $this->unavailable();
        }

        // Check API response status
        if (! $response->successful()) {

            Log::warning('Chatbot API request failed', [
                'status' => $response->status(),
            ]);

            return $this->unavailable();
        }

        // Read chatbot response
        $reply = $response->json('reply');

        if (! is_string($reply) || trim($reply) === '') {

            Log::warning('Chatbot returned invalid response', [
                'status' => $response->status(),
            ]);

            return $this->unavailable();
        }

        // Successful chatbot response
        return response()->json([
            'reply' => $reply,
        ])->header('Cache-Control', 'no-store');
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'reply' =>
                'Hindi available ang assistant sa ngayon. '
                . 'Subukan muli mamaya o makipag-ugnayan '
                . 'sa barangay office.',
        ], 503)->header('Cache-Control', 'no-store');
    }
}
