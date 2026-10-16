<nav x-data="{ mobileMenuOpen: false }" class="bg-white shadow-sm sticky top-0 z-[1000]" aria-label="Main navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="{{ route('rides.index') }}" class="flex items-center gap-2 text-xl font-semibold text-gray-900">
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
                    <a href="{{ route('routes.create') }}" class="text-sm font-medium {{ request()->routeIs('routes.create') ? 'text-primary' : 'text-gray-700 hover:text-primary' }} transition-colors">
                        Add Route
                    </a>
                    <a href="{{ route('rides.create') }}" class="text-sm font-medium {{ request()->routeIs('rides.create') ? 'text-primary' : 'text-gray-700 hover:text-primary' }} transition-colors">
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

                            @if (config('services.discord.invite_url'))
                                <div class="border-t border-gray-100">
                                    <a href="{{ config('services.discord.invite_url') }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="w-5 h-5 text-indigo-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M20.317 4.369a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.009c.12.099.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.891.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.029 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028ZM8.02 15.331c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.095 2.157 2.42 0 1.333-.955 2.418-2.157 2.418Zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.095 2.157 2.42 0 1.333-.947 2.418-2.157 2.418Z"/>
                                        </svg>
                                        Join our Discord
                                    </a>
                                </div>
                            @endif
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

                        <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-[1000]">
                            <a href="{{ route('profile.edit') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">Profile</a>
                            <a href="{{ route('routes.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">My Routes</a>
                            <a href="{{ route('rides.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">My Rides</a>
                            @if(auth()->user()->isAdmin())
                                <hr class="my-1 border-gray-100">
                                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block">Manage Users</a>
                            @endif
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
                    <a href="{{ route('routes.create') }}" class="px-2 py-2 rounded {{ request()->routeIs('routes.create') ? 'text-primary font-medium bg-primary/10' : 'text-gray-700 hover:bg-gray-50' }}">Add Route</a>
                    <a href="{{ route('rides.create') }}" class="px-2 py-2 rounded {{ request()->routeIs('rides.create') ? 'text-primary font-medium bg-primary/10' : 'text-gray-700 hover:bg-gray-50' }}">Create Ride</a>
                    <hr class="my-2 border-gray-100">
                    <a href="{{ route('profile.edit') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Profile</a>
                    <a href="{{ route('routes.index') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">My Routes</a>
                    <a href="{{ route('rides.index') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">My Rides</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}" class="px-2 py-2 text-gray-700 hover:bg-gray-50 rounded">Manage Users</a>
                    @endif
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