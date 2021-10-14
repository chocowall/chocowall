<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPackage extends Model
{
    use HasFactory;

    protected $table = 'group_package';

    protected $fillable = ['group_id', 'package_id'];
}
