<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'staff';

    protected $fillable = ['person_id', 'job_title', 'email', 'password', 'admin_id', 'catagory_id'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
    ];

    // Sabit mesai blokları — öğle arası (12:00-13:00) otomatik hariç kalır
    public const WORK_BLOCKS = [
        ['start' => '09:00', 'end' => '12:00'],
        ['start' => '13:00', 'end' => '17:00'],
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    /**
     * Bu staff'ın bağlı olduğu yönetici admin
     */
    public function managingAdmin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class, 'catagory_id');
    }
}
