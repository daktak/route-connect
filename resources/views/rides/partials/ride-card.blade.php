<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-gray-900 truncate">{{ $ride->title ?? $ride->route->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $ride->route->name }}</p>
            </div>
            <span class="ride-status {{ $ride->status }} flex-shrink-0">{{ ucfirst($ride->status) }}</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-4">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>{{ $ride->ride_date->format('l, M j, Y') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $ride->ride_date->format('g:i A') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                <span>{{ $ride->route->distance_km }} km</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                <span>{{ $ride->route->elevation_gain_m }}m</span>
            </div>
        </div>

        @if($ride->meeting_point_name)
            <div class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                <span>{{ $ride->meeting_point_name }}</span>
            </div>
        @endif

        @if($ride->description)
            <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $ride->description }}</p>
        @endif

        @if($ride->weather)
            @include('rides.partials.weather', [
                'weather' => $ride->weather,
                'location' => $ride->meeting_point_name ?: $ride->route->name . ' start',
            ])
        @endif

        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
            <div class="flex items-center gap-4">
                <div class="flex -space-x-2">
                    @foreach($ride->attendees->take(5) as $attendee)
                        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-medium border-2 border-white" title="{{ $attendee->user->name }}">
                            {{ strtoupper($attendee->user->name[0]) }}
                        </div>
                    @endforeach
                    @if($ride->attendees->count() > 5)
                        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-medium text-gray-600 border-2 border-white">
                            +{{ $ride->attendees->count() - 5 }}
                        </div>
                    @endif
                </div>
                <span class="text-sm text-gray-600">{{ $ride->attendees_count }} / {{ $ride->max_participants ?? '∞' }}</span>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('rides.show', $ride) }}" class="text-sm text-primary hover:underline font-medium">View Details</a>

                @auth
                    @php
                        $attendee = $ride->attendees()->where('user_id', auth()->id())->where('ride_version', $ride->version)->first();
                    @endphp
                    <div x-data="rideJoin" :ride-id="{{ $ride->id }}" :user-id="{{ auth()->id() }}" :status="{{ Js::from($attendee->status ?? null) }}" :ride-version="{{ $ride->version }}">
                        <button @click="toggle()" :disabled="loading" :class="buttonClass + ' px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors'" x-text="buttonText"></button>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors">Log in to join</a>
                @endauth
            </div>
        </div>
    </div>
</div>