<nav x-data="{ mobileMenuOpen: false }" class="bg-white shadow-sm sticky top-0 z-40" aria-label="Main navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="{{ route('routes.index') }}" class="flex items-center gap-2 text-xl font-semibold text-gray-900">
                    <svg class="w-8 h-8 text-primary" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <span>{{ config('app.name') }}</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex md:items-center md:gap-8">
                <a href="{{ route('routes.index') }}" class="text-sm font-medium text-gray-700 hover:text-primary transition-colors">
                    Routes
                </a>
                <a href="{{ route('routes.map') }}" class="text-sm font-medium text-gray-700 hover:text-primary transition-colors">
                    Map
                </a>
                <a href="{{ route('rides.index') }}" class="text-sm font-medium text-gray-700 hover:text-primary transition-colors">
                    Group Rides
                </a>

                @auth
                    <a href="{{ route('routes.create') }}" class="text-sm font-medium text-primary hover:text-primary-hover transition-colors">
                        Add Route
                    </a>
                    <a href="{{ route('rides.create') }}" class="text-sm font-medium text-gray-700 hover:text-primary transition-colors">
                        Create Ride
                    </a>
                @endauth
            </div>

            <!-- Right Side Actions -->
            <div class="flex items-center gap-4">
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-primary transition-colors hidden sm:block">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" class="bg-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-primary-hover transition-colors hidden sm:block">
                        Sign Up
                    </a>
                @else
                    <!-- Notification Bell -->
                    <div x-data="notificationBell" class="relative">
                        <button @click="open = !open" @click.outside="open = false" class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors" aria-label="Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span x-show="unreadCount > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-medium rounded-full flex items-center justify-center">
                                <span x-text="unreadCount"></span>
                            </span>
                        </button>

                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="notification-dropdown">
                            <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                                <h3 class="font-semibold text-gray-900">Notifications</h3>
                                <button @click="markAllAsRead" class="text-xs text-primary hover:underline" x-show="unreadCount > 0">Mark all read</button>
                            </div>

                            <template x-for="notification in notifications" :key="notification.id">
                                <div @click="markAsRead(notification.id)" class="notification-item" :class="{ unread: !notification.read_at }">
                                    <p class="text-sm text-gray-900" x-html="notification.data.message"></p>
                                    <p class="text-xs text-gray-500 mt-1" x-text="formatTime(notification.created_at)"></p>
                                </div>
                            </template>

                            <div x-show="notifications.length === 0" class="px-4 py-4 text-center text-gray-500 text-sm">
                                No notifications yet
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                            <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white font-medium">
                                {{ strtoupper(Auth::user()->name[0]) }}
                            </div>
                            <span class="hidden sm:block font-medium text-sm">{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-50">
                            <a href="{{ route('profile.edit') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">Profile</a>
                            <a href="{{ route('routes.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">My Routes</a>
                            <a href="{{ route('rides.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">My Rides</a>
                            <hr class="my-1 border-gray-100">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                            </form>
                        </div>
                    </div>
                @endguest

                <!-- Mobile Menu Button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{ 'hidden': mobileMenuOpen }">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="mobileMenuOpen">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden py-4 border-t border-gray-100">
            <div class="flex flex-col gap-2">
                <a href="{{ route('routes.index') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Routes</a>
                <a href="{{ route('routes.map') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Map</a>
                <a href="{{ route('rides.index') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Group Rides</a>
                @auth
                    <a href="{{ route('routes.create') }}" class="px-2 py-2 text-primary hover:bg-primary/10 rounded font-medium">Add Route</a>
                    <a href="{{ route('rides.create') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Create Ride</a>
                    <hr class="my-2 border-gray-100">
                    <a href="{{ route('profile.edit') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Profile</a>
                    <a href="{{ route('routes.index') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">My Routes</a>
                    <form method="POST" action="{{ route('logout') }}" class="px-2 py-2">
                        @csrf
                        <button type="submit" class="w-full text-left text-gray-700 hover:bg-gray-50 rounded">Log Out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Log in</a>
                    <a href="{{ route('register') }}" class="px-2 py-2 bg-primary text-white text-center rounded font-medium">Sign Up</a>
                @endauth
            </div>
        </div>
    </div>
</nav>