<?php

namespace App\Models;

use App\Support\SearchHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'staff_id',
        'customer_id',
        'service_id',
        'state_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'state_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // Keep the `conflict_key` column in sync. It's set to a
        // deterministic "<staff>|<start>" string for active appointments
        // and NULL for terminal ones. The unique index
        // `appointments_conflict_key_unique` lets multiple NULLs coexist
        // (NULL ≠ NULL in unique indexes on every supported driver) so a
        // COMPLETED or CANCELLED appointment at 10:00 does NOT block a
        // fresh booking at the same time. This is the authoritative
        // double-booking guard; the in-app scopeConflicting() remains as
        // a fast filter.
        static::saving(function (self $appointment) {
            $isTerminal = in_array(
                (int) $appointment->state_id,
                [Status::COMPLETED, Status::CANCELLED],
                true,
            );
            if ($isTerminal) {
                $appointment->conflict_key = null;

                return;
            }
            $start = $appointment->start_date instanceof \DateTimeInterface
                ? $appointment->start_date->format('Y-m-d H:i:s')
                : (string) $appointment->start_date;
            $appointment->conflict_key = $appointment->staff_id.'|'.$start;
        });
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'state_id');
    }

    public function scopeConflicting($query, $staffId, $start, $end, $excludeId = null)
    {
        // NOTE: This scope is a fast in-app filter. The authoritative
        // double-booking guard is the UNIQUE(staff_id, start_date) index
        // added in migration 2026_07_28_000001 — the DB will reject any
        // concurrent insert that slips past this scope.
        $query->where('staff_id', $staffId)
            ->whereNotIn('state_id', [Status::COMPLETED, Status::CANCELLED])
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('start_date', $date);
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeByStatus($query, $statusId)
    {
        return $query->where('state_id', $statusId);
    }

    public function scopeSearchCustomer($query, $name)
    {
        $escaped = SearchHelper::likeContains($name);

        return $query->whereHas('customer.person', function ($q) use ($escaped) {
            $q->where(function ($q2) use ($escaped) {
                $q2->whereRaw('name LIKE ? '.SearchHelper::ESCAPE_CLAUSE, [$escaped])
                    ->orWhereRaw('surname LIKE ? '.SearchHelper::ESCAPE_CLAUSE, [$escaped]);
            });
        });
    }

    public function scopeTab($query, $tab)
    {
        return match ($tab) {
            'upcoming' => $query->whereIn('state_id', [Status::PENDING, Status::CONFIRMED])
                ->where('start_date', '>=', now()),
            'pending' => $query->where('state_id', Status::PENDING),
            'completed' => $query->where('state_id', Status::COMPLETED),
            'cancelled' => $query->where('state_id', Status::CANCELLED),
            default => $query,
        };
    }
}
