<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Routes Map</h1>
                <p class="text-gray-600 mt-1">Explore routes on the map</p>
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
        <div x-data="routeMap" :routes='@json($mapRoutes)'
             class="relative flex h-[calc(100vh-14rem)] rounded-lg overflow-hidden border border-gray-200"
             style="min-height: 600px;">

            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                   class="absolute md:relative inset-y-0 left-0 z-30 w-80 max-w-[85%] bg-white border-r border-gray-200 transform transition-transform duration-300 md:translate-x-0 flex flex-col">
                <div class="flex flex-col h-full">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Routes</h2>
                        <button @click="sidebarOpen = false" class="md:hidden p-1 text-gray-500 hover:text-gray-700" aria-label="Close filters">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Filters -->
                    <div x-data="routeFilters" class="p-4 overflow-y-auto flex-1">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" x-model="filters.search" placeholder="Search routes..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                            <select x-model="filters.difficulty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All</option>
                                <option value="easy">Easy</option>
                                <option value="moderate">Moderate</option>
                                <option value="hard">Hard</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Features</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="feature in availableFeatures" :key="feature.value">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" :value="feature.value" x-model="filters.features" class="rounded border-gray-300 text-primary focus:ring-primary">
                                        <span class="text-sm text-gray-700" x-text="feature.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select x-model="filters.sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="newest">Newest First</option>
                                <option value="distance_asc">Distance: Short to Long</option>
                                <option value="distance_desc">Distance: Long to Short</option>
                                <option value="elevation_asc">Elevation: Low to High</option>
                                <option value="elevation_desc">Elevation: High to Low</option>
                                <option value="rating_desc">Highest Rated</option>
                            </select>
                        </div>

                        <button @click="clearFilters" x-show="hasActiveFilters()" class="w-full text-sm text-primary hover:underline font-medium">Clear filters</button>
                    </div>

                    <!-- Routes List in Sidebar -->
                    <div class="p-4 border-t border-gray-200 max-h-64 overflow-y-auto">
                        <h3 class="font-medium text-gray-900 mb-3">Routes ({{ $routes->count() }})</h3>
                        <x-map-sidebar :map-routes="$mapRoutes" />
                    </div>
                </div>
            </aside>

            <!-- Map Area -->
            <div class="flex-1 relative">
                <div x-ref="map" x-init="initMap($el)" class="absolute inset-0"></div>

                <!-- Locate Button -->
                <button @click="locateUser()" class="absolute top-4 right-4 z-20 bg-white shadow-lg rounded-lg p-2 text-gray-700 hover:text-primary transition-colors" title="Center on my location" aria-label="Center on my location">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                    </svg>
                </button>

                <!-- Sidebar Toggle (Mobile) -->
                <button @click="sidebarOpen = !sidebarOpen" class="absolute bottom-4 left-4 z-20 bg-white shadow-lg rounded-full p-3 md:hidden" aria-label="Toggle filters">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <!-- Route Detail Panel (when selected) -->
                <div x-show="selectedRoute" x-cloak x-transition
                     class="absolute inset-y-0 right-0 z-20 w-96 max-w-full bg-white shadow-lg border-l border-gray-200 overflow-y-auto">
                    <div class="flex flex-col h-full">
                        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900" x-text="selectedRoute ? selectedRoute.name : ''"></h3>
                            <button @click="selectedRoute = null" class="p-1 text-gray-500 hover:text-gray-700" aria-label="Close details">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="p-4 overflow-y-auto flex-1">
                            <div class="space-y-3">
                                <div class="flex items-center gap-3 text-sm text-gray-600">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                                        <span x-text="selectedRoute ? selectedRoute.distance_km + ' km' : ''"></span>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                        <span x-text="selectedRoute ? selectedRoute.elevation_gain_m + 'm' : ''"></span>
                                    </span>
                                    <span class="difficulty-badge" :class="selectedRoute ? selectedRoute.difficulty : ''" x-text="selectedRoute ? selectedRoute.difficulty : ''"></span>
                                </div>

                                <p class="text-sm text-gray-600" x-show="selectedRoute && selectedRoute.description" x-text="selectedRoute ? selectedRoute.description : ''"></p>

                                <div x-show="selectedRoute && selectedRoute.features && selectedRoute.features.length">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Features</h4>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="feature in (selectedRoute ? selectedRoute.features : [])" :key="feature.id">
                                            <span class="feature-badge" :class="feature.feature_type" x-html="feature.icon + ' ' + feature.label"></span>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="selectedRoute && selectedRoute.avg_rating > 0" class="flex items-center gap-2 text-sm">
                                    <div class="rating-stars">
                                        <template x-for="i in 5" :key="i">
                                            <span class="rating-star" :class="selectedRoute && i <= Math.round(selectedRoute.avg_rating) ? 'filled' : 'empty'">★</span>
                                        </template>
                                    </div>
                                    <span x-text="selectedRoute ? selectedRoute.avg_rating.toFixed(1) : ''"></span>
                                    <span class="text-gray-500" x-text="selectedRoute ? '(' + selectedRoute.rating_count + ' reviews)' : ''"></span>
                                </div>

                                <div class="pt-4 border-t border-gray-100 flex gap-2" x-show="selectedRoute">
                                    <a :href="selectedRoute ? '/routes/' + selectedRoute.id : '#'" class="flex-1 bg-primary text-white text-center py-2 px-4 rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors">View Details</a>
                                    <a x-show="selectedRoute && selectedRoute.upcoming_rides && selectedRoute.upcoming_rides.length" :href="selectedRoute ? '/rides/' + selectedRoute.upcoming_rides[0].id : '#'" class="flex-1 border border-gray-300 text-gray-700 text-center py-2 px-4 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Join Ride</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
