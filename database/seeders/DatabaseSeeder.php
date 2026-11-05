<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BambuLabCatalogSeeder::class);

        // Demo user for local development only; sign-in itself goes through SSO.
        if (app()->environment('local', 'testing')) {
            User::firstOrCreate(['email' => 'test@example.com'], ['name' => 'Test User']);
        }
    }
}
