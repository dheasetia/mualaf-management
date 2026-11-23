<?php

declare(strict_types=1);

use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM', 'name_ar' => 'مركز الدمام', 'name_en' => 'Dammam Center']);
});

it('shows the login page in Arabic until a guest chooses English for the session', function (): void {
    $this->get('/center/login')->assertOk()->assertSee('dir="rtl"', false);

    $this->from('/center/login')->post('/locale/en')->assertRedirect('/center/login');

    $this->get('/center/login')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
});

it('saves the choice on the account and applies it on every visit', function (): void {
    $dai = grantRole(memberOf($this->dammam), 'dai', $this->dammam);

    $this->actingAs($dai)->from('/center/DMM')->post('/locale/en')->assertRedirect('/center/DMM');

    expect($dai->refresh()->locale)->toBe('en');

    // A fresh session (another device) still follows the account.
    $this->flushSession();
    $this->actingAs($dai)->get('/center/DMM')->assertOk()->assertSee('lang="en"', false)->assertSee('Dammam Center')->assertSee('العربية');
});

it('offers only Arabic and English', function (): void {
    $this->post('/locale/fr')->assertNotFound();

    $user = User::factory()->create();
    $this->actingAs($user)->post('/locale/fr')->assertNotFound();
    expect($user->refresh()->locale)->toBe('ar');
});

it('names records in the active language, falling back to Arabic where English is optional', function (): void {
    $countries = [
        Country::factory()->create(['name_ar' => 'الفلبين', 'name_en' => 'Philippines']),
        Country::factory()->create(['name_ar' => 'الهند', 'name_en' => 'India']),
    ];
    $withoutEnglish = new class extends Model
    {
        protected $attributes = ['name_ar' => 'شركة', 'name_en' => null];
    };

    app()->setLocale('en');
    expect(LocalizedName::options(Country::query()->whereKey([$countries[0]->id, $countries[1]->id])))
        ->toBe([$countries[1]->id => 'India', $countries[0]->id => 'Philippines'])
        ->and(LocalizedName::of($withoutEnglish))->toBe('شركة');

    app()->setLocale('ar');
    expect(LocalizedName::of($countries[0]))->toBe('الفلبين');
});
