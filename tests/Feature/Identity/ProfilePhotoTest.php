<?php

declare(strict_types=1);

use App\Domain\Identity\Services\ProfilePhotos;
use App\Filament\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(ProfilePhotos::DISK);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('central'));
});

it('uploads a profile photo as a square JPEG and shows it as the avatar', function (): void {
    $component = Livewire::test(EditProfile::class)
        ->fillForm(['photo' => UploadedFile::fake()->image('me.png', 600, 400)])
        ->call('save')
        ->assertHasNoFormErrors();

    // The page reloads so the avatar in the top bar shows the new photo.
    expect(json_encode($component->effects))->toContain('window.location.reload()');
    Livewire::test(EditProfile::class)->fillForm(['name' => 'Same photo'])->call('save')
        ->tap(fn ($unchanged) => expect(json_encode($unchanged->effects))->not->toContain('window.location.reload()'));

    $path = $this->user->refresh()->avatar_path;
    $size = getimagesizefromstring((string) Storage::disk(ProfilePhotos::DISK)->get((string) $path));

    expect($path)->toStartWith('profile-photos/'.$this->user->uuid)
        ->and($size)->toMatchArray([0 => 256, 1 => 256, 'mime' => 'image/jpeg'])
        ->and($this->user->getFilamentAvatarUrl())->toContain('/profile-photos/'.$this->user->uuid);
});

it('drops metadata such as EXIF and GPS from the photo', function (): void {
    $upload = UploadedFile::fake()->createWithContent('me.jpg', jpegWithExif('SECRET-GPS-MARKER'));

    app(ProfilePhotos::class)->replace($this->user, (string) $upload->getRealPath());

    expect(Storage::disk(ProfilePhotos::DISK)->get((string) $this->user->avatar_path))->not->toContain('SECRET-GPS-MARKER');
});

it('refuses files that are not images', function (): void {
    $upload = UploadedFile::fake()->createWithContent('me.jpg', '%PDF-1.4 not an image');

    expectRuleViolation(fn () => app(ProfilePhotos::class)->replace($this->user, (string) $upload->getRealPath()), 'photo');
    expect($this->user->refresh()->avatar_path)->toBeNull();
});

it('replaces and removes the photo without leaving old files behind', function (): void {
    $photos = app(ProfilePhotos::class);
    $first = UploadedFile::fake()->image('a.jpg', 300, 300);
    $second = UploadedFile::fake()->image('b.jpg', 300, 300);
    $photos->replace($this->user, (string) $first->getRealPath());
    $firstPath = $this->user->avatar_path;
    $photos->replace($this->user, (string) $second->getRealPath());

    Storage::disk(ProfilePhotos::DISK)->assertMissing((string) $firstPath);

    Livewire::test(EditProfile::class)->fillForm(['remove_photo' => true])->call('save')->assertHasNoFormErrors();

    expect($this->user->refresh()->avatar_path)->toBeNull()
        ->and(Storage::disk(ProfilePhotos::DISK)->allFiles('profile-photos'))->toBe([])
        ->and($this->user->getFilamentAvatarUrl())->toBeNull();
});

it('serves photos to signed-in users only', function (): void {
    $upload = UploadedFile::fake()->image('a.jpg', 300, 300);
    app(ProfilePhotos::class)->replace($this->user, (string) $upload->getRealPath());
    $url = '/profile-photos/'.$this->user->uuid;

    $this->actingAs(User::factory()->create())->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'max-age=86400, private');

    $this->get('/profile-photos/'.User::factory()->create()->uuid)->assertNotFound();

    auth()->logout();
    $this->get($url)->assertNotFound();
});
