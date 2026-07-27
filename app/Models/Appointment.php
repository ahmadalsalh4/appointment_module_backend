<?php

namespace App\Models;

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
        'end_date'   => 'datetime',
        'state_id'   => 'integer',
    ];

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
        $query->where('staff_id', $staffId)
            ->where('state_id', '!=', Status::CANCELLED)
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
        return $query->whereHas('customer.person', function ($q) use ($name) {
            $q->where('name', 'like', "%{$name}%")
                ->orWhere('surname', 'like', "%{$name}%");
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
