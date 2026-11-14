<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\StorageLocation;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['code' => 'GD-01', 'name' => 'Gudang Utama'],
            ['code' => 'GD-02', 'name' => 'Gudang Suku Cadang'],
        ] as $row) {
            StorageLocation::updateOrCreate(['code' => $row['code']], $row);
        }

        foreach ([
            ['code' => 'VND-001', 'name' => 'PT Sumber Logam Contoh',  'address' => 'Jl. Contoh No. 1, Cilegon',  'phone' => '0254-000001', 'email' => 'sales@sumber-logam.example',  'is_active' => true],
            ['code' => 'VND-002', 'name' => 'CV Mitra Teknik Contoh',  'address' => 'Jl. Contoh No. 2, Serang',   'phone' => '0254-000002', 'email' => 'order@mitra-teknik.example',  'is_active' => true],
            ['code' => 'VND-003', 'name' => 'PT Kimia Industri Contoh', 'address' => 'Jl. Contoh No. 3, Tangerang', 'phone' => '021-000003',   'email' => 'info@kimia-industri.example', 'is_active' => true],
        ] as $row) {
            Vendor::updateOrCreate(['code' => $row['code']], $row);
        }

        
        foreach ([
            ['code' => 'MAT-001', 'name' => 'Baut M12 x 50',          'base_uom' => 'PCS',   'qty_decimals' => 0, 'min_stock' => 200],
            ['code' => 'MAT-002', 'name' => 'Mur M12',                'base_uom' => 'PCS',   'qty_decimals' => 0, 'min_stock' => 200],
            ['code' => 'MAT-003', 'name' => 'Bearing 6205',           'base_uom' => 'PCS',   'qty_decimals' => 0, 'min_stock' => 20],
            ['code' => 'MAT-004', 'name' => 'Filter Oli Hidrolik',    'base_uom' => 'PCS',   'qty_decimals' => 0, 'min_stock' => 10],
            ['code' => 'MAT-005', 'name' => 'Seal Kit Silinder',      'base_uom' => 'SET',   'qty_decimals' => 0, 'min_stock' => 5],
            ['code' => 'MAT-006', 'name' => 'Kabel NYY 4x16',         'base_uom' => 'METER', 'qty_decimals' => 2, 'min_stock' => 100],
            ['code' => 'MAT-007', 'name' => 'Selang Hidrolik 1/2"',   'base_uom' => 'METER', 'qty_decimals' => 2, 'min_stock' => 50],
            ['code' => 'MAT-008', 'name' => 'Plat Baja 5 mm',         'base_uom' => 'KG',    'qty_decimals' => 3, 'min_stock' => 500],
            ['code' => 'MAT-009', 'name' => 'Kawat Las E6013',        'base_uom' => 'KG',    'qty_decimals' => 3, 'min_stock' => 50],
            ['code' => 'MAT-010', 'name' => 'Oli Pelumas ISO VG 46',  'base_uom' => 'LITER', 'qty_decimals' => 3, 'min_stock' => 100],
            ['code' => 'MAT-011', 'name' => 'Cat Anti Karat',         'base_uom' => 'LITER', 'qty_decimals' => 3, 'min_stock' => 40],
            ['code' => 'MAT-012', 'name' => 'Thinner',                'base_uom' => 'LITER', 'qty_decimals' => 3, 'min_stock' => 40],
        ] as $row) {
            Material::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }
    }
}
