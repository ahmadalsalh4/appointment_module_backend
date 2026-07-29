<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = ['name'];

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function staff()
    {
        return $this->hasMany(Staff::class, 'category_id');
    }
}
