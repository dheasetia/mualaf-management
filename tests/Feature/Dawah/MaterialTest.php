<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Actions\DistributeMaterial;
use App\Domain\Dawah\Actions\ManageMaterials;
use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Dawah\Models\DawahMaterialDistribution;
use App\Domain\Platform\Models\Language;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\DawahMaterials\Pages\ManageDawahMaterials as CentralMaterials;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Storage::fake('local');
    $this->tenant = Tenant::factory()->active()->create();
    $this->language = Language::factory()->create();
    $this->daiUser = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->dai = inTenant($this->tenant, fn () => Dai::factory()->create(['user_id' => $this->daiUser->id]));
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->materials = fn (): ManageMaterials => app(ManageMaterials::class);
    $this->draft = fn (array $overrides = []) => ($this->in)(fn () => ($this->materials)()->save(null, [
        'title' => 'What is Islam?', 'material_type' => 'leaflet', 'language_id' => $this->language->id,
        'external_url' => 'https://example.org/leaflet', 'stock_quantity' => 50, ...$overrides,
    ], $this->daiUser));
    $this->approved = function (array $overrides = []): DawahMaterial {
        $material = ($this->draft)($overrides);

        return ($this->in)(function () use ($material): DawahMaterial {
            ($this->materials)()->submitForReview($material, $this->daiUser);

            return ($this->materials)()->approve($material, $this->head);
        });
    };
});

it('needs a file or a link before review and a reviewer other than the author', function (): void {
    $material = ($this->draft)(['external_url' => null]);
    ($this->in)(fn () => expectRuleViolation(fn () => ($this->materials)()->submitForReview($material, $this->daiUser), 'external_url'));

    $mine = ($this->in)(fn () => ($this->materials)()->save(null, ['title' => 'x', 'material_type' => 'book', 'language_id' => $this->language->id, 'external_url' => 'https://example.org'], $this->head));
    ($this->in)(function () use ($mine): void {
        ($this->materials)()->submitForReview($mine, $this->head);
        expectRuleViolation(fn () => ($this->materials)()->approve($mine, $this->head), 'status');
        expect(fn () => ($this->materials)()->approve($mine, $this->daiUser))->toThrow(AuthorizationException::class);
    });
});

it('stores an attached file through the document checks', function (): void {
    $material = ($this->draft)();
    $path = tempnam(sys_get_temp_dir(), 'mat');
    file_put_contents($path, "%PDF-1.4\n%leaflet\n");

    $material = ($this->in)(fn () => ($this->materials)()->attachFile($material, $path, 'leaflet.pdf', $this->daiUser));

    expect($material->file_document_id)->not->toBeNull();
    expect(Storage::disk('local')->allFiles("tenants/{$this->tenant->uuid}/dawah/materials/{$material->uuid}"))->toHaveCount(1);
});

it('retires the previous version when a new one is approved and freezes approved materials', function (): void {
    $first = ($this->approved)();

    ($this->in)(function () use ($first): void {
        expectRuleViolation(fn () => ($this->materials)()->save($first, ['title' => 'x', 'material_type' => 'leaflet', 'language_id' => $this->language->id], $this->daiUser), 'status');

        $second = ($this->materials)()->newVersion($first, '2', $this->daiUser);
        ($this->materials)()->submitForReview($second, $this->daiUser);
        ($this->materials)()->approve($second, $this->head);

        expect($first->refresh()->status)->toBe(MaterialStatus::Retired)
            ->and($second->refresh())->status->toBe(MaterialStatus::Approved)->parent_material_id->toBe($first->id)->version->toBe('2');
    });
});

it('distributes only approved material, decrements stock and never below zero', function (): void {
    $draft = ($this->draft)();
    $approved = ($this->approved)();

    ($this->in)(function () use ($draft, $approved): void {
        $distribute = app(DistributeMaterial::class);
        expectRuleViolation(fn () => $distribute->handle($draft, ['quantity' => 1], $this->daiUser), 'material_id');

        $distribute->handle($approved, ['quantity' => 45], $this->daiUser);
        expectRuleViolation(fn () => $distribute->handle($approved, ['quantity' => 6], $this->daiUser), 'quantity');
        expect($approved->refresh()->stock_quantity)->toBe(5);

        expectRuleViolation(fn () => $distribute->handle($approved, ['convert_id' => Convert::factory()->create()->id], $this->daiUser), 'related_convert_id');
        expect(fn () => $distribute->handle($approved, [], $this->head))->toThrow(AuthorizationException::class);
    });

    expect(($this->in)(fn () => DawahMaterialDistribution::query()->sole()))->quantity->toBe(45)->dai_id->toBe($this->dai->id);
});

it('shows national materials to every office and office materials only to their own', function (): void {
    $national = DawahMaterial::factory()->create(['title' => 'National']);
    $own = ($this->draft)(['title' => 'Ours']);
    $other = Tenant::factory()->active()->create();

    expect(($this->in)(fn () => DawahMaterial::query()->pluck('title')->sort()->values()->all()))->toBe(['National', 'Ours'])
        ->and(inTenant($other, fn () => DawahMaterial::query()->pluck('title')->all()))->toBe(['National']);

    ($this->in)(function () use ($national): void {
        expect(fn () => ($this->materials)()->retire($national, $this->head))->toThrow(AuthorizationException::class);
        app(DistributeMaterial::class)->handle($national, ['quantity' => 3], $this->daiUser);
        expect($national->refresh()->stock_quantity)->toBeNull();
    });
});

it('lets Central draft, review and approve national materials that offices read but do not change', function (): void {
    $author = grantRole(User::factory()->create(), 'central_admin');
    $reviewer = grantRole(User::factory()->create(), 'super_admin');
    app(TenantContext::class)->clear();

    $material = ($this->materials)()->save(null, [
        'title' => 'Prayer guide', 'material_type' => 'leaflet', 'language_id' => $this->language->id,
        'external_url' => 'https://example.org/prayer', 'stock_quantity' => 40,
    ], $author);
    expect($material)->tenant_id->toBeNull()->stock_quantity->toBeNull();

    ($this->materials)()->submitForReview($material, $author);
    expect(fn () => ($this->materials)()->approve($material, $author))->toThrow(RuleViolation::class);
    ($this->materials)()->approve($material, $reviewer);
    expect(fn () => ($this->materials)()->addStock($material->refresh(), 10, $author))->toThrow(RuleViolation::class);

    ($this->in)(function () use ($material): void {
        expect(DawahMaterial::query()->whereKey($material->id)->sole()->status)->toBe(MaterialStatus::Approved)
            ->and(fn () => ($this->materials)()->newVersion($material, '2', $this->head))->toThrow(AuthorizationException::class);
    });

    // An office material stays out of Central's reach.
    $office = ($this->draft)();
    app(TenantContext::class)->clear();
    expect(DawahMaterial::query()->whereKey($office->id)->exists())->toBeFalse()
        ->and(fn () => ($this->materials)()->submitForReview($office, $author))->toThrow(AuthorizationException::class);
});

it('manages national materials from the Central panel', function (): void {
    $admin = grantRole(User::factory()->create(), 'central_admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::test(CentralMaterials::class)
        ->callAction('create', ['title' => 'Prayer guide', 'material_type' => 'leaflet', 'language_id' => $this->language->id, 'external_url' => 'https://example.org/prayer'])
        ->assertHasNoActionErrors();

    expect(DawahMaterial::query()->sole())->tenant_id->toBeNull()->title->toBe('Prayer guide');
});
