<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'person_id',
        'job_title',
        'email',
        'password',
        'category_id',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'person_id' => 'integer',
        'category_id' => 'integer',
        'admin_id' => 'integer',
    ];

    public const WORK_BLOCKS = [
        ['start' => '09:00', 'end' => '12:00'],
        ['start' => '13:00', 'end' => '17:00'],
    ];

    public const BUSINESS_TIMEZONE = 'Europe/Istanbul';

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function managingAdmin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
