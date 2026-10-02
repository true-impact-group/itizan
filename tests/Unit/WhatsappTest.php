<?php

namespace Tests\Unit;

use App\Support\Whatsapp;
use Tests\TestCase;

class WhatsappTest extends TestCase
{
    public function test_link_opens_the_support_number_with_the_prefilled_message(): void
    {
        $url = Whatsapp::url();

        $this->assertStringStartsWith('https://wa.me/966551475212?text=', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('السلام عليكم، أنا عميل برنامج اتزان، وأحتاج مساعدة بخصوص: ', $query['text']);
    }

    public function test_number_is_normalised_from_the_international_00_format(): void
    {
        config(['itizan.whatsapp.number' => '00966551475212']);

        $this->assertStringStartsWith('https://wa.me/966551475212?', Whatsapp::url());
    }

    public function test_a_custom_message_is_encoded(): void
    {
        $this->assertStringContainsString('text='.rawurlencode('مرحبا & أهلا'), Whatsapp::url('مرحبا & أهلا'));
    }
}
