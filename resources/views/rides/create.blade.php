<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Create Group Ride</h1>
                <p class="text-gray-600 mt-1">Organize a ride with the community</p>
            </div>
            <a href="{{ route('rides.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Back to Rides</a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @php
            $firstRoute = collect($selectedRouteIds)->map(fn ($id) => $routes->firstWhere('id', $id))->filter()->first();
        @endphp
        <form method="POST" action="{{ route('rides.store') }}" class="space-y-8">
            @csrf

            <!-- Route Selection -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Select Route(s)</h3>
                <p class="text-sm text-gray-500 mb-4">Select one or more routes. With more than one, confirmed riders can vote for their preferred route. The first route is the default.</p>

                @php
                    $routesConfig = $routes->map(fn ($r) => [
                        'id' => (int) $r->id,
                        'name' => $r->name,
                        'distance_km' => $r->distance_km,
                        'elevation_gain_m' => $r->elevation_gain_m,
                        'difficulty' => $r->difficulty,
                        'start_lat' => $r->start_lat,
                        'start_lng' => $r->start_lng,
                    ])->values();
                @endphp

                <div x-data="routeMultiSelect({{ Js::from($routesConfig) }}, {{ Js::from($selectedRouteIds) }})" class="space-y-4">
                    <input type="text" x-model="search" placeholder="Search routes..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">

                    <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                        <template x-for="route in filteredRoutes" :key="route.id">
                            <label class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" :checked="isSelected(route.id)" @change="toggle(route.id)" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-gray-900 truncate" x-text="route.name"></span>
                                    <span class="block text-sm text-gray-500" x-text="`${route.distance_km} km • ${route.elevation_gain_m}m`"></span>
                                </span>
                            </label>
                        </template>
                        <template x-if="filteredRoutes.length === 0">
                            <p class="p-3 text-sm text-gray-500">No routes match your search.</p>
                        </template>
                    </div>

                    <div x-show="selected.length > 0" x-cloak>
                        <p class="text-sm font-medium text-gray-700 mb-2">Selected routes <span class="text-gray-400 font-normal">(first is the default route)</span></p>
                        <ul class="space-y-2">
                            <template x-for="(id, index) in selected" :key="id">
                                <li class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
                                    <span class="w-6 h-6 flex items-center justify-center rounded-full bg-primary text-white text-xs font-medium" x-text="index + 1"></span>
                                    <span class="flex-1 min-w-0 truncate text-sm text-gray-900" x-text="routeById(id)?.name"></span>
                                    <button type="button" @click="move(id, -1)" :disabled="index === 0" class="text-gray-400 hover:text-gray-700 disabled:opacity-30" title="Move up">&uarr;</button>
                                    <button type="button" @click="move(id, 1)" :disabled="index === selected.length - 1" class="text-gray-400 hover:text-gray-700 disabled:opacity-30" title="Move down">&darr;</button>
                                    <button type="button" @click="remove(id)" class="text-red-500 hover:text-red-700" title="Remove">&times;</button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <template x-for="id in selected" :key="`input-${id}`">
                        <input type="hidden" name="route_ids[]" :value="id">
                    </template>
                </div>
                @error('route_ids')
                    <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                @enderror
                @error('route_ids.*')
                    <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <!-- Ride Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Ride Details</h3>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ride Title (optional)</label>
                        <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g., Sunday Morning Spin" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date & Time *</label>
                        <input type="datetime-local" name="ride_date" value="{{ old('ride_date') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        @error('ride_date')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meeting Point</label>
                        <div x-data="meetingPointPicker({ lat: {{ $firstRoute->start_lat ?? 'null' }}, lng: {{ $firstRoute->start_lng ?? 'null' }} })" class="space-y-3">
                            <div class="flex gap-2">
                                <input type="text" name="meeting_point_name" x-model="name" placeholder="Search or enter meeting point name..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <button type="button" @click="search()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50" :disabled="searching">
                                    <span x-show="!searching">Search</span>
                                    <span x-show="searching" x-cloak>Searching...</span>
                                </button>
                            </div>
                            <input type="hidden" name="meeting_point_lat" :value="lat">
                            <input type="hidden" name="meeting_point_lng" :value="lng">
                            <div x-show="lat && lng" x-cloak class="text-sm text-gray-500">
                                Selected: <span x-text="lat ? lat.toFixed(6) : ''"></span>, <span x-text="lng ? lng.toFixed(6) : ''"></span>
                                <span x-show="resolvingName" class="text-gray-400">• resolving name…</span>
                                <span x-show="timezone" x-cloak class="text-gray-400">• Times shown in <span x-text="timezone"></span></span>
                            </div>
                            <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden" x-ref="map"></div>
                            <p class="text-xs text-gray-400">Click on the map to set the meeting point.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Participants (optional)</label>
                        <input type="number" name="max_participants" value="{{ old('max_participants') }}" min="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Leave empty for no limit</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                        <textarea name="description" rows="4" placeholder="Any details about the ride..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-4">
                <a href="{{ route('rides.index') }}" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-hover transition-colors">Create Ride</button>
            </div>
        </form>
    </div>
</x-app-layout>