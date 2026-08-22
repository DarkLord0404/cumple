<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        collect([
            ['name' => 'UCI', 'slug' => 'uci'],
            ['name' => 'Hospitalización', 'slug' => 'hospitalizacion'],
            ['name' => 'Urgencias', 'slug' => 'urgencias'],
            ['name' => 'Cirugía', 'slug' => 'cirugia'],
            ['name' => 'Coordinación asistencial', 'slug' => 'coordinacion-asistencial'],
        ])->each(fn (array $area) => Area::query()->updateOrCreate(['slug' => $area['slug']], $area));

        if (env('ADMIN_EMAIL') && env('ADMIN_PASSWORD')) {
            User::query()->updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                [
                    'name' => env('ADMIN_NAME', 'Administrador CUMPLE'),
                    'password' => env('ADMIN_PASSWORD'),
                    'role' => 'administrator',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
