<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = ['name'];

    public function services()
    {
        return $this->hasMany(Service::class, 'catagory_id');
    }
    
    public function staff()
    {
        return $this->hasMany(Staff::class, 'catagory_id');
    }
}
