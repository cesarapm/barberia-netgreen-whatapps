<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    private string $sid;
    private string $token;
    private string $from;
    private string $baseUrl;

    public function __construct()
    {
        $this->sid     = config('services.twilio.sid');
        $this->token   = config('services.twilio.token');
        $this->from    = config('services.twilio.from');
        $this->baseUrl = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";
    }

    /**
     * Envía un mensaje de WhatsApp al número destino.
     *
     * @param  string $to   Número en formato E.164, e.g. +5214445087305
     * @param  string $body Texto del mensaje
     * @return string|null  Twilio MessageSid si fue exitoso, null si falló
     */
    public function sendWhatsApp(string $to, string $body): ?string
    {
        // Twilio requiere el prefijo whatsapp:
        $toFormatted = str_starts_with($to, 'whatsapp:') ? $to : "whatsapp:{$to}";

        $response = Http::withBasicAuth($this->sid, $this->token)
            ->asForm()
            ->post($this->baseUrl, [
                'From' => $this->from,
                'To'   => $toFormatted,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            Log::error('Twilio send error', [
                'status'   => $response->status(),
                'response' => $response->json(),
                'to'       => $toFormatted,
            ]);
            return null;
        }

        return $response->json('sid');
    }
}
