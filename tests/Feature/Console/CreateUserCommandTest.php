<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;

it('creates a user attached to the chosen workspace', function () {
    $workspace = Workspace::factory()->create(['name' => 'Acme']);

    $this->artisan('user:create')
        ->expectsQuestion('Nombre', 'Ana Ruiz')
        ->expectsQuestion('Email', 'ana@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirma la contraseña', 'correct-horse-battery')
        ->expectsQuestion('Workspace', (string) $workspace->id)
        ->expectsQuestion('Rol', 'admin')
        ->expectsConfirmation('¿Crear el usuario?', 'yes')
        ->assertSuccessful();

    $user = User::query()->where('email', 'ana@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ana Ruiz')
        ->and($user->email_verified_at)->not->toBeNull();

    $membership = WorkspaceMember::query()
        ->where('workspace_id', $workspace->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role)->toBe('admin');
});

it('creates a user that can actually log in', function () {
    Workspace::factory()->create();

    $this->artisan('user:create')
        ->expectsQuestion('Nombre', 'Bruno Diaz')
        ->expectsQuestion('Email', 'bruno@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirma la contraseña', 'correct-horse-battery')
        ->expectsQuestion('Workspace', '__none__')
        ->expectsConfirmation('¿Crear el usuario?', 'yes')
        ->assertSuccessful();

    // The assertion that matters: proves the password was hashed exactly once.
    expect(Auth::attempt([
        'email' => 'bruno@example.com',
        'password' => 'correct-horse-battery',
    ]))->toBeTrue();
});

it('skips the workspace step when no workspace exists', function () {
    expect(Workspace::query()->count())->toBe(0);

    $this->artisan('user:create')
        ->expectsQuestion('Nombre', 'Carla Gil')
        ->expectsQuestion('Email', 'carla@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirma la contraseña', 'correct-horse-battery')
        ->expectsConfirmation('¿Crear el usuario?', 'yes')
        ->assertSuccessful();

    expect(User::query()->where('email', 'carla@example.com')->exists())->toBeTrue();
});

/*
 * On invalid input the command re-prompts in a real terminal, but under tests
 * ConfiguresPrompts::promptUntilValid() throws PromptValidationException instead
 * of looping (see the runningUnitTests() branch). So these assert what is
 * observable here: the rule fired, the command aborted, nothing was written.
 */

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:create')
        ->expectsQuestion('Nombre', 'Dora Paz')
        ->expectsQuestion('Email', 'taken@example.com')
        ->assertFailed();

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

it('rejects a password that fails the application rules', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Nombre', 'Eva Sol')
        ->expectsQuestion('Email', 'eva@example.com')
        ->expectsQuestion('Contraseña', 'short')
        ->assertFailed();

    expect(User::query()->where('email', 'eva@example.com')->exists())->toBeFalse();
});

it('creates nothing when the confirmation is declined', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Nombre', 'Fran Mora')
        ->expectsQuestion('Email', 'fran@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirma la contraseña', 'correct-horse-battery')
        ->expectsConfirmation('¿Crear el usuario?', 'no')
        ->assertFailed();

    expect(User::query()->where('email', 'fran@example.com')->exists())->toBeFalse();
});
