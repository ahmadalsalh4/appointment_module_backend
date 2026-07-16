<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    protected $table = 'admin';

    protected $fillable = ['staff_id', 'permission_level'];

    /**
     * Adminin kendi personel/login kaydı
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Bu adminin yönettiği personeller
     */
    public function managedStaff()
    {
        return $this->hasMany(Staff::class, 'admin_id');
    }
}
