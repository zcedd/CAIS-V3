<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    private const NOTIFICATIONS_PER_PAGE = 15;

    private const UNREAD_COUNT_CACHE_SECONDS = 60;

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     category: string,
     *     title: string,
     *     message: string,
     *     url: string|null,
     *     data: array<string, mixed>,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    public function serialize(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => $this->category($notification),
            'title' => $this->title($data, $notification->type),
            'message' => $this->message($data),
            'url' => $this->url($data),
            'data' => $data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    public function paginateForUser(User $user): LengthAwarePaginator
    {
        return $user->notifications()
            ->latest()
            ->paginate(self::NOTIFICATIONS_PER_PAGE)
            ->through(fn (DatabaseNotification $notification): array => $this->serialize($notification))
            ->withQueryString();
    }

    public function findForUser(User $user, string $notificationId): DatabaseNotification
    {
        return $user->notifications()->whereKey($notificationId)->firstOrFail();
    }

    public function unreadCountForUser(User $user): int
    {
        return (int) Cache::remember(
            $this->unreadCountCacheKey($user),
            self::UNREAD_COUNT_CACHE_SECONDS,
            fn (): int => $user->notifications()
                ->whereNull('read_at')
                ->toBase()
                ->count(),
        );
    }

    public function forgetUnreadCountCache(User $user): void
    {
        Cache::forget($this->unreadCountCacheKey($user));
    }

    public function markAsReadForUser(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $this->findForUser($user, $notificationId);

        if ($notification->read_at === null) {
            $notification->markAsRead();
            $this->forgetUnreadCountCache($user);
        }

        return $notification->refresh();
    }

    public function markAllAsReadForUser(User $user): int
    {
        $updated = $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        if ($updated > 0) {
            $this->forgetUnreadCountCache($user);
        }

        return $updated;
    }

    private function unreadCountCacheKey(User $user): string
    {
        return "user.{$user->id}.unread_notifications_count";
    }

    public function category(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $category = $data['category'] ?? null;

        if ($category === 'system') {
            return 'system';
        }

        return 'personal';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function title(array $data, string $type): string
    {
        if (is_string($data['title'] ?? null) && trim($data['title']) !== '') {
            return $this->plainText(trim($data['title']));
        }

        $className = class_basename($type);

        return trim((string) preg_replace('/Notification$/i', '', preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $className)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function message(array $data): string
    {
        if (is_string($data['message'] ?? null) && trim($data['message']) !== '') {
            return $this->plainText(trim($data['message']));
        }

        if (is_string($data['body'] ?? null) && trim($data['body']) !== '') {
            return $this->plainText(trim($data['body']));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function url(array $data): ?string
    {
        foreach (['url', 'action_url', 'attach_url', 'link'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $url = trim($value);

                if (str_starts_with($url, '//')) {
                    return null;
                }

                return $url;
            }
        }

        return null;
    }

    private function plainText(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(strip_tags($decoded));
    }
}
