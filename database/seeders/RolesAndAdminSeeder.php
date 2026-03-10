<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear roles
        $admin  = Role::firstOrCreate(['name' => 'admin']);
        $asesor = Role::firstOrCreate(['name' => 'asesor']);

        // Crear usuario administrador si no existe
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Cesar Mata',
                'password' => Hash::make('123123'),
            ]
        );

        $adminUser->assignRole($admin);

        // Asegurar que el usuario ID 1 siempre tenga rol admin
        $userOne = User::find(1);
        if ($userOne && !$userOne->hasRole('admin')) {
            $userOne->assignRole($admin);
            $this->command->info('✓ Usuario ID 1 asignado como admin.');
        }

        $this->command->info('✓ Roles creados: admin, asesor');
        $this->command->info('✓ Admin: admin@chatbot.com / password123');
    }
}
