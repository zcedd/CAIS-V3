<?php

namespace App\Http\Middleware;

use App\Models\Department;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToDepartment
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $department = $request->route('department');

        if (! $department instanceof Department || $user === null) {
            abort(403);
        }

        if ($user instanceof User && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->department_id !== $department->id) {
            abort(403);
        }

        return $next($request);
    }
}
