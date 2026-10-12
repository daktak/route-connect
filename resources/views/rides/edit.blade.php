<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Edit Group Ride</h1>
                <p class="text-gray-600 mt-1">{{ $ride->title ?: $ride->route->name }}</p>
            </div>
            <a href="{{ route('rides.show', $ride) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Back to Ride</a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Changing the route or date will require all attendees to confirm their attendance again.
        </div>

        <form method="POST" action="{{ route('rides.update', $ride) }}" class="space-y-8">
            @csrf
            @method('PUT')

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
                        'start_lat' => $r->start_lat ?? null,
                        'start_lng' => $r->start_lng ?? null,
                    ])->values();
                    $currentRouteIds = array_map('intval', (array) old('route_ids', $selectedRouteIds));
                @endphp

                <div x-data="routeMultiSelect({{ Js::from($routesConfig) }}, {{ Js::from($currentRouteIds) }})" class="space-y-4">
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
                        <input type="text" name="title" value="{{ old('title', $ride->title) }}" placeholder="e.g., Sunday Morning Spin" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        @error('title')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date & Time *</label>
                        <input type="datetime-local" name="ride_date" value="{{ old('ride_date', $ride->ride_date->timezone($ride->timezoneName())->format('Y-m-d\TH:i')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <p class="mt-1 text-sm text-gray-500">Times are shown in the ride timezone: {{ $ride->timezoneName() }}</p>
                        @error('ride_date')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            @foreach(['planned', 'confirmed', 'cancelled', 'completed'] as $status)
                                <option value="{{ $status }}" {{ old('status', $ride->status) === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meeting Point</label>
                        <input type="text" name="meeting_point_name" value="{{ old('meeting_point_name', $ride->meeting_point_name) }}" placeholder="Meeting point name..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <input type="number" step="any" name="meeting_point_lat" value="{{ old('meeting_point_lat', $ride->meeting_point_lat) }}" placeholder="Latitude" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <input type="number" step="any" name="meeting_point_lng" value="{{ old('meeting_point_lng', $ride->meeting_point_lng) }}" placeholder="Longitude" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        @error('meeting_point_lat')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                        @error('meeting_point_lng')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Participants (optional)</label>
                        <input type="number" name="max_participants" value="{{ old('max_participants', $ride->max_participants) }}" min="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Leave empty for no limit</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                        <textarea name="description" rows="4" placeholder="Any details about the ride..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">{{ old('description', $ride->description) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-between gap-4">
                <button type="submit" form="delete-ride-form" class="px-6 py-3 border border-red-300 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors" onclick="return confirm('Cancel this ride? This cannot be undone.');">Cancel Ride</button>
                <div class="flex gap-4">
                    <a href="{{ route('rides.show', $ride) }}" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Cancel</a>
                    <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-hover transition-colors">Save Changes</button>
                </div>
            </div>
        </form>

        <form id="delete-ride-form" method="POST" action="{{ route('rides.destroy', $ride) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</x-app-layout>
