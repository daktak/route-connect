<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Cycling Routes</h1>
                <p class="text-gray-600 mt-1">Discover and share cycling routes</p>
            </div>
            @auth
                <a href="{{ route('routes.create') }}" class="bg-primary text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-hover transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Route
                </a>
            @endauth
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Filters Sidebar -->
            <aside x-data="routeFilters" class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>

                    <!-- Search -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" x-model="filters.search" placeholder="Route name, description..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <!-- Difficulty -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                        <select x-model="filters.difficulty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">All</option>
                            <option value="easy">Easy</option>
                            <option value="moderate">Moderate</option>
                            <option value="hard">Hard</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>

                    <!-- Distance Range -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Distance (km)</label>
                        <div class="flex gap-2">
                            <input type="number" x-model.number="filters.minDistance" placeholder="Min" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <input type="number" x-model.number="filters.maxDistance" placeholder="Max" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>

                    <!-- Elevation Range -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Elevation Gain (m)</label>
                        <div class="flex gap-2">
                            <input type="number" x-model.number="filters.minElevation" placeholder="Min" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <input type="number" x-model.number="filters.maxElevation" placeholder="Max" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Features</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="feature in availableFeatures" :key="feature.value">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" :value="feature.value" x-model="filters.features" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    <span class="text-sm text-gray-700" x-text="feature.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select x-model="filters.sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="distance_asc">Distance: Short to Long</option>
                            <option value="distance_desc">Distance: Long to Short</option>
                            <option value="elevation_asc">Elevation: Low to High</option>
                            <option value="elevation_desc">Elevation: High to Low</option>
                            <option value="rating_desc">Highest Rated</option>
                            <option value="popular">Most Popular</option>
                        </select>
                    </div>

                    <!-- Clear Filters -->
                    <button @click="clearFilters" x-show="hasActiveFilters()" class="w-full text-sm text-primary hover:underline font-medium">
                        Clear all filters
                    </button>
                </div>
            </aside>

            <!-- Routes List -->
            <div class="lg:col-span-3">
                @if($routes->isEmpty())
                    <div class="text-center py-16">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No routes found</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new route.</p>
                        @auth
                            <a href="{{ route('routes.create') }}" class="mt-4 inline-block bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-hover transition-colors">Create Route</a>
                        @endauth
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="routes-list">
                        @foreach($routes as $route)
                            <article class="route-card group">
                                <div class="aspect-video bg-gray-100 relative overflow-hidden">
                                    @if($route->geometry)
                                        <div class="absolute inset-0" x-data="{ route: @json($route) }" x-init="
                                            const map = L.map(this, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, touchZoom: false }).setView([{{ $route->geometry['coordinates'][0][1] }}, {{ $route->geometry['coordinates'][0][0] }}], 12);
                                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
                                            L.geoJSON(route.geometry, { style: { color: '{{ $route->difficultyColor }}', weight: 3, opacity: 0.8 } }).addTo(map);
                                            map.fitBounds(L.geoJSON(route.geometry).getBounds(), { padding: [10, 10] });
                                        "></div>
                                    @else
                                        <div class="absolute inset-0 flex items-center justify-center text-gray-400">
                                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="absolute top-2 right-2">
                                        <span class="difficulty-badge {{ $route->difficulty }}">{{ ucfirst($route->difficulty ?? 'unknown') }}</span>
                                    </div>
                                    @if($route->avg_rating > 0)
                                        <div class="absolute bottom-2 left-2 bg-white/90 backdrop-blur-sm rounded-full px-2 py-1 flex items-center gap-1">
                                            <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            <span class="text-sm font-medium text-gray-900">{{ number_format($route->avg_rating, 1) }}</span>
                                            <span class="text-xs text-gray-500">({{ $route->rating_count }})</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-900 mb-1 line-clamp-1">{{ $route->name }}</h3>
                                    <p class="text-sm text-gray-500 mb-3 line-clamp-2">{{ $route->description }}</p>

                                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 mb-3">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                                            {{ $route->distance_km }} km
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                            {{ $route->elevation_gain_m }}m
                                        </span>
                                        @if($route->estimated_time_min)
                                            <span class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ $route->estimated_time_min }} min
                                            </span>
                                        @endif
                                    </div>

                                    @if($route->features->count())
                                        <div class="flex flex-wrap gap-1.5 mb-3">
                                            @foreach($route->features as $feature)
                                                <span class="feature-badge {{ $feature->feature_type }}">
                                                    {{ $feature->icon }} {{ $feature->label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                        <a href="{{ route('routes.show', $route) }}" class="text-sm text-primary hover:underline font-medium">View Details</a>
                                        <span class="text-xs text-gray-500">by {{ $route->user->name }}</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8 flex justify-center">
                        {{ $routes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>