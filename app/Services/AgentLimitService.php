<?php

namespace App\Services;

use App\Models\User;

class AgentLimitService
{
    public function snapshot(): array
    {
        $maxAgents = $this->maxAgents();
        $currentAgents = User::role('asesor')->count();

        return [
            'max_agents' => $maxAgents,
            'current_agents' => $currentAgents,
            'remaining_slots' => $maxAgents === null ? null : max($maxAgents - $currentAgents, 0),
            'is_unlimited' => $maxAgents === null,
            'can_create' => $maxAgents === null ? true : $currentAgents < $maxAgents,
        ];
    }

    public function canCreateAnother(): bool
    {
        return $this->snapshot()['can_create'];
    }

    public function maxAgents(): ?int
    {
        $raw = config('limits.max_agents', '*');

        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);

        if ($value === '' || $value === '*' || $value === '0') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
