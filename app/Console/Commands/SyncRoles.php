<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions and the roles that ship with HubTube.
 *
 * Idempotent, so it is safe on every deploy: existing roles keep whatever an
 * admin has since changed on the Roles screen unless --reset is passed.
 */
class SyncRoles extends Command
{
    protected $signature = 'hubtube:sync-roles {--reset : Also reset each seeded role back to its default permissions}';

    protected $description = 'Create HubTube permissions and the moderator, editor, uploader and trusted_uploader roles';

    public function handle(): int
    {
        $guard = config('auth.defaults.guard', 'web');

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, $guard);
        }

        $this->info('Permissions: '.count(Permissions::all()).' available.');

        foreach (Permissions::roles() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $existed = $role->wasRecentlyCreated === false;

            if (! $existed || $this->option('reset')) {
                $role->syncPermissions($permissions);
                $this->line(sprintf(
                    '  %s %s (%d permissions)',
                    $existed ? 'reset' : 'created',
                    $roleName,
                    count($permissions)
                ));

                continue;
            }

            $this->line("  kept {$roleName} ({$role->permissions->count()} permissions) — pass --reset to restore defaults");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('Roles are assigned per user in Admin → Users, and edited in Admin → System → Roles.');

        return self::SUCCESS;
    }
}
