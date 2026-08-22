<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\FindingSource;
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

        collect([
            ['Auditoría Instituto Geológico Colombiano', false], ['Auditoría INVIMA BPE', true],
            ['Auditoría INVIMA BPER', true], ['Auditoría INVIMA BPM', true], ['Auditorías ARL', false],
            ['Auditorías a aseguradoras', false], ['Auditorías ICONTEC', false],
            ['Auditorías del Ministerio de Salud y Protección Social', false],
            ['Auditorías de Secretaría de Salud Departamental', false],
            ['Auditorías de Secretaría de Salud Distrital', false],
            ['Autoevaluación de acreditación RES 5095/2018', false],
            ['Autoevaluación de habilitación RES 3100/2019', false], ['Autoinspecciones', false],
            ['Comités institucionales', false], ['Hallazgos de referenciación interna', false],
            ['Hallazgos de referenciación externa', false], ['Incumplimiento de metas de indicadores', false],
            ['Incumplimiento de procesos y políticas institucionales', false], ['Matrices de riesgos', false],
            ['No conforme', false], ['PAMEC', false], ['Resultado de PQR', false],
            ['Revisión interna de procesos', false], ['Rondas de seguridad', false],
            ['Sesiones breves de seguridad del paciente', false],
        ])->each(fn (array $source) => FindingSource::updateOrCreate(['name' => $source[0]], ['is_invima' => $source[1]]));

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
