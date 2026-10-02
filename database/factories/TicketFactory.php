<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TicketType::Support,
            'subject' => 'استفسار عن الاشتراك',
            'body' => 'أحتاج مساعدة بخصوص اشتراكي.',
            'status' => TicketStatus::Open,
        ];
    }
}
