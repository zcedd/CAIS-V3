<?php

namespace App\Http\Middleware;

use App\Models\DistributionBatch;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileDistributionDepartment
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $batch = $request->route('distributionBatch');
        $user = $request->user();

        if ($batch instanceof DistributionBatch && $user instanceof User) {
            abort_unless((int) $batch->department_id === (int) $user->department_id, 404);
        }

        return $next($request);
    }
}
