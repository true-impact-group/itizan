<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Support\Navigation;
use FilesystemIterator;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_arabic_is_the_default_language_and_renders_right_to_left(): void
    {
        $this->get(route('home'))->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
    }

    public function test_visitor_can_switch_to_english_which_renders_left_to_right(): void
    {
        $this->seedCatalog();

        $this->get(route('locale', 'en'))->assertRedirect();

        $this->get(route('home'))
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="en"', false)
            ->assertSee('Your journey to a healthy weight… starts with Itizan')
            ->assertSee('Start with a free consultation')
            ->assertDontSee('رحلتك لوزن صحي');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->get(route('locale', 'fr'))->assertNotFound();
    }

    public function test_switching_language_while_signed_in_is_remembered_on_the_account(): void
    {
        $client = $this->client(['locale' => 'ar']);

        $this->actingAs($client)->get(route('locale', 'en'));

        $this->assertSame('en', $client->fresh()->locale);
    }

    public function test_signed_in_users_get_their_preferred_language_on_a_fresh_session(): void
    {
        $this->actingAs($this->client(['locale' => 'en']))
            ->get(route('account.dashboard'))
            ->assertSee('lang="en"', false);
    }

    public function test_money_uses_the_currency_name_of_the_active_language(): void
    {
        $this->assertSame('999 ريال', money(99900));

        app()->setLocale('en');

        $this->assertSame('999 SAR', money(99900));
    }

    public function test_english_content_is_served_from_the_database_translations(): void
    {
        $this->seedCatalog();
        $this->get(route('locale', 'en'));

        $this->get(route('pricing'))->assertSee('Six-month package')->assertSee('Annual package');
        $this->get(route('policies.show', 'privacy'))->assertSee('Privacy policy');
    }

    public function test_every_arabic_string_in_the_code_and_views_has_an_english_translation(): void
    {
        $translations = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach ($this->arabicStrings() as $string => $file) {
            if (! array_key_exists($string, $translations)) {
                $missing[] = "{$file}: {$string}";
            }
        }

        $this->assertSame([], $missing, "Add these to lang/en.json:\n".implode("\n", $missing));
    }

    public function test_english_translations_keep_the_same_placeholders_as_the_arabic_source(): void
    {
        $translations = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $broken = [];

        foreach ($translations as $arabic => $english) {
            preg_match_all('/:[a-z_]+/i', $arabic, $source);
            preg_match_all('/:[a-z_]+/i', $english, $target);
            sort($source[0]);
            sort($target[0]);

            if ($source[0] !== $target[0]) {
                $broken[] = $arabic;
            }
        }

        $this->assertSame([], $broken);
    }

    public function test_enum_labels_translate_to_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('Confirmed', AppointmentStatus::Confirmed->label());
        $this->assertSame('On its way to you', OrderStatus::OutForDelivery->label());
        $this->assertSame('Doctor / consultant', Role::Doctor->label());
    }

    public function test_every_navigation_label_is_translated(): void
    {
        $translations = json_decode(file_get_contents(lang_path('en.json')), true);

        foreach (Role::cases() as $role) {
            foreach (Navigation::for($role) as $item) {
                $this->assertArrayHasKey($item['label'], $translations, "Missing translation for nav label [{$item['label']}]");
            }
        }
    }

    /**
     * Every quoted Arabic literal in app/ and in compiled Blade views (the same thing the translator is asked for).
     * Only the language-switcher label is exempt: it must stay in its own language.
     *
     * @return array<string, string> string => file
     */
    private function arabicStrings(): array
    {
        $found = [];
        $exempt = ['العربية'];

        $scan = function (string $php, string $file) use (&$found, $exempt) {
            foreach (token_get_all($php) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = eval('return '.$token[1].';');
                    // Only strings with Arabic *letters* need translating (not bare punctuation such as "،").
                    if (is_string($value) && preg_match('/(?=\p{L})\p{Arabic}/u', $value) && ! in_array($value, $exempt, true)
                        && ! preg_match('/^[٠-٩]$/u', $value)) {
                        $found[$value] ??= $file;
                    }
                }
            }
        };

        foreach ([[app_path(), '.php', false], [resource_path('views'), '.blade.php', true]] as [$dir, $suffix, $blade]) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (str_ends_with($file->getFilename(), $suffix)) {
                    $source = file_get_contents($file->getPathname());
                    $scan($blade ? Blade::compileString($source) : $source, $file->getPathname());
                }
            }
        }

        foreach (config('itizan.safety_questions') as $question) {
            $found[$question['text']] = 'config/itizan.php';
        }

        return $found;
    }
}
