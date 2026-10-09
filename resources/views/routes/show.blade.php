<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $route->name }}</h1>
                <p class="text-gray-600 mt-1">by <a href="#" class="font-medium hover:underline">{{ $route->user->name }}</a> • {{ $route->created_at->diffForHumans() }}</p>
            </div>
            @auth
                @can('update', $route)
                    <a href="{{ route('routes.edit', $route) }}" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">Edit</a>
                @endcan
            @endauth
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Map & Elevation -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                    <div class="aspect-video relative">
                        <div id="route-map" class="absolute inset-0" x-data="singleRouteMap({ geometry: {{ Js::from($geometry) }}, difficulty: {{ Js::from($route->difficulty) }}, features: {{ Js::from($featuresData) }} })"></div>
                    </div>
                </div>

                <!-- Elevation Profile -->
                @if($route->gpx_data['tracks'][0]['segments'][0] ?? false)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Elevation Profile</h3>
                        <div class="elevation-chart" x-data="elevationChart({ profile: {{ Js::from($elevationProfile) }} })"><canvas></canvas></div>
                        <div class="mt-4 grid grid-cols-3 gap-4 text-sm text-gray-600">
                            <div class="text-center">
                                <div class="font-semibold text-gray-900">{{ $route->distance_km }} km</div>
                                <div>Distance</div>
                            </div>
                            <div class="text-center">
                                <div class="font-semibold text-gray-900">{{ $route->elevation_gain_m }} m</div>
                                <div>Elevation Gain</div>
                            </div>
                            <div class="text-center">
                                <div class="font-semibold text-gray-900">{{ $route->estimated_time_min ?? '—' }} min</div>
                                <div>Est. Time</div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Description -->
                @if($route->description)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
                        <div class="prose prose-sm max-w-none text-gray-700">{{ $route->description }}</div>
                    </div>
                @endif

                <!-- Features -->
                @if($route->features->count())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Route Features</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($route->features as $feature)
                                <span class="feature-badge {{ $feature->feature_type }}">{{ $feature->icon }} {{ $feature->label }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- GPX Download -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Download GPX</h3>
                            <p class="text-gray-500 text-sm">Use with your bike computer or phone</p>
                        </div>
                        <a href="{{ route('routes.download', $route) }}" class="bg-primary text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-hover transition-colors flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download GPX
                        </a>
                    </div>
                </div>

                <!-- Ratings & Comments -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Ratings & Reviews</h3>
                        <div class="flex items-center gap-4">
                            <div class="text-3xl font-bold text-gray-900">{{ number_format($route->avg_rating, 1) }}</div>
                            <div class="rating-stars">
                                @for($i = 1; $i <= 5; $i++)
                                    <span class="rating-star {{ $i <= round($route->avg_rating) ? 'filled' : 'empty' }}">★</span>
                                @endfor
                            </div>
                            <span class="text-gray-500">({{ $route->rating_count }} reviews)</span>
                        </div>
                    </div>

                    <!-- Rating Breakdown -->
                    @if($route->avgRatingModel)
                        <div class="px-6 py-4 border-b border-gray-100">
                            @for($i = 5; $i >= 1; $i--)
                                <div class="flex items-center gap-3 mb-1">
                                    <span class="text-sm text-gray-600 w-6">{{ $i }}★</span>
                                    <div class="flex-1 h-2 bg-gray-100 rounded overflow-hidden">
                                        <div class="bg-yellow-400 h-full rounded" style="width: {{ $route->rating_count ? ($route->avgRatingModel->{'stars_' . $i} / $route->rating_count * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-500 w-10 text-right">{{ $route->avgRatingModel->{'stars_' . $i} }}</span>
                                </div>
                            @endfor
                        </div>
                    @endif

                    <!-- User Rating -->
                    @auth
                        @php
                            $userRating = $route->ratings()->where('user_id', auth()->id())->first();
                        @endphp
                        <div class="p-6 border-b border-gray-100">
                            <h4 class="font-medium text-gray-900 mb-3">{{ $userRating ? 'Your Rating' : 'Rate this Route' }}</h4>
                            <div x-data="routeRating({{ $userRating->rating ?? 0 }}, '{{ route('routes.rate', $route) }}')" class="flex items-center gap-4">
                                <template x-for="i in 5" :key="i">
                                    <button type="button" @click="rating = i" :class="i <= rating ? 'filled' : 'empty'" class="rating-star text-3xl cursor-pointer transition-colors" :disabled="loading">★</button>
                                </template>
                                <button type="button" @click="submit()" :disabled="loading || rating === 0" class="ml-4 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!loading">{{ $userRating ? 'Update' : 'Submit' }}</span>
                                    <span x-show="loading" x-cloak>Saving...</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="p-6 border-b border-gray-100 text-center">
                            <a href="{{ route('login') }}" class="text-primary hover:underline">Log in</a> to rate this route.
                        </div>
                    @endauth

                    <!-- Comments -->
                    <div class="p-6">
                        <h4 class="font-semibold text-gray-900 mb-4">Comments ({{ $route->comments()->count() }})</h4>

                        @auth
                            <form @submit.prevent="submit()" x-data="routeComments('{{ route('routes.comments.store', $route) }}')" class="mb-6">
                                <textarea name="content" x-model="content" rows="3" placeholder="Write a comment..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required></textarea>
                                <div class="mt-2 flex justify-end">
                                    <button type="submit" :disabled="posting" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-show="!posting">Post Comment</span>
                                        <span x-show="posting" x-cloak>Posting...</span>
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-center text-gray-500 py-4"><a href="{{ route('login') }}" class="text-primary hover:underline">Log in</a> to comment.</p>
                        @endauth

                        <div id="comments-list" class="space-y-6">
                            @foreach($route->comments()->with('user', 'replies.user')->get() as $comment)
                                @include('routes.partials.comment', ['comment' => $comment])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-1 space-y-6">
                <!-- Route Stats -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Route Stats</h3>
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Distance</dt>
                            <dd class="font-semibold text-gray-900">{{ $route->distance_km }} km</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Elevation Gain</dt>
                            <dd class="font-semibold text-gray-900">{{ $route->elevation_gain_m }} m</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Estimated Time</dt>
                            <dd class="font-semibold text-gray-900">{{ $route->estimated_time_min ? $route->estimated_time_min . ' min' : '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Difficulty</dt>
                            <dd><span class="difficulty-badge {{ $route->difficulty }}">{{ ucfirst($route->difficulty) }}</span></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Avg Rating</dt>
                            <dd class="flex items-center gap-2">
                                @if($route->avg_rating > 0)
                                    <span class="font-semibold">{{ number_format($route->avg_rating, 1) }}</span>
                                    <div class="rating-stars">
                                        @for($i = 1; $i <= 5; $i++)
                                            <span class="rating-star {{ $i <= round($route->avg_rating) ? 'filled' : 'empty' }}">★</span>
                                        @endfor
                                    </div>
                                    <span class="text-sm text-gray-500">({{ $route->rating_count }})</span>
                                @else
                                    <span class="text-gray-500">No ratings yet</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Upcoming Rides -->
                @if($route->upcomingRides->count())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Upcoming Group Rides</h3>
                        <div class="space-y-3">
                            @foreach($route->upcomingRides as $ride)
                                <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-medium text-gray-900">{{ $ride->title ?? 'Group Ride' }}</span>
                                        <span class="ride-status {{ $ride->status }}">{{ ucfirst($ride->status) }}</span>
                                    </div>
                                    <div class="text-sm text-gray-600">{{ $ride->ride_date->format('l, M j, Y \a\t g:i A') }}</div>
                                    @if($ride->meeting_point_name)
                                        <div class="text-sm text-gray-500 mt-1">{{ $ride->meeting_point_name }}</div>
                                    @endif
                                    <a href="{{ route('rides.show', $ride) }}" class="text-sm text-primary hover:underline mt-2 inline-block">View Ride</a>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('rides.index') }}" class="text-sm text-primary hover:underline block mt-3 text-center">View All Rides</a>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <h4 class="mt-2 font-medium text-gray-900">No upcoming rides</h4>
                        <p class="text-gray-500 text-sm mt-1">Be the first to organize a group ride on this route!</p>
                        @auth
                            <a href="{{ route('rides.create', ['route' => $route->id]) }}" class="mt-4 inline-block bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-hover transition-colors">Create Ride</a>
                        @endauth
                    </div>
                @endif

                <!-- Author Info -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xl">
                            {{ strtoupper($route->user->name[0]) }}
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">{{ $route->user->name }}</h4>
                            <p class="text-sm text-gray-500">{{ $route->user->routes()->count() }} routes shared</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>