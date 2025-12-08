<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use function Symfony\Component\Clock\now;

class GlobalCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $globalCategorySeeder = [
            'Food',
            'Transport',
            'Entertainment',
            'Health',
            'Education',
            'Shopping'
        ];

        foreach ($globalCategorySeeder as $item) {
            Category::updateOrCreate(
                [
                    'name' => $item,
                    'is_global' => true,
                ],
                [
                    'uuid' => Str::uuid(),
                    'user_id' => null,
                    'client_updated_at' => now(),
                ]
            );
        }
    }
}
