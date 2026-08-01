<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates a user account from the console.
 *
 * Registration is disabled on purpose — no Features::registration(), no register
 * route — so this is the only way to bootstrap the first account, and the escape
 * hatch for any account that cannot come through the invitation flow.
 *
 * Interactive by design: the password never lands in shell history.
 */
final class CreateUserCommand extends Command
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    protected $signature = 'user:create';

    protected $description = 'Create a user account interactively (registration is disabled by design)';

    /** Roles offered here; `owner` belongs to workspace creation, not to this command. */
    private const ROLES = ['admin', 'member', 'guest'];

    private const NO_WORKSPACE = '__none__';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('user:create is interactive by design; run it from a terminal.');

            return self::FAILURE;
        }

        $name = (string) text(
            label: 'Nombre',
            validate: $this->rulesFor('name', $this->nameRules()),
        );

        $email = (string) text(
            label: 'Email',
            validate: $this->rulesFor('email', $this->emailRules()),
        );

        $plainPassword = (string) password(
            label: 'Contraseña',
            validate: $this->rulesFor('password', $this->passwordRulesWithoutConfirmation()),
        );

        password(
            label: 'Confirma la contraseña',
            validate: fn (string $value): ?string => $value === $plainPassword
                ? null
                : 'La confirmación no coincide.',
        );

        [$workspaceId, $role] = $this->askForMembership();

        $this->newLine();
        $this->components->twoColumnDetail('Nombre', $name);
        $this->components->twoColumnDetail('Email', $email);
        $this->components->twoColumnDetail(
            'Workspace',
            $workspaceId === null ? 'ninguno' : Workspace::query()->whereKey($workspaceId)->value('name').' ('.$role.')',
        );

        if (! confirm(label: '¿Crear el usuario?', default: true)) {
            $this->components->warn('Cancelado, no se ha creado nada.');

            return self::FAILURE;
        }

        $user = $this->createUser($name, $email, $plainPassword, $workspaceId, $role);

        $this->components->info("Usuario {$user->email} creado (#{$user->getKey()}).");

        return self::SUCCESS;
    }

    /**
     * @return array{0:?int,1:?string}
     */
    private function askForMembership(): array
    {
        $workspaces = Workspace::query()->orderBy('name')->pluck('name', 'id');

        if ($workspaces->isEmpty()) {
            $this->components->info('No hay workspaces todavía: el usuario se creará sin pertenencia.');

            return [null, null];
        }

        $choice = select(
            label: 'Workspace',
            options: [self::NO_WORKSPACE => 'Ninguno'] + $workspaces->all(),
        );

        if ((string) $choice === self::NO_WORKSPACE) {
            return [null, null];
        }

        $role = (string) select(
            label: 'Rol',
            options: array_combine(self::ROLES, self::ROLES),
            default: 'member',
        );

        return [(int) $choice, $role];
    }

    /**
     * Mirrors InvitationAcceptController::accept() so there is one canonical way
     * a user is born in this codebase.
     */
    private function createUser(string $name, string $email, string $plainPassword, ?int $workspaceId, ?string $role): User
    {
        return DB::transaction(function () use ($name, $email, $plainPassword, $workspaceId, $role): User {
            // The password is passed raw on purpose: User::casts() declares
            // 'password' => 'hashed'. Hashing here would double-hash it and
            // produce an account that can never log in.
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $plainPassword,
            ]);

            // Email verification is enabled and, with no register route, the user
            // has no way to trigger the verification mail.
            $user->forceFill(['email_verified_at' => now()])->save();

            if ($workspaceId !== null) {
                WorkspaceMember::query()->firstOrCreate(
                    ['workspace_id' => $workspaceId, 'user_id' => (int) $user->getKey()],
                    ['role' => $role, 'joined_at' => now()],
                );
            }

            return $user;
        });
    }

    /**
     * Prompt validator backed by the application's own rules, so the command
     * cannot create an account the app itself would reject.
     *
     * Validating under the real attribute name matters: Rule::unique() infers
     * its column from it.
     *
     * @param  array<int,mixed>  $rules
     */
    private function rulesFor(string $attribute, array $rules): Closure
    {
        return function (mixed $value) use ($attribute, $rules): ?string {
            $validator = Validator::make([$attribute => $value], [$attribute => $rules]);

            return $validator->fails() ? $validator->errors()->first($attribute) : null;
        };
    }

    /**
     * passwordRules() carries `confirmed`, which needs a companion field. The
     * confirmation is prompted separately, so drop it here.
     *
     * @return array<int,mixed>
     */
    private function passwordRulesWithoutConfirmation(): array
    {
        return array_values(array_filter(
            $this->passwordRules(),
            fn (mixed $rule): bool => $rule !== 'confirmed',
        ));
    }
}
