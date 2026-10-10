<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="phone" :value="__('Phone Number')" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div>
            <x-input-label for="emergency_contact_name" :value="__('Emergency Contact Name')" />
            <x-text-input id="emergency_contact_name" name="emergency_contact_name" type="text" class="mt-1 block w-full" :value="old('emergency_contact_name', $user->emergency_contact_name)" autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('emergency_contact_name')" />
        </div>

        <div>
            <x-input-label for="emergency_contact_phone" :value="__('Emergency Contact Phone')" />
            <x-text-input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" class="mt-1 block w-full" :value="old('emergency_contact_phone', $user->emergency_contact_phone)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('emergency_contact_phone')" />
        </div>

        <div>
            <x-input-label for="email_notifications" :value="__('Email Notifications')" />
            <div class="mt-1 flex items-center gap-2">
                <input type="checkbox" name="email_notifications" id="email_notifications" class="rounded border-gray-300 text-primary focus:ring-primary" {{ $user->email_notifications ? 'checked' : '' }}>
                <label for="email_notifications" class="text-sm text-gray-700">{{ __('Receive email notifications for ride updates, comments, and ratings') }}</label>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('email_notifications')" />
        </div>

        <div>
            <x-input-label for="telegram_chat_id" :value="__('Telegram Chat ID')" />
            <x-text-input id="telegram_chat_id" name="telegram_chat_id" type="text" class="mt-1 block w-full" :value="old('telegram_chat_id', $user->telegram_chat_id)" placeholder="Enter your Telegram chat ID" autocomplete="off" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Find your chat ID by messaging @userinfobot on Telegram') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('telegram_chat_id')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
