<?php

namespace Tests\Feature;

use App\Models\GroupRide;
use App\Models\RideAttendee;
use App\Models\Route;
use App\Models\User;
use App\Notifications\RideReminder as RideReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendRideRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function makeRide(array $attributes = [], ?User $attendee = null): GroupRide
    {
        $organizer = User::factory()->create();
        $route = Route::create([
            'user_id' => $organizer->id,
            'name' => 'Test Route',
        ]);

        $ride = GroupRide::create(array_merge([
            'route_id' => $route->id,
            'organizer_id' => $organizer->id,
            'title' => 'Test Ride',
            'ride_date' => now()->addHours(23),
            'status' => 'planned',
            'version' => 1,
        ], $attributes));

        $attendee ??= User::factory()->create();

        RideAttendee::create([
            'group_ride_id' => $ride->id,
            'user_id' => $attendee->id,
            'ride_version' => $ride->version,
            'status' => 'confirmed',
        ]);

        return $ride;
    }

    public function test_reminder_is_sent_once_within_the_window(): void
    {
        Notification::fake();
        Queue::fake();

        $attendee = User::factory()->create();
        $ride = $this->makeRide(attendee: $attendee);

        $this->artisan('rides:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($attendee, RideReminderNotification::class, 1);
        $this->assertNotNull($ride->fresh()->reminder_sent_at);

        $this->artisan('rides:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($attendee, RideReminderNotification::class, 1);
    }

    public function test_reminder_is_skipped_outside_the_window(): void
    {
        Notification::fake();
        Queue::fake();

        $attendee = User::factory()->create();
        $this->makeRide(['ride_date' => now()->addHours(30)], $attendee);

        $this->artisan('rides:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminder_is_skipped_for_a_ride_that_already_started(): void
    {
        Notification::fake();
        Queue::fake();

        $attendee = User::factory()->create();
        $this->makeRide(['ride_date' => now()->subHour()], $attendee);

        $this->artisan('rides:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminder_is_skipped_when_already_sent(): void
    {
        Notification::fake();
        Queue::fake();

        $attendee = User::factory()->create();
        $ride = $this->makeRide(attendee: $attendee);
        $ride->forceFill(['reminder_sent_at' => now()->subHour()])->save();

        $this->artisan('rides:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_ride_without_confirmed_attendees_is_left_unmarked(): void
    {
        Notification::fake();
        Queue::fake();

        $organizer = User::factory()->create();
        $route = Route::create(['user_id' => $organizer->id, 'name' => 'Solo Route']);
        $ride = GroupRide::create([
            'route_id' => $route->id,
            'organizer_id' => $organizer->id,
            'title' => 'Solo Ride',
            'ride_date' => now()->addHours(23),
            'status' => 'planned',
            'version' => 1,
        ]);

        $this->artisan('rides:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($ride->fresh()->reminder_sent_at);
    }

    public function test_hours_option_overrides_the_window(): void
    {
        Notification::fake();
        Queue::fake();

        $attendee = User::factory()->create();
        $this->makeRide(['ride_date' => now()->addHours(40)], $attendee);

        $this->artisan('rides:send-reminders', ['--hours' => 48])->assertSuccessful();

        Notification::assertSentToTimes($attendee, RideReminderNotification::class, 1);
    }
}
