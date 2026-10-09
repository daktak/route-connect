<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add New Route</h1>
            <p class="text-gray-600 mt-1">Upload a GPX file to create a route</p>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST" action="{{ route('routes.store') }}" enctype="multipart/form-data" x-data="gpxUpload" class="space-y-8">
            @csrf

            <!-- GPX Upload -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">1. Upload GPX File</h3>

                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary transition-colors cursor-pointer"
                     @dragover.prevent @drop.prevent="handleFile($event)"
                     @click="$refs.fileInput.click()">
                    <input type="file" id="gpx_file" name="gpx_file" accept=".gpx" @change="handleFile($event)" class="hidden" x-ref="fileInput">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="mt-2 text-gray-600" x-show="!file">Drag & drop a GPX file here, or click to select</p>
                    <p class="mt-2 text-gray-600 font-medium" x-show="file" x-cloak x-text="file ? file.name : ''"></p>
                    <p class="text-sm text-gray-400 mt-1">Supports .gpx files up to 10MB</p>
                </div>

                @error('gpx_file')
                    <p class="mt-3 text-red-600 text-sm">{{ $message }}</p>
                @enderror

                <div x-show="error" x-cloak class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm" x-text="error"></div>

                <div x-show="parsing" x-cloak class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Parsing GPX file...
                </div>
            </div>

            <!-- GPX Preview -->
            <div x-show="preview" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">2. Review & Edit</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Route Name *</label>
                        <input type="text" name="name" x-model="preview.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                        <select name="difficulty" x-model="preview.difficulty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="easy">Easy</option>
                            <option value="moderate">Moderate</option>
                            <option value="hard">Hard</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" x-model="preview.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Time (minutes)</label>
                        <input type="number" name="estimated_time_min" x-model="preview.estimated_time_min" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Public Route</label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_public" value="1" checked class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-700">Visible to all users</span>
                        </label>
                    </div>
                </div>

                <!-- Stats Preview -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-gray-900" x-text="preview.distance_km + ' km'"></div>
                        <div class="text-sm text-gray-500">Distance</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900" x-text="preview.elevation_gain_m + ' m'"></div>
                        <div class="text-sm text-gray-500">Elevation Gain</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900" x-text="preview.estimated_time_min + ' min'"></div>
                        <div class="text-sm text-gray-500">Est. Time</div>
                    </div>
                </div>

                <!-- Map Preview -->
                <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden mb-6"
                     x-data="routePreviewMap"
                     :geometry="preview ? preview.geometry : null"></div>

                <!-- Features -->
                <div class="border-t border-gray-100 pt-6">
                    <h4 class="font-medium text-gray-900 mb-3">Route Features (optional)</h4>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach(\App\Models\RouteFeature::FEATURE_TYPES as $type => $label)
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="features[]" value="{{ $type }}" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                        <template x-for="custom in customFeatures" :key="custom">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="features[]" :value="'custom:' + custom" checked class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-700" x-text="custom"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <input type="text" x-model="customFeature" @keydown.enter.prevent="addCustomFeature()" placeholder="Custom feature..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <button type="button" @click="addCustomFeature()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Add</button>
                    </div>
                </div>

                <button type="button" @click="clear()" class="mt-4 text-sm text-gray-500 hover:underline">Upload different file</button>
            </div>

            <!-- Submit -->
            <div x-show="preview" x-cloak class="flex justify-end gap-4">
                <a href="{{ route('routes.index') }}" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-hover transition-colors">Create Route</button>
            </div>
        </form>
    </div>
</x-app-layout>
