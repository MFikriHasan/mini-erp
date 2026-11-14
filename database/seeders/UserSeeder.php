<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dept = fn (string $code) => Department::where('code', $code)->value('id');

        $users = [
            [
                'name' => 'Admin Demo',
                'email' => 'admin@mini-erp.test',
                'role' => UserRole::Admin,
                'department_id' => $dept('ITD')
            ],

            [
                'name' => 'Purchasing Demo',
                'email' => 'purchasing@mini-erp.test',
                'role' => UserRole::Purchasing,
                'department_id' => $dept('PRC')
            ],

            [
                'name' => 'Gudang Demo',
                'email' => 'warehouse@mini-erp.test',
                'role' => UserRole::Warehouse,
                'department_id' => $dept('WHS')
            ],

            [
                'name' => 'Manager Demo',
                'email' => 'manager@mini-erp.test',
                'role' => UserRole::Manager,
                'department_id' => $dept('PRD')
            ],

            [
                'name' => 'Staff Demo',
                'email' => 'staff@mini-erp.test',
                'role' => UserRole::Staff,
                'department_id' => $dept('PRD')
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->forceFill($data + ['password' => 'password'])->save();
        }
    }
}
