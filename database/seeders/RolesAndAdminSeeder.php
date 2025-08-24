<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создание ролей
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        // rename 'client' to 'manager' if exists
        $clientRole = Role::where('name', 'client')->first();
        if ($clientRole) {
            $clientRole->name = 'manager';
            $clientRole->save();
        }
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $operatorRole = Role::firstOrCreate(['name' => 'operator']);

        // Создание клиента
        $client = Client::firstOrCreate(
            ['name' => 'Admin Company'],
            [
                'company' => 'PayEasy Corp',
                'phone' => '+1234567890',
            ]
        );

        // Создание пользователя и привязка к client_id
        $admin = User::firstOrCreate(
            ['email' => 'admin@payeasy.pro'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'client_id' => $client->id,
            ]
        );

        // Присвоить роль
        $admin->assignRole($adminRole);

        $this->command->info('Admin user and roles created. Client linked.');
    }
}
