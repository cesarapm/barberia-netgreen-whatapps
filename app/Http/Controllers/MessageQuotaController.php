<?php

namespace App\Http\Controllers;

use App\Services\MessageQuotaService;

class MessageQuotaController extends Controller
{
    public function show(MessageQuotaService $messageQuota)
    {
        $snapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($snapshot);

        return response()->json($snapshot);
    }
}
