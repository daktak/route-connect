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
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Route</h3>

                <div class="relative">
                    <select name="route_id" id="route_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent appearance-none bg-white">
                        <option value="">Select a route...</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}" {{ (int) old('route_id', $ride->route_id) === $route->id ? 'selected' : '' }}>
                                {{ $route->name }} ({{ $route->distance_km }} km, {{ $route->elevation_gain_m }}m)
                            </option>
                        @endforeach
                    </select>
                </div>
                @error('route_id')
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
                        <input type="datetime-local" name="ride_date" value="{{ old('ride_date', $ride->ride_date->format('Y-m-d\TH:i')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
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
