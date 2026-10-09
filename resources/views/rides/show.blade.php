<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $ride->title ?? $ride->route->name }}</h1>
                <p class="text-gray-600 mt-1">
                    {{ $ride->ride_date->format('l, F j, Y \a\t g:i A') }}
                    @if($ride->meeting_point_name)
                        • {{ $ride->meeting_point_name }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="ride-status {{ $ride->status }}">{{ ucfirst($ride->status) }}</span>
                @auth
                    @can('update', $ride)
                        <a href="{{ route('rides.edit', $ride) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Edit</a>
                    @endcan
                @endauth
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Route Map -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                    <div class="aspect-video relative">
                        <div id="ride-map" class="absolute inset-0" x-data="routeMap" x-init="
                            initMap();
                            if (routeGeometry) {
                                const color = getDifficultyColor(difficulty);
                                L.geoJSON(routeGeometry, { style: { color, weight: 4, opacity: 0.9 } }).addTo(routesLayer);
                                map.fitBounds(routesLayer.getBounds(), { padding: [20, 20] });
                            }
                            @if($ride->meeting_point_lat && $ride->meeting_point_lng)
                                L.marker([{{ $ride->meeting_point_lat }}, {{ $ride->meeting_point_lng }}], {
                                    icon: L.divIcon({
                                        className: 'custom-marker',
                                        html: '<div class="text-3xl">📍</div>',
                                        iconSize: [30, 30],
                                        iconAnchor: [15, 30],
                                    })
                                }).bindPopup('Meeting Point: {{ $ride->meeting_point_name }}').addTo(map);
                            @endif
                        " :route-geometry="@json($ride->route->geometry)" :difficulty="{{ $ride->route->difficulty }}"></div>
                    </div>
                </div>

                <!-- Ride Details -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ride Details</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm text-gray-500">Date & Time</dt>
                            <dd class="font-medium text-gray-900">{{ $ride->ride_date->format('l, F j, Y \a\t g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Meeting Point</dt>
                            <dd class="font-medium text-gray-900">{{ $ride->meeting_point_name ?? 'Not specified' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Route</dt>
                            <dd class="font-medium text-gray-900">
                                <a href="{{ route('routes.show', $ride->route) }}" class="text-primary hover:underline">{{ $ride->route->name }}</a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Distance / Elevation</dt>
                            <dd class="font-medium text-gray-900">{{ $ride->route->distance_km }} km • {{ $ride->route->elevation_gain_m }}m</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Difficulty</dt>
                            <dd><span class="difficulty-badge {{ $ride->route->difficulty }}">{{ ucfirst($ride->route->difficulty) }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Max Participants</dt>
                            <dd class="font-medium text-gray-900">{{ $ride->max_participants ?? 'No limit' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Organizer</dt>
                            <dd class="font-medium text-gray-900">{{ $ride->organizer->name }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm text-gray-500">Description</dt>
                            <dd class="font-medium text-gray-900">{{ $ride->description ?? 'No description provided' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Attendees -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Attendees ({{ $ride->attendees_count }}{{ $ride->max_participants ? ' / ' . $ride->max_participants : '' }})</h3>
                        @auth
                            @php
                                $attendee = $ride->attendees()->where('user_id', auth()->id())->where('ride_version', $ride->version)->first();
                            @endphp
                            <div x-data="rideJoin" :ride-id="{{ $ride->id }}" :user-id="{{ auth()->id() }}" :status="{{ $attendee->status ?? '' }}" :ride-version="{{ $ride->version }}">
                                <button @click="toggle()" :disabled="loading" :class="buttonClass + ' px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors'" x-text="buttonText"></button>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors">Log in to join</a>
                        @endauth
                    </div>

                    <div class="divide-y divide-gray-100">
                        @foreach($ride->attendees()->where('ride_version', $ride->version)->with('user')->get() as $attendee)
                            <div class="p-4 flex items-center justify-between hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-medium">
                                        {{ strtoupper($attendee->user->name[0]) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $attendee->user->name }}</p>
                                        <p class="text-sm text-gray-500">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ $attendee->status === 'confirmed' ? 'bg-green-100 text-green-800' :
                                                   ($attendee->status === 'tentative' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                {{ ucfirst($attendee->status) }}
                                            </span>
                                            @if($attendee->joined_at)
                                                • Joined {{ $attendee->joined_at->diffForHumans() }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @if($attendee->note)
                                    <p class="text-sm text-gray-500 max-w-xs">{{ $attendee->note }}</p>
                                @endif
                            </div>
                        @endforeach

                        @if($ride->attendees_count === 0)
                            <div class="p-6 text-center text-gray-500">No attendees yet. Be the first to join!</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-1 space-y-6">
                <!-- Route Info Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Route Info</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Distance</span>
                            <span class="font-semibold">{{ $ride->route->distance_km }} km</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Elevation Gain</span>
                            <span class="font-semibold">{{ $ride->route->elevation_gain_m }} m</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Difficulty</span>
                            <span class="difficulty-badge {{ $ride->route->difficulty }}">{{ ucfirst($ride->route->difficulty) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Est. Time</span>
                            <span class="font-semibold">{{ $ride->route->estimated_time_min ?? '—' }} min</span>
                        </div>
                    </div>
                    <a href="{{ route('routes.show', $ride->route) }}" class="mt-4 block text-center text-primary hover:underline text-sm">View Route Details</a>
                </div>

                <!-- Organizer -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Organizer</h3>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xl">
                            {{ strtoupper($ride->organizer->name[0]) }}
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ $ride->organizer->name }}</p>
                            <p class="text-sm text-gray-500">{{ $ride->organizer->routes()->count() }} routes</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                @auth
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 space-y-3">
                        @can('update', $ride)
                            <a href="{{ route('rides.edit', $ride) }}" class="w-full bg-gray-100 text-gray-700 px-4 py-3 rounded-lg text-center font-medium hover:bg-gray-200 transition-colors block">Edit Ride</a>
                        @endcan
                        @can('delete', $ride)
                            <form method="POST" action="{{ route('rides.destroy', $ride) }}" onsubmit="return confirm('Delete this ride?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full bg-red-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors">Cancel Ride</button>
                            </form>
                        @endcan
                    </div>
                @endauth
            </aside>
        </div>
    </div>
</x-app-layout>