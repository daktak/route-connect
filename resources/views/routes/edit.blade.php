<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Edit Route</h1>
                <p class="text-gray-600 mt-1">{{ $route->name }}</p>
            </div>
            <a href="{{ route('routes.show', $route) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">View Route</a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST" action="{{ route('routes.update', $route) }}" class="space-y-8">
            @csrf
            @method('PUT')

            <!-- Basic Info -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Route Details</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Route Name *</label>
                        <input type="text" name="name" value="{{ old('name', $route->name) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        @error('name')
                            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                        <select name="difficulty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="easy" {{ $route->difficulty === 'easy' ? 'selected' : '' }}>Easy</option>
                            <option value="moderate" {{ $route->difficulty === 'moderate' ? 'selected' : '' }}>Moderate</option>
                            <option value="hard" {{ $route->difficulty === 'hard' ? 'selected' : '' }}>Hard</option>
                            <option value="expert" {{ $route->difficulty === 'expert' ? 'selected' : '' }}>Expert</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">{{ old('description', $route->description) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Time (minutes)</label>
                        <input type="number" name="estimated_time_min" value="{{ old('estimated_time_min', $route->estimated_time_min) }}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_public" value="1" {{ old('is_public', $route->is_public) ? 'checked' : '' }} class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-700">Public Route</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Route Features</h3>

                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach(\App\Models\RouteFeature::FEATURE_TYPES as $type => $label)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="features[]" value="{{ $type }}" {{ $route->features->pluck('feature_type')->contains($type) ? 'checked' : '' }} class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                @if($route->features->where('feature_type', 'custom')->count())
                    <div class="mb-4">
                        <h4 class="font-medium text-gray-900 mb-2">Custom Features</h4>
                        @foreach($route->features->where('feature_type', 'custom') as $feature)
                            <div class="flex items-center gap-2 mb-2">
                                <span class="feature-badge custom">{{ $feature->icon }} {{ $feature->feature_subtype }}</span>
                                <button type="button" onclick="this.parentElement.remove()" class="text-red-600 hover:underline text-sm">Remove</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex gap-2">
                    <input type="text" name="custom_feature" placeholder="Add custom feature..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <button type="button" onclick="addCustomFeature(this)" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Add</button>
                </div>
            </div>

            <!-- Map Preview -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Route Map</h3>
                <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden" x-data="routeMap({ routeGeometry: @json($route->geometry), difficulty: @json($route->difficulty) })" x-init="
                    initMap();
                    if (routeGeometry) {
                        const color = getDifficultyColor(difficulty);
                        L.geoJSON(routeGeometry, { style: { color, weight: 4, opacity: 0.9 } }).addTo(routesLayer);
                        map.fitBounds(routesLayer.getBounds(), { padding: [20, 20] });
                    }
                "></div>
            </div>

            <!-- GPX File Info -->
            @if($route->gpx_file_path)
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">GPX File</h3>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <div>
                                <p class="font-medium text-gray-900">{{ basename($route->gpx_file_path) }}</p>
                                <p class="text-sm text-gray-500">Uploaded {{ $route->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <a href="{{ route('routes.download', $route) }}" class="text-primary hover:underline text-sm font-medium">Download</a>
                    </div>
                </div>
            @endif

            <!-- Delete Route -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-red-900 mb-2">Danger Zone</h3>
                <p class="text-red-700 mb-4">Deleting this route will permanently remove it along with all ratings, comments, and associated group rides.</p>
                <form method="POST" action="{{ route('routes.destroy', $route) }}" onsubmit="return confirm('Are you sure you want to delete this route? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors">Delete Route</button>
                </form>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-4">
                <a href="{{ route('routes.show', $route) }}" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-hover transition-colors">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        function addCustomFeature(btn) {
            const input = btn.previousElementSibling;
            const value = input.value.trim();
            if (!value) return;

            const container = btn.closest('.bg-white').querySelector('.flex.flex-wrap.gap-2');
            const label = document.createElement('label');
            label.className = 'inline-flex items-center gap-2 cursor-pointer';
            label.innerHTML = `<input type="checkbox" name="features[]" value="custom:${value}" checked class="rounded border-gray-300 text-primary focus:ring-primary"><span class="text-sm text-gray-700">${value}</span>`;
            container.appendChild(label);
            input.value = '';
        }
    </script>
</x-app-layout>