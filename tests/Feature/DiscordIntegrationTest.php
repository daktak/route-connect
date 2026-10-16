<?php

namespace Tests\Feature;

use App\Events\CommentCreated;
use App\Events\RideCancelled;
use App\Events\RideChanged;
use App\Events\RideCreated;
use App\Events\RideJoined;
use App\Events\RideLeft;
use App\Events\RideReminder;
use App\Jobs\SendDiscordMessage;
use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Models\Route;
use App\Models\RouteComment;
use App\Models\User;
use App\Notifications\NewComment;
use App\Notifications\RideCancelled as RideCancelledNotification;
use App\Notifications\RideChanged as RideChangedNotification;
use App\Notifications\RideJoined as RideJoinedNotification;
use App\Notifications\RideLeft as RideLeftNotification;
use App\Notifications\RideReminder as RideReminderNotification;
use App\Services\DiscordService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiscordIntegrationTest extends TestCase
{
    private function ride(array $attributes = []): GroupRide
    {
        $ride = new GroupRide(array_merge([
            'title' => 'Sunday Loop',
            'ride_date' => now()->addDay(),
            'timezone' => 'Australia/Melbourne',
            'meeting_point_name' => 'Fed Square',
            'status' => 'planned',
            'version' => 1,
        ], $attributes));
        $ride->id = 1;
        $route = new Route(['name' => 'Yarra Bend']);
        $route->id = 5;
        $ride->setRelation('route', $route);

        return $ride;
    }

    private function user(string $name, int $id): User
    {
        $user = new User(['name' => $name]);
        $user->id = $id;

        return $user;
    }

    public function test_ride_created_posts_to_discord(): void
    {
        Queue::fake();

        $creator = $this->user('Alice', 10);
        event(new RideCreated($this->ride(), $creator));

        Queue::assertPushed(SendDiscordMessage::class, function (SendDiscordMessage $job) {
            return str_contains($job->content, 'Alice has created a ride')
                && str_contains($job->content, '[Sunday Loop](')
                && str_contains($job->content, '/rides/1)')
                && str_contains($job->content, '[Yarra Bend](')
                && str_contains($job->content, '/routes/5)')
                && str_contains($job->content, 'leaving from Fed Square');
        });
    }

    public function test_ride_created_uses_fallback_meeting_point(): void
    {
        Queue::fake();

        $creator = $this->user('Alice', 10);
        event(new RideCreated($this->ride(['meeting_point_name' => null]), $creator));

        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'leaving from TBA'));
    }

    public function test_ride_created_escapes_markdown_in_labels(): void
    {
        Queue::fake();

        $creator = $this->user('Alice', 10);
        $ride = $this->ride(['title' => 'Sunday [Loop]']);

        event(new RideCreated($ride, $creator));

        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, '[Sunday \[Loop\]]('));
    }

    public function test_ride_joined_notifies_organizer_and_posts(): void
    {
        Queue::fake();
        Notification::fake();

        $organizer = $this->user('Olive', 2);
        $attendee = $this->user('Bob', 3);
        $ride = $this->ride(['organizer_id' => 2]);
        $ride->setRelation('organizer', $organizer);

        event(new RideJoined($ride, $attendee));

        Notification::assertSentTo($organizer, RideJoinedNotification::class);
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Bob has joined ride [Sunday Loop]('));
    }

    public function test_ride_joined_by_organizer_posts_without_notifying(): void
    {
        Queue::fake();
        Notification::fake();

        $organizer = $this->user('Olive', 2);
        $ride = $this->ride(['organizer_id' => 2]);
        $ride->setRelation('organizer', $organizer);

        event(new RideJoined($ride, $organizer));

        Notification::assertNothingSent();
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Olive has joined ride [Sunday Loop]('));
    }

    public function test_ride_left_notifies_organizer_and_posts(): void
    {
        Queue::fake();
        Notification::fake();

        $organizer = $this->user('Olive', 2);
        $attendee = $this->user('Bob', 3);
        $ride = $this->ride(['organizer_id' => 2]);
        $ride->setRelation('organizer', $organizer);

        event(new RideLeft($ride, $attendee));

        Notification::assertSentTo($organizer, RideLeftNotification::class);
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Bob has left ride [Sunday Loop]('));
    }

    public function test_ride_left_by_organizer_posts_without_notifying(): void
    {
        Queue::fake();
        Notification::fake();

        $organizer = $this->user('Olive', 2);
        $ride = $this->ride(['organizer_id' => 2]);
        $ride->setRelation('organizer', $organizer);

        event(new RideLeft($ride, $organizer));

        Notification::assertNothingSent();
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Olive has left ride [Sunday Loop]('));
    }

    public function test_ride_changed_notifies_removed_attendees_and_posts(): void
    {
        Queue::fake();
        Notification::fake();

        $actor = $this->user('Olive', 2);
        $attendeeUser = $this->user('Bob', 3);
        $attendee = new RideAttendee(['group_ride_id' => 1, 'user_id' => 3, 'ride_version' => 1]);
        $attendee->setRelation('user', $attendeeUser);

        event(new RideChanged($this->ride(), 'route changed', collect([$attendee]), $actor));

        Notification::assertSentTo($attendeeUser, RideChangedNotification::class);
        Queue::assertPushed(SendDiscordMessage::class, function (SendDiscordMessage $job) {
            return str_contains($job->content, 'Olive updated ride [Sunday Loop](')
                && str_contains($job->content, 'attendees must reconfirm');
        });
    }

    public function test_ride_cancelled_notifies_attendees_and_posts(): void
    {
        Queue::fake();
        Notification::fake();

        $attendeeUser = $this->user('Bob', 3);
        $attendee = new RideAttendee(['group_ride_id' => 1, 'user_id' => 3, 'ride_version' => 1]);
        $attendee->setRelation('user', $attendeeUser);

        event(new RideCancelled($this->ride(), 'cancelled', collect([$attendee])));

        Notification::assertSentTo($attendeeUser, RideCancelledNotification::class);
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Ride Sunday Loop scheduled for') && str_contains($job->content, 'has been cancelled'));
    }

    public function test_ride_reminder_notifies_each_user_and_posts_once(): void
    {
        Queue::fake();
        Notification::fake();

        $first = $this->user('Bob', 3);
        $second = $this->user('Cara', 4);
        $ride = $this->ride();

        event(new RideReminder($ride, collect([$first, $second])));

        Notification::assertSentTo($first, RideReminderNotification::class);
        Notification::assertSentTo($second, RideReminderNotification::class);
        Queue::assertPushed(SendDiscordMessage::class, 1);
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Reminder: ride [Sunday Loop](') && str_contains($job->content, 'starts at'));
    }

    public function test_comment_notifies_route_author_and_posts(): void
    {
        Queue::fake();
        Notification::fake();

        $author = $this->user('Carol', 4);
        $owner = $this->user('Dave', 5);

        $route = new Route(['name' => 'Beach Rd', 'user_id' => 5]);
        $route->id = 7;
        $route->setRelation('user', $owner);

        $comment = new RouteComment(['route_id' => 7, 'user_id' => 4, 'content' => 'nice']);
        $comment->id = 9;

        event(new CommentCreated($route, $comment, $author));

        Notification::assertSentTo($owner, NewComment::class);
        Queue::assertPushed(SendDiscordMessage::class, function (SendDiscordMessage $job) {
            return str_contains($job->content, 'Carol commented on [Beach Rd](')
                && str_contains($job->content, '/routes/7#comment-9)');
        });
    }

    public function test_comment_by_route_author_posts_without_notifying(): void
    {
        Queue::fake();
        Notification::fake();

        $owner = $this->user('Dave', 5);

        $route = new Route(['name' => 'Beach Rd', 'user_id' => 5]);
        $route->id = 7;
        $route->setRelation('user', $owner);

        $comment = new RouteComment(['route_id' => 7, 'user_id' => 5, 'content' => 'mine']);
        $comment->id = 9;

        event(new CommentCreated($route, $comment, $owner));

        Notification::assertNothingSent();
        Queue::assertPushed(SendDiscordMessage::class, fn (SendDiscordMessage $job) => str_contains($job->content, 'Dave commented on [Beach Rd]('));
    }

    public function test_discord_service_posts_content_to_webhook(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.example/webhook']);
        Http::fake(['discord.example/*' => Http::response('', 204)]);

        $sent = app(DiscordService::class)->post('hello room');

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request->url() === 'https://discord.example/webhook' && $request['content'] === 'hello room');
    }

    public function test_discord_service_is_a_noop_without_a_webhook(): void
    {
        config(['services.discord.webhook_url' => null]);
        Http::fake();

        $this->assertFalse(app(DiscordService::class)->post('hello'));

        Http::assertNothingSent();
    }

    public function test_discord_service_returns_false_on_error_response(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.example/webhook']);
        Http::fake(['*' => Http::response('bad request', 400)]);

        $this->assertFalse(app(DiscordService::class)->post('hello'));
    }

    public function test_discord_service_returns_false_on_connection_exception(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.example/webhook']);
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertFalse(app(DiscordService::class)->post('hello'));
    }

    public function test_send_discord_message_job_posts_content(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.example/webhook']);
        Http::fake(['*' => Http::response('', 204)]);

        (new SendDiscordMessage('from the job'))->handle(app(DiscordService::class));

        Http::assertSent(fn ($request) => $request['content'] === 'from the job');
    }
}
