<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'page-'.fake()->unique()->numerify('####'),
            'group' => 'policy',
            'title_ar' => 'صفحة تجريبية',
            'title_en' => 'Sample page',
            'body_ar' => "## عنوان\n\nنص تجريبي.",
            'body_en' => "## Heading\n\nSample text.",
            'is_published' => true,
            'sort_order' => 0,
        ];
    }
}
