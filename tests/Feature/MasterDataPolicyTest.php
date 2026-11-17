<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Material;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

// Hapus baris ini bila Pest.php sudah menerapkan RefreshDatabase untuk folder Feature
uses(RefreshDatabase::class);

dataset('master_models', [
    'Material' => [Material::class],
    'Vendor' => [Vendor::class],
    'Department' => [Department::class],
    'StorageLocation' => [StorageLocation::class],
]);

it('semua peran boleh melihat master data', function (string $model) {
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);

        expect(Gate::forUser($user)->allows('viewAny', $model))->toBeTrue();
    }
})->with('master_models');

it('hanya admin yang boleh menambah, mengubah, dan menghapus master data', function (string $model) {
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);
        $gate = Gate::forUser($user);
        $expected = $role === UserRole::Admin;

        expect($gate->allows('create', $model))->toBe($expected, "create oleh {$role->value}")
            ->and($gate->allows('update', new $model))->toBe($expected, "update oleh {$role->value}")
            ->and($gate->allows('delete', new $model))->toBe($expected, "delete oleh {$role->value}")
            ->and($gate->allows('deleteAny', $model))->toBe($expected, "deleteAny oleh {$role->value}");
    }
})->with('master_models');
