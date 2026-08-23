<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasFactory, Notifiable;

    public const COORDINATOR_ROLES = ['coordinator_medical', 'coordinator_nursing_junior', 'coordinator_audit'];

    public const ROLE_LABELS = [
        'administrator' => 'Administrador',
        'coordinator_medical' => 'Coordinador médico',
        'coordinator_nursing_junior' => 'Coordinador Jr. de enfermería',
        'coordinator_audit' => 'Coordinadora de Auditoría',
        'quality' => 'Calidad',
        'collaborator' => 'Colaborador',
    ];

    protected $fillable = ['organization_id', 'area_id', 'name', 'email', 'password', 'role', 'is_active', 'email_verified_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function coordinatedAreas(): HasMany
    {
        return $this->hasMany(Area::class, 'coordinator_id');
    }

    public function isCoordinator(): bool
    {
        return in_array($this->role, self::COORDINATOR_ROLES, true);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
