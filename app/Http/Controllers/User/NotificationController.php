<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Notification\IndexRequest;
use App\Http\Requests\User\Notification\ShowRequest;
use App\Models\Department;
use App\Services\User\NotificationService;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Display notifications for the authenticated user.
     */
    public function index(IndexRequest $request, Department $department): Response
    {
        return Inertia::render('user/notifications/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'notifications' => $this->notificationService->paginateForUser(
                $request->user(),
            ),
        ]);
    }

    /**
     * Display a single notification for the authenticated user.
     */
    public function show(ShowRequest $request, Department $department, string $notification): Response
    {
        $notificationModel = $this->notificationService->findForUser(
            $request->user(),
            $notification,
        );

        return Inertia::render('user/notifications/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'notification' => $this->notificationService->serialize($notificationModel),
        ]);
    }
}
