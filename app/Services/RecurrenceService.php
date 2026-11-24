<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurrenceService
{
    public function occurrences(Carbon $start, array $rule, ?Carbon $until = null, ?int $count = null): Collection
    {
        $freq = $rule['freq'] ?? 'weekly';
        $interval = max(1, (int) ($rule['interval'] ?? 1));
        $end = $until ?: ($rule['until'] ?? null ? Carbon::parse($rule['until']) : null);
        $limit = $count ?: ($rule['count'] ?? null);
        $cap = 52;

        $dates = collect();
        $current = $start->copy();

        if ($freq === 'monthly') {
            $weekday = (int) ($rule['by_weekday'] ?? $start->dayOfWeek);
            $week = (int) ($rule['by_week'] ?? 1);
            $monthCursor = $start->copy()->startOfMonth();
            while (true) {
                $d = $this->nthWeekdayOfMonth($monthCursor, $weekday, $week);
                if ($d && $d->greaterThanOrEqualTo($start)) {
                    if ($end && $d->greaterThan($end)) {
                        break;
                    }
                    $dates->push($d->copy());
                    if ($limit && $dates->count() >= $limit) {
                        break;
                    }
                }
                $monthCursor->addMonth();
                if ($dates->count() >= $cap) {
                    break;
                }
                if ($end && $monthCursor->greaterThan($end)) {
                    break;
                }
            }

            return $dates;
        }

        $stepDays = match ($freq) {
            'daily' => 1 * $interval,
            'weekly' => 7 * $interval,
            'fortnightly' => 14 * $interval,
            default => 7,
        };

        // align to weekday for weekly/fortnightly
        if (in_array($freq, ['weekly', 'fortnightly'], true) && isset($rule['by_weekday'])) {
            $target = (int) $rule['by_weekday'];
            while ($current->dayOfWeek !== $target) {
                $current->addDay();
            }
        }

        while (true) {
            if ($end && $current->greaterThan($end)) {
                break;
            }
            if ($limit && $dates->count() >= $limit) {
                break;
            }
            if ($dates->count() >= $cap) {
                break;
            }
            $dates->push($current->copy());
            $current->addDays($stepDays);
        }

        return $dates;
    }

    private function nthWeekdayOfMonth(Carbon $monthStart, int $weekday, int $n): ?Carbon
    {
        $first = $monthStart->copy()->startOfMonth();
        // find first occurrence of weekday
        while ($first->dayOfWeek !== $weekday) {
            $first->addDay();
            if ($first->month !== $monthStart->month) {
                return null;
            }
        }
        $target = $first->copy()->addWeeks($n - 1);
        if ($target->month !== $monthStart->month) {
            // last occurrence fallback not available; skip
            return null;
        }

        return $target;
    }
}
