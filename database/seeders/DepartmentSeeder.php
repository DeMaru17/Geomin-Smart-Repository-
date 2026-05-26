<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => '101', 'name' => 'PREPARASI'],
            ['code' => '102', 'name' => 'ANALISIS'],
            ['code' => '103', 'name' => 'PENGENDALIAN REKAMAN'],
            ['code' => '104', 'name' => 'AUDIT INTERNAL'],
            ['code' => '105', 'name' => 'KAJI ULANG MANAJEMEN'],
            ['code' => '106', 'name' => 'PENGENDALIAN DOKUMEN'],
            ['code' => '107', 'name' => 'KAJI ULANG PERMINTAAN TENDER, SUBKONTRAK PENGUJIAN, KALIBRASI'],
            ['code' => '108', 'name' => 'REKRUTMEN DAN PELATIHAN'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name']],
            );
        }
    }
}
