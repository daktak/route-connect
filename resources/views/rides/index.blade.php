<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Group Rides</h1>
                <p class="text-gray-600 mt-1">Find or organize group rides</p>
            </div>
            @auth
                <a href="{{ route('rides.create') }}" class="bg-primary text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-hover transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Ride
                </a>
            @endauth
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Filters -->
            <aside class="lg:col-span-1">
                <form method="GET" action="{{ route('rides.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="upcoming" {{ $status === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                            <option value="past" {{ $status === 'past' ? 'selected' : '' }}>Past</option>
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <div class="space-y-2">
                            <input type="date" name="from" value="{{ $from }}" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="From">
                            <input type="date" name="to" value="{{ $to }}" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="To">
                        </div>
                    </div>

                    @if($status !== 'upcoming' || $from || $to)
                        <a href="{{ route('rides.index') }}" class="text-sm text-primary hover:underline">Clear filters</a>
                    @endif
                </form>
            </aside>

            <!-- Rides List -->
            <div class="lg:col-span-2 space-y-6">
                @if($rides->isEmpty())
                    <div class="text-center py-16">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">
                            {{ $status === 'past' ? 'No past rides found' : 'No group rides found' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or create the first group ride!</p>
                        @auth
                            <a href="{{ route('rides.create') }}" class="mt-4 inline-block bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-hover transition-colors">Create Ride</a>
                        @endauth
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($rides as $ride)
                            @include('rides.partials.ride-card', ['ride' => $ride])
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $rides->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
