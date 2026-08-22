<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FindingSource extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'is_invima', 'is_active'];

    protected function casts(): array
    {
        return ['is_invima' => 'boolean', 'is_active' => 'boolean'];
    }
}
