<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['code' => 'PRD', 'name' => 'Produksi'],
            ['code' => 'MNT', 'name' => 'Pemeliharaan'],
            ['code' => 'ITD', 'name' => 'Teknologi Informasi'],
            ['code' => 'PRC', 'name' => 'Pengadaan'],
            ['code' => 'WHS', 'name' => 'Gudang'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['code' => $department['code']], $department);
        }
    }
}
