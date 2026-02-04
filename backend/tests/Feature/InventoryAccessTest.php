<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

class InventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndUserSeeder::class);
    }

    public function test_branch_user_can_only_see_users_in_same_branch()
    {
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);

        $userA1 = User::factory()->create(['branch_id' => $branchA->id, 'username' => 'userA1']);
        $userA2 = User::factory()->create(['branch_id' => $branchA->id, 'username' => 'userA2']);
        $userB1 = User::factory()->create(['branch_id' => $branchB->id, 'username' => 'userB1']);

        $userA1->assignRole('admin_branch'); // Assume this role exists or use generic

        $response = $this->actingAs($userA1)->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonFragment(['username' => 'userA1']);
        $response->assertJsonFragment(['username' => 'userA2']);
        $response->assertJsonMissing(['username' => 'userB1']);
    }

    public function test_warehouse_user_can_only_see_users_in_same_warehouse()
    {
        $warehouseA = Warehouse::factory()->create(['name' => 'Warehouse A']);
        $warehouseB = Warehouse::factory()->create(['name' => 'Warehouse B']);

        $userA1 = User::factory()->create(['warehouse_id' => $warehouseA->id, 'username' => 'whUserA1']);
        $userB1 = User::factory()->create(['warehouse_id' => $warehouseB->id, 'username' => 'whUserB1']);

        $userA1->assignRole('warehouse_staff'); // Assume generic role

        $response = $this->actingAs($userA1)->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonFragment(['username' => 'whUserA1']);
        $response->assertJsonMissing(['username' => 'whUserB1']);
    }

    public function test_super_admin_can_see_all_users()
    {
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $userA1 = User::factory()->create(['branch_id' => $branchA->id, 'username' => 'userA1']);

        $superAdmin = User::factory()->create(['username' => 'superadmin']);
        $superAdmin->assignRole('super_admin');

        $response = $this->actingAs($superAdmin)->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonFragment(['username' => 'userA1']);
    }
}
