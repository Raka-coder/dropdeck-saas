<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email = 'google@example.com', string $name = 'Google User'): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn(
        (new SocialiteUser)->map(['id' => '123456', 'name' => $name, 'email' => $email, 'avatar' => null]),
    );

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('creates and authenticates a user from the Google callback', function () {
    fakeGoogleUser();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'google@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Google User')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('reuses the existing account for a known Google email', function () {
    $existing = User::factory()->create(['email' => 'google@example.com']);

    fakeGoogleUser();

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    expect(User::query()->where('email', 'google@example.com')->count())->toBe(1)
        ->and(auth()->id())->toBe($existing->id);
});
