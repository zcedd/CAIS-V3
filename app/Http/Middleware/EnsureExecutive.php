<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExecutive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can(PermissionName::ProgramApprove->value)) {
            abort(403);
        }

        return $next($request);
    }
}
