<?php

declare(strict_types=1);

use App\Models\User;

it('persists a valid telegram username', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telegram_username' => '@ana_lopez',
        ])
        ->assertRedirect();

    expect($user->fresh()->telegram_username)->toBe('@ana_lopez');
});

it('accepts an empty telegram username (clears it)', function () {
    $user = User::factory()->create(['telegram_username' => 'ana']);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telegram_username' => '',
        ])
        ->assertRedirect();

    expect($user->fresh()->telegram_username)->toBeNull();
});

it('rejects a telegram username with invalid characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telegram_username' => 'bad handle!',
        ])
        ->assertSessionHasErrors('telegram_username');
});

it('rejects a telegram username that is too short', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'telegram_username' => '@abc',
        ])
        ->assertSessionHasErrors('telegram_username');
});
