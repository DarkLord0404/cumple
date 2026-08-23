<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueKairoIntegrationToken extends Command
{
    protected $signature = 'integration:kairo-token {organization : Slug de la organización}';
    protected $description = 'Crea o reemplaza la credencial privada para importar borradores desde Kairo';

    public function handle(): int
    {
        $organization = Organization::where('slug', $this->argument('organization'))->firstOrFail();
        $creator = $organization->users()->where('role', 'admin')->first() ?? $organization->users()->firstOrFail();
        $token = Str::random(80);

        IntegrationConnection::updateOrCreate(
            ['organization_id' => $organization->id, 'provider' => 'kairo'],
            ['created_by' => $creator->id, 'name' => 'Kairo Meet', 'token_hash' => hash('sha256', $token), 'is_active' => true]
        );

        $this->warn('Guarda esta credencial ahora; CUMPLE no la volverá a mostrar:');
        $this->line($token);

        return self::SUCCESS;
    }
}
