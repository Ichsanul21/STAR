<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dev@star.test'],
            [
                'name' => 'Developer',
                'role' => 'dev',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        foreach (array_keys(config('star_options.vehicle_models', [])) as $index => $value) {
            Lookup::updateOrCreate(
                ['group' => 'vehicle_models', 'value' => $value],
                ['label' => config("star_options.vehicle_models.{$value}"), 'sort_order' => $index, 'is_active' => true]
            );
        }
    }
}
