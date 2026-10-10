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
        <form method="POST" action="{{ route('rides.store') }}" class="space-y-8">
            @csrf

            <!-- Route Selection -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Route</h3>

                @if(isset($route))
                    <!-- Pre-selected route from route detail page -->
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-800 font-medium">Route pre-selected from route detail:</p>
                        <p class="text-lg font-semibold text-gray-900 mt-1">{{ $route->name }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $route->distance_km }} km, {{ $route->elevation_gain_m }}m elevation</p>
                        <input type="hidden" name="route_id" value="{{ $route->id }}">
                    </div>
                @else
                    <!-- Normal route selection dropdown -->
                    <div x-data="routeSelect" class="relative">
                        <select name="route_id" id="route_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent appearance-none bg-white" @change="onRouteChange($event)">
                            <option value="">Select a route...</option>
                            @foreach($routes as $route)
                                <option value="{{ $route->id }}" data-start-lat="{{ $route->start_lat }}" data-start-lng="{{ $route->start_lng }}" {{ old('route_id') == $route->id ? 'selected' : '' }}>
                                    {{ $route->name }} ({{ $route->distance_km }} km, {{ $route->elevation_gain_m }}m)
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    @error('route_id')
                        <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                    @enderror
                @endif
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
                        <div x-data="meetingPointPicker" class="space-y-3">
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