<?php

namespace Database\Seeders;

use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Customer', 'slug' => 'customer', 'sort_order' => 1],
            ['name' => 'Electrician', 'slug' => 'electrician', 'sort_order' => 2],
            ['name' => 'Plumber', 'slug' => 'plumber', 'sort_order' => 3],
            ['name' => 'Delivery Agent', 'slug' => 'delivery-agent', 'sort_order' => 4],
        ];

        foreach ($types as $type) {
            UserType::updateOrCreate(
                ['slug' => $type['slug']],
                array_merge($type, ['is_active' => true])
            );
        }
    }
}
