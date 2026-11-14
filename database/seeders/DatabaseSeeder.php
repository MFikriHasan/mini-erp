<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::unguarded(function () {
            $this->call([
                DepartmentSeeder::class,
                UserSeeder::class,
                MasterDataSeeder::class,
            ]);
        });
    }
}
