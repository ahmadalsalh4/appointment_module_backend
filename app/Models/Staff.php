<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'staff';

    // NOTE: `catagory_id` is a schema typo kept by design. See migration
    // 2026_07_20_061627_create_staff_table.php. Do not rename without a
    // coordinated DB + frontend change.
    //
    // `admin_id` is intentionally NOT in $fillable. It must be assigned
    // explicitly by an authenticated admin (StaffController::store), never
    // accepted from client input, to prevent a form-field leak from letting
    // a user reassign themselves to a different admin.
    protected $fillable = ['person_id', 'job_title', 'email', 'password', 'catagory_id'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
    ];

    // Sabit mesai blokları — öğle arası (12:00-13:00) otomatik hariç kalır.
    // Saatler BUSINESS_TIMEZONE içinde yorumlanır (Türkiye saati).
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

    /**
     * Bu staff'ın bağlı olduğu yönetici admin
     */
    public function managingAdmin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function category()
    {
        // NOTE: `catagory_id` typo — see comment on $fillable.
        return $this->belongsTo(Category::class, 'catagory_id');
    }
}
