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
            'name' => 'Al Abid', 
            'email' => 'alabid@invoicing.com', 
            'is_admin' => '0', 
            'password' => Hash::make('Alabid@123'), 
        ]);
    }
}
