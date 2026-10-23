<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings;
use App\Models\User;
use App\Support\Theme;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Website colours chosen under Store settings reach the shop and the admin. */
class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
    }

    public function test_shop_gets_the_default_colours(): void
    {
        $this->getJson('/api/store')
            ->assertOk()
            ->assertJsonPath('theme.primary', '#e8650c')
            ->assertJsonPath('theme.dark', '#0b1120')
            ->assertJsonPath('theme.sale', '#e11d48');
    }

    public function test_admin_can_pick_a_ready_made_set_and_save_it(): void
    {
        Livewire::test(StoreSettings::class)
            ->fillForm(['theme_preset' => 'green'])
            ->assertFormSet(['theme_primary' => '#15803d', 'theme_dark' => '#0f2a1d'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/store')->assertJsonPath('theme.primary', '#15803d');
        $this->get('/admin')->assertOk();
    }

    public function test_colours_too_light_for_white_text_are_refused(): void
    {
        Livewire::test(StoreSettings::class)
            ->fillForm(['theme_primary' => '#ffeb3b'])
            ->call('save')
            ->assertHasFormErrors(['theme_primary']);

        $this->assertSame('#e8650c', Theme::colours()['theme_primary']);
    }

    public function test_broken_saved_values_fall_back_to_the_defaults(): void
    {
        \App\Models\Setting::updateOrCreate(['key' => 'theme_primary'], ['value' => 'red;}body{display:none']);

        $this->getJson('/api/store')->assertJsonPath('theme.primary', '#e8650c');
    }
}
