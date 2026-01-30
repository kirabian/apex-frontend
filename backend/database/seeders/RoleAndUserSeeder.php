<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset cache permission (penting agar role baru terbaca)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Daftar 10 Role PStore
        $roles = [
            'Super Admin',
            'Owner',
            'Manager Pusat',
            'Manager Cabang',
            'Supervisor',
            'Admin Gudang',
            'Admin Finance',
            'Kasir',
            'Sales/Promotor',
            'Teknisi'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // 3. Buat Akun Super Admin (Fabian)
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'Fabian Syah',
                'email' => 'admin@apexpos.com',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'branch_id' => null, // Super Admin Global
            ]
        );

        // 4. Tempelkan Role Super Admin ke Fabian
        $admin->assignRole('Super Admin');

        $this->command->info('Role & User Fabian berhasil dibuat!');
    }
}