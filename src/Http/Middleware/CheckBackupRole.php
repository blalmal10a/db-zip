<?php

namespace Blalmal10a\DbZip\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckBackupRole
{
    public function handle(Request $request, Closure $next)
    {
        $roles = config('db-zip.required_roles', []);

        if (empty($roles)) {
            return $next($request);
        }

        $user = $request->user();

        /** @phpstan-ignore method.notFound */
        abort_unless($user && $user->hasAnyRole($roles), 403);

        return $next($request);
    }
}
