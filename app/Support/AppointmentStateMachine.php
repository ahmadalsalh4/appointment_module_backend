<?php

namespace App\Support;

use App\Models\Status;

/**
 * Single source of truth for appointment state transitions.
 *
 * Allowed transitions:
 *   PENDING   -> CONFIRMED  (staff: confirm; admin: confirm)
 *   PENDING   -> CANCELLED  (customer: cancel; admin: cancel)
 *   CONFIRMED -> COMPLETED  (staff: complete; admin: complete)
 *   CONFIRMED -> CANCELLED  (staff: cancel; admin: cancel)
 *
 * Terminal states (COMPLETED, CANCELLED) accept no further transitions.
 */
class AppointmentStateMachine
{
    /**
     * Role-keyed allowed transitions map. 'any' applies to both staff and admin;
     * customer-initiated transitions are restricted via canTransition().
     *
     * @var array<int, array<string, array<int>>>
     */
    private const TRANSITIONS = [
        Status::PENDING => [
            'staff' => [Status::CONFIRMED, Status::CANCELLED],
            'admin' => [Status::CONFIRMED, Status::CANCELLED],
            'customer' => [Status::CANCELLED],
            'any' => [Status::CONFIRMED, Status::CANCELLED],
        ],
        Status::CONFIRMED => [
            'staff' => [Status::COMPLETED, Status::CANCELLED],
            'admin' => [Status::COMPLETED, Status::CANCELLED],
            'customer' => [],
            'any' => [Status::COMPLETED, Status::CANCELLED],
        ],
        Status::COMPLETED => [
            'staff' => [],
            'admin' => [],
            'customer' => [],
            'any' => [],
        ],
        Status::CANCELLED => [
            'staff' => [],
            'admin' => [],
            'customer' => [],
            'any' => [],
        ],
    ];

    /**
     * Returns the next allowed states from the given current state for
     * the given role.
     *
     * @return array<int>
     */
    public static function allowed(int $fromState, string $role): array
    {
        $map = self::TRANSITIONS[$fromState] ?? [];
        $roleList = $map[$role] ?? $map['any'] ?? [];

        return array_values(array_unique($roleList));
    }

    public static function canTransition(int $fromState, int $toState, string $role): bool
    {
        return in_array($toState, self::allowed($fromState, $role), true);
    }

    public static function isTerminal(int $state): bool
    {
        return in_array($state, [Status::COMPLETED, Status::CANCELLED], true);
    }

    /**
     * Validates that $toState is non-terminal-or-current. Returns null on
     * success, or a Turkish error message.
     */
    public static function errorMessage(int $fromState, int $toState, string $role): ?string
    {
        if ($fromState === $toState) {
            return 'Randevu zaten bu durumda.';
        }
        if (self::isTerminal($fromState)) {
            return 'Tamamlanmış veya iptal edilmiş randevular değiştirilemez.';
        }
        if (! self::canTransition($fromState, $toState, $role)) {
            return 'Bu durum geçişi geçersiz.';
        }

        return null;
    }
}
