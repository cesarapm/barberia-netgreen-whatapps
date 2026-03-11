<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Recibe mensajes entrantes desde n8n / Twilio.
     * Si is_human = true en la conversación, NO responde nada (el agente humano
     * manejará desde el front).
     * Si is_human = false, simplemente guarda y deja que n8n continúe con la IA.
     */
    public function receive(Request $request, MessageQuotaService $messageQuota)
    {
        $quotaSnapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($quotaSnapshot);

        if ($messageQuota->isBlocked($quotaSnapshot)) {
            return response()->json([
                'status' => 'blocked',
                ...$messageQuota->blockedPayload($quotaSnapshot),
            ], 429);
        }

        $payload = $request->input('data', []);

        $from           = $payload['fromE164']        ?? null;
        $body           = $payload['body']            ?? '';
        $twilioSid      = $payload['raw']['data']['messageSid'] ?? null;
        $conversationId = $payload['conversation_id'] ?? null;

        if (!$from) {
            Log::warning('Webhook sin fromE164', $request->all());
            return response()->json(['status' => 'ignored'], 200);
        }

        // Responde de inmediato al webhook y deja la persistencia/broadcast para después.
        dispatch(function () use ($from, $body, $twilioSid) {
            // Obtener o crear contacto
            $contact = Contact::firstOrCreate(['phone' => $from]);

            // Obtener o crear conversación activa
            $conversation = Conversation::firstOrCreate(
                ['contact_id' => $contact->id, 'status' => 'active'],
                ['is_human' => false]
            );

            // Guardar el mensaje entrante
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'body'            => $body,
                'direction'       => 'inbound',
                'sender_type'     => 'user',
                'twilio_sid'      => $twilioSid,
            ]);

            // Emitir por WebSocket al front
            broadcast(new MessageReceived($message));

            $messageQuota = app(MessageQuotaService::class);
            $newQuotaSnapshot = $messageQuota->snapshot();
            $messageQuota->notifyIfChanged($newQuotaSnapshot);

            Log::info('Mensaje guardado', [
                'contact'      => $from,
                'conversation' => $conversation->id,
                'is_human'     => $conversation->is_human,
            ]);
        })->afterResponse();

        return response()->json([
            'status'      => 'ok',
            'queued'      => true,
            'conversation_id' => $conversationId,
            'quota' => $quotaSnapshot,
        ], 200);
    }

    /**
     * Recibe el mensaje de respuesta que generó la IA en n8n
     * y lo guarda para mantener el hilo de la conversación.
     */
    public function storeOutbound(Request $request, MessageQuotaService $messageQuota)
    {
        $rawPayload = $request->all();
        $payload = $request->input('body');
        if (!is_array($payload)) {
            $payload = $request->all();
        }

        $conversationToken = trim((string) ($payload['conversation_id'] ?? ''));

        dispatch(function () use ($payload, $rawPayload) {
            Log::info('Webhook outbound received', $rawPayload);

            $messageQuota = app(MessageQuotaService::class);
            $quotaSnapshot = $messageQuota->snapshot();
            $messageQuota->notifyIfChanged($quotaSnapshot);

            if ($messageQuota->isBlocked($quotaSnapshot)) {
                Log::warning('Outbound bloqueado por cuota', [
                    'conversation_id' => $payload['conversation_id'] ?? null,
                ]);
                return;
            }

            $validated = validator($payload, [
                'conversation_id' => 'required|string',
                'body'            => 'required|string',
                'twilio_sid'      => 'nullable|string',
            ])->validate();

            $conversationToken = trim((string) $validated['conversation_id']);
            $messageBody = $validated['body'];
            $twilioSid = $validated['twilio_sid'] ?? null;

            $conversation = null;
            if (ctype_digit($conversationToken)) {
                $conversation = Conversation::find((int) $conversationToken);
            }

            if (!$conversation) {
                $parts = array_values(array_filter(explode('-', $conversationToken)));
                foreach ($parts as $part) {
                    $digits = preg_replace('/\D+/', '', $part);
                    if (!$digits) {
                        continue;
                    }

                    $candidates = ['+' . $digits, $digits];
                    foreach ($candidates as $candidatePhone) {
                        $contact = Contact::where('phone', $candidatePhone)->first();
                        if (!$contact) {
                            continue;
                        }

                        $conversation = Conversation::where('contact_id', $contact->id)
                            ->where('status', 'active')
                            ->latest('id')
                            ->first();

                        if (!$conversation) {
                            $conversation = Conversation::where('contact_id', $contact->id)
                                ->latest('id')
                                ->first();
                        }

                        if ($conversation) {
                            break 2;
                        }
                    }
                }
            }

            if (!$conversation) {
                $parts = array_values(array_filter(explode('-', $conversationToken)));
                $fallbackPart = count($parts) > 0 ? $parts[count($parts) - 1] : $conversationToken;
                $fallbackDigits = preg_replace('/\D+/', '', $fallbackPart);

                if ($fallbackDigits) {
                    $contact = Contact::firstOrCreate(['phone' => '+' . $fallbackDigits]);
                    $conversation = Conversation::firstOrCreate(
                        ['contact_id' => $contact->id, 'status' => 'active'],
                        ['is_human' => false]
                    );
                }
            }

            if (!$conversation) {
                Log::warning('Outbound ignorado: no se pudo resolver ni crear conversación', [
                    'conversation_id' => $conversationToken,
                    'twilio_sid' => $twilioSid,
                ]);
                return;
            }

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'body'            => $messageBody,
                'direction'       => 'outbound',
                'sender_type'     => 'bot',
                'twilio_sid'      => $twilioSid,
            ]);

            broadcast(new MessageReceived($message));
        })->afterResponse();

        return response()->json([
            'status' => 'ok',
            'queued' => true,
            'conversation_id' => $conversationToken,
            'quota' => null,
        ]);
    }
}
