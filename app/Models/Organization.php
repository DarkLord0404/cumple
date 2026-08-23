<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'contact_email', 'is_active', 'reminders_enabled', 'reminder_days', 'overdue_alerts_enabled', 'review_alerts_enabled'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reminders_enabled' => 'boolean',
            'reminder_days' => 'array',
            'overdue_alerts_enabled' => 'boolean',
            'review_alerts_enabled' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
