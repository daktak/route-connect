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

                <div class="relative">
                    <select name="route_id" id="route_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent appearance-none bg-white">
                        <option value="">Select a route...</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}" {{ old('route_id') == $route->id ? 'selected' : '' }}>
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

                @if(old('route_id'))
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg" x-data="routeMap" x-init="
                        initMap();
                        if (routeGeometry) {
                            const color = getDifficultyColor(difficulty);
                            L.geoJSON(routeGeometry, { style: { color, weight: 4, opacity: 0.9 } }).addTo(routesLayer);
                            map.fitBounds(routesLayer.getBounds(), { padding: [20, 20] });
                        }
                    " :route-geometry="@json($selectedRoute->geometry ?? null)" :difficulty="{{ $selectedRoute->difficulty ?? 'moderate' }}"></div>
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
                        <div x-data="{ lat: null, lng: null, searching: false }" class="space-y-3">
                            <div class="flex gap-2">
                                <input type="text" name="meeting_point_name" value="{{ old('meeting_point_name') }}" placeholder="Search or enter meeting point name..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <button type="button" @click="searchLocation()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50" :disabled="searching">
                                    <span x-show="!searching">Search</span>
                                    <span x-show="searching">Searching...</span>
                                </button>
                            </div>
                            <input type="hidden" name="meeting_point_lat" :value="lat">
                            <input type="hidden" name="meeting_point_lng" :value="lng">
                            <div x-show="lat && lng" class="text-sm text-gray-500">Selected: {{ lat.toFixed(6) }}, {{ lng.toFixed(6) }}</div>
                            <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden" x-data="routeMap" x-init="
                                initMap();
                                if (lat && lng) {
                                    map.setView([lat, lng], 15);
                                    L.marker([lat, lng]).addTo(map);
                                }
                                map.on('click', e => {
                                    lat = e.latlng.lat;
                                    lng = e.latlng.lng;
                                    this.$dispatch('location-selected', { lat, lng });
                                });
                            ">
                            </div>
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

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('rideCreateMap', () => ({
                lat: null,
                lng: null,
                searching: false,
                map: null,
                marker: null,

                init() {
                    this.map = L.map(this.$el, { zoomControl: true }).setView([47.0, 8.0], 8);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(this.map);

                    this.map.on('click', (e) => {
                        this.lat = e.latlng.lat;
                        this.lng = e.latlng.lng;
                        this.updateMarker();
                        this.$dispatch('location-selected', { lat: this.lat, lng: this.lng });
                    });

                    this.$watch('lat', () => {
                        this.$el.querySelector('input[name="meeting_point_lat"]').value = this.lat;
                    });
                    this.$watch('lng', () => {
                        this.$el.querySelector('input[name="meeting_point_lng"]').value = this.lng;
                    });
                },

                async searchLocation() {
                    this.searching = true;
                    const query = this.$el.querySelector('input[name="meeting_point_name"]').value;
                    if (!query) { this.searching = false; return; }

                    try {
                        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
                        const results = await response.json();
                        if (results.length > 0) {
                            this.lat = parseFloat(results[0].lat);
                            this.lng = parseFloat(results[0].lon);
                            this.updateMarker();
                            this.map.setView([this.lat, this.lng], 15);
                        }
                    } catch (e) {
                        console.error('Geocoding failed', e);
                    } finally {
                        this.searching = false;
                    }
                },

                updateMarker() {
                    if (this.marker) this.map.removeLayer(this.marker);
                    this.marker = L.marker([this.lat, this.lng]).addTo(this.map);
                },
            }));
        });
    </script>
</x-layouts.app>