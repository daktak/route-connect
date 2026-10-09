<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-gray-900 truncate">{{ $user->name }}</h1>
                    @if($user->isAdmin())
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">Admin</span>
                    @endif
                </div>
                <p class="text-gray-600 mt-1 truncate">{{ $user->email }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('admin.users.edit', $user) }}" class="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-hover transition-colors">Edit</a>
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach([
                'Routes' => $user->routes_count,
                'Rides organized' => $user->organized_rides_count,
                'Rides joined' => $user->attended_rides_count,
                'Comments' => $user->comments_count,
                'Ratings' => $user->ratings_count,
            ] as $label => $count)
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
                    <div class="text-2xl font-bold text-gray-900">{{ $count }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Account details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                    <dd class="mt-1 text-gray-900">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="mt-1 text-gray-900">{{ $user->email }}
                        @if($user->email_verified_at)
                            <span class="ml-2 text-xs text-green-700">verified</span>
                        @else
                            <span class="ml-2 text-xs text-amber-700">unverified</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Joined</dt>
                    <dd class="mt-1 text-gray-900">{{ $user->created_at?->format('l, M j, Y g:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Last updated</dt>
                    <dd class="mt-1 text-gray-900">{{ $user->updated_at?->format('l, M j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>

        @can('delete', $user)
            <div class="bg-white rounded-lg shadow-sm border border-red-100 p-6">
                <h2 class="text-lg font-semibold text-red-700 mb-2">Delete user</h2>
                <p class="text-sm text-gray-600 mb-4">This permanently deletes the account along with their routes, comments, ratings and ride attendance. Rides they organized are kept but left without an organizer.</p>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete {{ $user->name }}? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors">Delete user</button>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>
