<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'PStore Jakarta Pusat',
                'code' => 'PST-JKT01',
                'address' => 'Jl. Condet Raya No. 1',
                'timezone' => 'WIB'
            ],
            [
                'name' => 'PStore Batam',
                'code' => 'PST-BTM01',
                'address' => 'Jl. Nagoya No. 12',
                'timezone' => 'WIB'
            ],
            [
                'name' => 'PStore Bali',
                'code' => 'PST-BALI01',
                'address' => 'Jl. Teuku Umar No. 9',
                'timezone' => 'WITA'
            ],
            [
                'name' => 'PStore Jayapura',
                'code' => 'PST-WIT01',
                'address' => 'Jl. Ahmad Yani No. 5',
                'timezone' => 'WIT'
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }
    }
}