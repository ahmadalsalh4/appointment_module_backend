<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    // `id` is intentionally fillable: seeders and tests need to set known
// IDs so that the four hard-coded constants (PENDING=1, CONFIRMED=2,
// COMPLETED=3, CANCELLED=4) line up with FK references on the
// `appointments.state_id` column. There is no public route that calls
// Status::create(), so there is no attack surface for PK injection.
// If you ever expose such a route, REMOVE `id` from $fillable and
// convert the seeder/test factories to `firstOrCreate(['name' => ...])`
// + a separate update of the id.
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
