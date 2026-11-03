<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function reply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            return response()->json([
                'reply' => 'Maglagay muna ng tanong.',
            ], 422);
        }

        $key = config('chatbot.key');

        if (! is_string($key) || $key === '') {
            return $this->unavailable();
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $key])
                ->connectTimeout(10)
                ->timeout(75)
                ->post(
                    rtrim(config('chatbot.url'), '/') . '/chat',
                    ['message' => $message]
                );
        } catch (ConnectionException $exception) {
            return $this->unavailable();
        }

        if (! $response->successful()) {
            return $this->unavailable();
        }

        $reply = $response->json('reply');

        if (! is_string($reply) || trim($reply) === '') {
            return $this->unavailable();
        }

        return response()->json([
            'reply' => $reply,
        ])->header('Cache-Control', 'no-store');
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'reply' =>
                'Hindi available ang assistant sa ngayon. '
                . 'Subukan muli mamaya o makipag-ugnayan sa barangay office.',
        ], 503)->header('Cache-Control', 'no-store');
    }
}