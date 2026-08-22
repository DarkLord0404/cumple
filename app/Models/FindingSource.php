<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FindingSource extends Model
{
    protected $fillable = ['name', 'is_invima', 'is_active'];

    protected function casts(): array
    {
        return ['is_invima' => 'boolean', 'is_active' => 'boolean'];
    }
}
