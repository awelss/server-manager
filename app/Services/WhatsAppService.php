<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function send(string $to, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => config('services.whatsapp.api_key'),
            ])->post(config('services.whatsapp.url') . '/send-message', [
                'to' => $to,
                'message' => $message,
            ]);

            if (!$response->successful()) {
                Log::warning('WhatsApp send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                ]);
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp send error: ' . $e->getMessage());
            return false;
        }
    }
}
