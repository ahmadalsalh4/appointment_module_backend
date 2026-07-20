<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = ['name', 'surname', 'phone_number'];

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }
}
