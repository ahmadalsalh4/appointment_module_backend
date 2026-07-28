<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    // NOTE: `catagory_id` is a schema typo kept by design. See migration
    // 2026_07_20_061631_create_services_table.php.
    protected $fillable = ['catagory_id', 'name', 'duration'];

    public function category()
    {
        // NOTE: `catagory_id` typo — see comment on $fillable.
        return $this->belongsTo(Category::class, 'catagory_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
