@php
    $variant = $variant ?? 'compact';
    $location = $location ?? null;
    $temp = $weather['temperature'] !== null ? round($weather['temperature']) . '°' : '—';
    $rain = $weather['precip_probability'] !== null ? round($weather['precip_probability']) . '%' : '—';
    $wind = $weather['wind_speed'] !== null ? round($weather['wind_speed']) . ' km/h' : '—';
    $forecastTime = $weather['time']->format('M j, g:i A') . ' UTC';
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
        </dl>
        <p class="mt-4 text-xs text-gray-400" title="{{ $tooltip }}">Forecast for {{ $forecastTime }}@if($location) · {{ $location }}@endif</p>
        <p class="mt-1 text-xs text-gray-400">Weather data by <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" class="hover:underline">Open-Meteo</a></p>
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
        <span class="text-gray-400">{{ $weather['condition'] }}</span>
    </div>
@endif
