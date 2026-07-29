<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'name'];

    const PENDING = 1;

    const CONFIRMED = 2;

    const COMPLETED = 3;

    const CANCELLED = 4;

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'state_id');
    }
}
