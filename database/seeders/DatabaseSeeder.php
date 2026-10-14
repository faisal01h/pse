<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Profiles and records are submitted with a public source and stay unpublished
     * until that source is reviewed. This seeder does not invent remarks or cases.
     *
     * @return void
     */
    public function run()
    {
        $this->call(AuthorizationSeeder::class);
    }
}
