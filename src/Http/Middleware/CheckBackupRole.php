<?php

namespace Blalmal10a\DbZip\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBackupRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = config('db-zip.required_roles', []);

        if (empty($roles)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            abort(403, 'Access denied: role check requires Spatie Laravel Permission.');
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
