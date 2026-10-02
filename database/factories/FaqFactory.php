<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_ar' => 'هل الاستشارة الأولى مجانية؟',
            'question_en' => 'Is the first consultation free?',
            'answer_ar' => 'نعم، استشارة مجانية واحدة للعملاء الجدد.',
            'answer_en' => 'Yes, one free consultation for new clients.',
            'sort_order' => 0,
            'is_published' => true,
        ];
    }
}
