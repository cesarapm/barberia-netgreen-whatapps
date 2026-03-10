<?php

namespace App\Services;

use App\Events\MessageQuotaUpdated;
use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MessageQuotaService
{
    public function snapshot(): array
    {
        $monthlyQuota = (int) config('messaging.monthly_quota', 1000);
        $warningPercent = max(1, min(99, (int) config('messaging.warning_percent', 80)));

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $period = $startOfMonth->format('Y-m');

        $used = Message::query()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        if ($monthlyQuota <= 0) {
            return [
                'period' => $period,
                'monthly_quota' => $monthlyQuota,
                'used' => $used,
                'remaining' => null,
                'usage_percent' => null,
                'warning_percent' => $warningPercent,
                'warning' => false,
                'blocked' => false,
                'status' => 'unlimited',
            ];
        }

        $remaining = max($monthlyQuota - $used, 0);
        $usagePercent = (int) floor(($used / $monthlyQuota) * 100);
        $blocked = $used >= $monthlyQuota;
        $warning = !$blocked && $usagePercent >= $warningPercent;

        return [
            'period' => $period,
            'monthly_quota' => $monthlyQuota,
            'used' => $used,
            'remaining' => $remaining,
            'usage_percent' => min($usagePercent, 100),
            'warning_percent' => $warningPercent,
            'warning' => $warning,
            'blocked' => $blocked,
            'status' => $blocked ? 'blocked' : ($warning ? 'warning' : 'ok'),
        ];
    }

    public function notifyIfChanged(?array $snapshot = null): void
    {
        $snapshot ??= $this->snapshot();

        $cacheKey = $this->statusCacheKey($snapshot['period']);
        $lastStatus = Cache::get($cacheKey);

        if ($lastStatus === $snapshot['status']) {
            return;
        }

        Cache::put($cacheKey, $snapshot['status'], $this->cacheExpiry());
        broadcast(new MessageQuotaUpdated($snapshot));
    }

    public function isBlocked(?array $snapshot = null): bool
    {
        $snapshot ??= $this->snapshot();
        return (bool) ($snapshot['blocked'] ?? false);
    }

    public function blockedPayload(?array $snapshot = null): array
    {
        $snapshot ??= $this->snapshot();

        return [
            'error' => 'Cupo mensual de mensajes agotado.',
            'quota' => $snapshot,
        ];
    }

    private function statusCacheKey(string $period): string
    {
        return "message_quota:last_status:{$period}";
    }

    private function cacheExpiry(): Carbon
    {
        return now()->endOfMonth()->addDay();
    }
}
