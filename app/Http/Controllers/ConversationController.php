<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageQuotaService;
use App\Services\TwilioService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Lista conversaciones activas.
     * - Admin: ve todas.
     * - Asesor: solo las asignadas a él.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Conversation::with(['contact', 'lastMessage', 'assignedAgent'])
            ->where('status', 'active');

        if ($user->hasRole('asesor')) {
            $query->where('assigned_to', $user->id);
        }

        $conversations = $query->orderByDesc('updated_at')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'is_human'     => $c->is_human,
                'status'       => $c->status,
                'contact'      => [
                    'id'    => $c->contact->id,
                    'phone' => $c->contact->phone,
                    'name'  => $c->contact->name,
                ],
                'assigned_to'  => $c->assignedAgent ? [
                    'id'   => $c->assignedAgent->id,
                    'name' => $c->assignedAgent->name,
                ] : null,
                'last_message' => $c->lastMessage?->body,
                'updated_at'   => $c->updated_at,
            ]);

        return response()->json($conversations);
    }

    /**
     * Devuelve todos los mensajes de una conversación.
     */
    public function messages(Conversation $conversation)
    {
        return response()->json(
            $conversation->messages()->orderBy('created_at')->get()
        );
    }

    /**
     * Activa/desactiva el modo humano de una conversación.
     * is_human = true  → el agente humano responde desde el front
     * is_human = false → n8n / IA retoma el control
     */
    public function toggleHuman(Conversation $conversation)
    {
        $conversation->update(['is_human' => !$conversation->is_human]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'is_human'        => $conversation->is_human,
        ]);
    }

    /**
     * El agente humano envía un mensaje desde el front.
     * Solo permitido cuando is_human = true.
     */
    public function sendHuman(
        Request $request,
        Conversation $conversation,
        TwilioService $twilio,
        MessageQuotaService $messageQuota
    )
    {
        $quotaSnapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($quotaSnapshot);

        if ($messageQuota->isBlocked($quotaSnapshot)) {
            return response()->json($messageQuota->blockedPayload($quotaSnapshot), 429);
        }

        if (!$conversation->is_human) {
            return response()->json(['error' => 'La conversación no está en modo humano.'], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:1600',
        ]);

        // Enviar el mensaje real por WhatsApp vía Twilio
        $twilioSid = $twilio->sendWhatsApp(
            $conversation->contact->phone,
            $validated['body']
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'body'            => $validated['body'],
            'direction'       => 'outbound',
            'sender_type'     => 'human_agent',
            'twilio_sid'      => $twilioSid,
        ]);

        broadcast(new MessageReceived($message));

        $quotaSnapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($quotaSnapshot);

        return response()->json([
            'status' => 'sent',
            'message_id' => $message->id,
            'quota' => $quotaSnapshot,
        ]);
    }
}
