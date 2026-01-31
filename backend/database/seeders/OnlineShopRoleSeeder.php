<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class OnlineShopRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define Permissions
        $permissions = [
            'online.orders',
            'online.scan',
            'online.analysis'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Create Roles
        $tokoOnline = Role::firstOrCreate(['name' => 'toko_online']);
        $tokoOnline->givePermissionTo([
            'online.orders',
            'online.scan',
            'inventory.view'
        ]);

        $leaderShopee = Role::firstOrCreate(['name' => 'leader_shopee']);
        $leaderShopee->givePermissionTo([
            'online.analysis',
            'inventory.view'
        ]);

        $this->command->info('Online Shop Roles & Permissions seeded!');
    }
}
