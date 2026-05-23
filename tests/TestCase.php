<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the testing helper traits.
     * Otomatis migrate database HRIS testing sebelum RefreshDatabase berjalan.
     */
    protected function setUpTraits(): array
    {
        $uses = parent::setUpTraits();

        // Jika test menggunakan RefreshDatabase, pastikan tabel users
        // di koneksi mysql_hris juga di-migrate
        if (isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class])) {
            $this->migrateHrisDatabase();
        }

        return $uses;
    }

    /**
     * Migrate tabel users di database HRIS testing.
     */
    protected function migrateHrisDatabase(): void
    {
        Artisan::call('migrate', [
            '--database' => 'mysql_hris',
            '--path' => 'database/migrations/testing',
            '--realpath' => false,
        ]);
    }
}
