<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

@php
    $oauthProviders = collect(['google' => 'Google', 'github' => 'GitHub', 'strava' => 'Strava'])
        ->filter(fn ($label, $p) => config("services.$p.client_id") && config("services.$p.client_secret"));
@endphp
@if ($oauthProviders->isNotEmpty())
    <div class="mt-6">
        <div class="relative">
            <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-gray-300 dark:border-gray-700"></span></div>
            <div class="relative flex justify-center text-sm"><span class="bg-white dark:bg-gray-800 px-2 text-gray-500">Or sign up with</span></div>
        </div>
        <div class="mt-4 flex flex-col gap-2">
            @foreach ($oauthProviders as $provider => $label)
                @if (config("services.$provider.client_id"))
                    <a href="{{ route('oauth.redirect', $provider) }}" class="inline-flex items-center justify-center w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
</x-guest-layout>
