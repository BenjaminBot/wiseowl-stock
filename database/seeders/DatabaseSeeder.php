<?php

namespace Database\Seeders;

use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SalesChannel::firstOrCreate(['type' => 'unallocated', 'is_system' => true], ['name' => 'Unallocated']);
    }
}
