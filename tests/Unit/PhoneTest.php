<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_normalises_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [
            'local with zero' => ['0551234567', '+966551234567'],
            'local without zero' => ['551234567', '+966551234567'],
            'with country code' => ['966551234567', '+966551234567'],
            'with plus' => ['+966 55 123 4567', '+966551234567'],
            'double zero prefix' => ['00966551234567', '+966551234567'],
            'dashes and spaces' => ['055-123-4567', '+966551234567'],
            'arabic-indic digits' => ['٠٥٥١٢٣٤٥٦٧', '+966551234567'],
            'international number' => ['+971501234567', '+971501234567'],
            'the support whatsapp number' => ['00966551475212', '+966551475212'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_numbers(?string $input): void
    {
        $this->assertNull(Phone::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'letters only' => ['abc'],
            'too short' => ['055123'],
            'saudi landline is not a mobile' => ['+966112345678'],
            'local number that is not a mobile' => ['0112345678'],
        ];
    }

    public function test_masks_the_middle_of_a_number(): void
    {
        $this->assertSame('+9665••••••67', Phone::mask('+966551234567'));
        $this->assertSame('', Phone::mask(null));
    }
}
