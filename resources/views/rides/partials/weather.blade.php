@php
    $variant = $variant ?? 'compact';
    $location = $location ?? null;
    $airQuality = $airQuality ?? null;
    $tz = $tz ?? config('app.default_timezone');
    $temp = $weather['temperature'] !== null ? round($weather['temperature']) . '°' : '—';
    $rain = $weather['precip_probability'] !== null ? round($weather['precip_probability']) . '%' : '—';
    $wind = $weather['wind_speed'] !== null ? round($weather['wind_speed']) . ' km/h' : '—';
    $forecastTime = $weather['time']->timezone($tz)->format('M j, g:i A');
    $uv = $weather['uv_index'] ?? null;
    $uvLevel = $weather['uv_level'] ?? null;
    $uvClass = match ($uvLevel) {
        'Moderate' => 'bg-yellow-100 text-yellow-800',
        'High' => 'bg-orange-100 text-orange-800',
        'Very high' => 'bg-red-100 text-red-800',
        'Extreme' => 'bg-purple-100 text-purple-800',
        default => 'bg-green-100 text-green-800',
    };
    $aqiLevel = $airQuality['level'] ?? null;
    $aqiScale = ($airQuality['scale'] ?? 'us') === 'us' ? 'US' : 'European';
    $aqiClass = match ($aqiLevel) {
        'Fair', 'Moderate' => 'bg-yellow-100 text-yellow-800',
        'Poor', 'Unhealthy for sensitive groups' => 'bg-orange-100 text-orange-800',
        'Very poor', 'Unhealthy' => 'bg-red-100 text-red-800',
        'Extremely poor', 'Very unhealthy', 'Hazardous' => 'bg-purple-100 text-purple-800',
        default => 'bg-green-100 text-green-800',
    };
    $tooltip = 'Forecast' . ($location ? ' for ' . $location : '') . ' at ' . $forecastTime . ' · Weather data by Open-Meteo.com';
@endphp

@if($variant === 'detail')
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Weather at Start</h3>
        <div class="flex items-center gap-4">
            <span class="text-5xl leading-none" aria-hidden="true">{{ $weather['icon'] }}</span>
            <div>
                <p class="text-3xl font-bold text-gray-900">{{ $temp }}C</p>
                <p class="text-gray-600">{{ $weather['condition'] }}</p>
            </div>
        </div>
        <dl class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Feels like</dt>
                <dd class="font-medium text-gray-900">{{ $weather['apparent'] !== null ? round($weather['apparent']) . '°C' : '—' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Chance of rain</dt>
                <dd class="font-medium text-gray-900">{{ $rain }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Wind</dt>
                <dd class="font-medium text-gray-900">
                    {{ $wind }}@if($weather['wind_gusts'] !== null) <span class="text-gray-500 font-normal">(gusts {{ round($weather['wind_gusts']) }} km/h)</span>@endif
                </dd>
            </div>
            @if($uv !== null)
                <div class="flex justify-between">
                    <dt class="text-gray-500">UV index</dt>
                    <dd class="font-medium text-gray-900">
                        {{ round($uv) }} <span class="ml-1 px-1.5 py-0.5 rounded text-xs {{ $uvClass }}">{{ $uvLevel }}</span>
                    </dd>
                </div>
            @endif
            @if($airQuality)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Air quality</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $airQuality['aqi'] }} <span class="ml-1 px-1.5 py-0.5 rounded text-xs {{ $aqiClass }}" title="{{ $aqiScale }} AQI">{{ $aqiLevel }}</span>
                    </dd>
                </div>
            @endif
        </dl>
        <p class="mt-4 text-xs text-gray-400" title="{{ $tooltip }}">Forecast for {{ $forecastTime }}@if($location) · {{ $location }}@endif</p>
        <p class="mt-1 text-xs text-gray-400">Weather data by <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" class="hover:underline">Open-Meteo</a>@if($airQuality) · Air quality (CAMS)@endif</p>
    </div>
@else
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 mb-4" title="{{ $tooltip }}">
        <span class="flex items-center gap-1.5">
            <span class="text-lg leading-none" aria-hidden="true">{{ $weather['icon'] }}</span>
            <span class="font-semibold text-gray-900">{{ $temp }}C</span>
        </span>
        <span class="flex items-center gap-1" title="Chance of rain">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v18m0 0a6 6 0 01-6-6c0-3 6-12 6-12s6 9 6 12a6 6 0 01-6 6z"/></svg>
            {{ $rain }}
        </span>
        <span class="flex items-center gap-1" title="Wind speed">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h11a3 3 0 100-6M3 12h15a3 3 0 110 6M3 16h8"/></svg>
            {{ $wind }}
        </span>
        @if($uv !== null)
            <span class="flex items-center gap-1" title="UV index: {{ $uvLevel }}">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                {{ round($uv) }}
            </span>
        @endif
        @if($airQuality)
            <span class="flex items-center gap-1" title="Air quality ({{ $aqiScale }} AQI): {{ $aqiLevel }}">
                <span class="inline-block w-2 h-2 rounded-full {{ $aqiClass }}" aria-hidden="true"></span>
                AQI {{ $airQuality['aqi'] }}
            </span>
        @endif
        <span class="text-gray-400">{{ $weather['condition'] }}</span>
    </div>
@endif
