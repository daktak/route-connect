<?php

namespace Tests\Feature;

use App\Models\GroupRide;
use App\Models\Route;
use App\Models\RouteComment;
use App\Models\User;
use App\Notifications\NewComment;
use App\Notifications\RideJoined;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user, array $attributes = []): DatabaseNotification
    {
        return $user->notifications()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [
                'type' => 'ride_joined',
                'message' => 'Test notification',
                'url' => 'http://localhost/rides/1',
            ],
            'read_at' => null,
        ], $attributes));
    }

    public function test_user_can_mark_their_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $this->makeNotification($owner);

        $this->actingAs($other)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_only_affects_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->makeNotification($user);
        $this->makeNotification($user, ['read_at' => now()]);
        $otherNotification = $this->makeNotification($other);

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertOk();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_user_can_dismiss_their_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user);

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $notification->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_dismiss_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $this->makeNotification($owner);

        $this->actingAs($other)
            ->delete(route('notifications.destroy', $notification->id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_clear_all_only_deletes_the_current_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->makeNotification($user);
        $this->makeNotification($user);
        $otherNotification = $this->makeNotification($other);

        $this->actingAs($user)
            ->delete(route('notifications.clear-all'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);
    }

    public function test_prune_deletes_only_old_read_notifications(): void
    {
        $user = User::factory()->create();

        $oldRead = $this->makeNotification($user, ['read_at' => now()->subDays(100)]);
        $recentRead = $this->makeNotification($user, ['read_at' => now()->subDays(10)]);
        $oldUnread = $this->makeNotification($user);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $oldUnread->id]);
    }

    public function test_prune_days_option_overrides_the_configured_retention(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification($user, ['read_at' => now()->subDays(5)]);

        $this->artisan('notifications:prune', ['--days' => 3])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_ride_notification_payload_includes_a_url(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $route = Route::create(['user_id' => $organizer->id, 'name' => 'Test Route']);
        $ride = GroupRide::create([
            'route_id' => $route->id,
            'organizer_id' => $organizer->id,
            'title' => 'Test Ride',
            'ride_date' => now()->addDay(),
            'status' => 'planned',
            'version' => 1,
        ]);

        $payload = (new RideJoined($ride, $attendee))->toArray($organizer);

        $this->assertSame(route('rides.show', $ride), $payload['url']);
    }

    public function test_comment_notification_payload_links_to_the_comment(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $route = Route::create(['user_id' => $owner->id, 'name' => 'Test Route']);
        $comment = RouteComment::create([
            'route_id' => $route->id,
            'user_id' => $author->id,
            'content' => 'Nice route',
        ]);

        $payload = (new NewComment($route, $comment, $author))->toArray($owner);

        $this->assertSame(
            route('routes.show', $route).'#comment-'.$comment->id,
            $payload['url']
        );
    }
}
