# db-zip-role-access

Activate this skill when implementing role-based access control for backup/restore pages.

## How it works

1. **Config-driven** — `config('db-zip.required_roles')` defines which Spatie roles are allowed. If empty array, any authenticated user can access.

2. **Middleware** — `CheckBackupRole` middleware checks:
   ```php
   $roles = config('db-zip.required_roles');
   if (empty($roles)) return $next($request);
   abort_unless($request->user()?->hasAnyRole($roles), 403);
   ```

3. **Middleware registration** — Register in the ServiceProvider:
   ```php
   $router->aliasMiddleware('backup-role', CheckBackupRole::class);
   ```

4. **Route-level** — Apply `backup-role` middleware in the route group alongside auth.

## Extending

Publishable base controller allows consuming apps to override `authorizeBackupAccess()` for custom logic.

## Dependencies

- `spatie/laravel-permission` — must be installed in the consuming app for role checking.
