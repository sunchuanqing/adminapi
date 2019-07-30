<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class Price_type extends Model
{
    use NodeTrait;
    protected $fillable = ['name', 'shop_id'];
}
