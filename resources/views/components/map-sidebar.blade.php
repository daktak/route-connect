<div class="space-y-2">
    <template x-for="route in routes" :key="route.id">
        <button @click="window.location.href = '/routes/' + route.id" class="w-full text-left p-3 rounded-lg hover:bg-gray-50 border border-transparent transition-colors" :class="{ 'bg-primary/10 border-primary': selectedRoute && selectedRoute.id === route.id }">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full flex-shrink-0" :style="'background-color: ' + getDifficultyColor(route.difficulty)"></span>
                <span class="flex-1 text-sm font-medium text-gray-900 truncate" x-text="route.name"></span>
            </div>
            <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                <span x-text="route.distance_km + ' km'"></span>
                <span x-text="route.elevation_gain_m + 'm'"></span>
                <span x-show="route.avg_rating" class="flex items-center gap-1 text-yellow-600">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364 1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <span x-text="route.avg_rating ? route.avg_rating.toFixed(1) : ''"></span>
                </span>
            </div>
        </button>
    </template>

    <p x-show="!routes || !routes.length" class="text-sm text-gray-500 py-4 text-center">No routes found</p>
</div>
