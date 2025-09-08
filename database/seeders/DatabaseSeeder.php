<?php

namespace Database\Seeders;

use CreateUsersSeeder;
use Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::create([
            'name' => 'Noor', 
            'email' => 'noor@invoicing.com', 
            'is_admin' => '0', 
            'password' => Hash::make('Noor@123'), 
        ]);
    }
}
