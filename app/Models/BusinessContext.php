<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessContext extends Model
{
    protected $table = 'business_context';

    protected $fillable = ['name', 'content', 'updated_by'];
}
