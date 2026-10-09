import Alpine from 'alpinejs';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

window.Alpine = Alpine;
window.L = L;

// Fix Leaflet marker icon paths
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

// Alpine Components

// Route filter sidebar
Alpine.data('routeFilters', () => ({
    filters: {
        search: '',
        difficulty: '',
        minDistance: '',
        maxDistance: '',
        minElevation: '',
        maxElevation: '',
        features: [],
        sort: 'newest',
    },
    availableFeatures: [
        { value: 'gravel', label: 'Gravel' },
        { value: 'steep', label: 'Steep Climbs' },
        { value: 'technical', label: 'Technical' },
        { value: 'scenic', label: 'Scenic' },
        { value: 'road', label: 'Road' },
        { value: 'water', label: 'Water Crossings' },
        { value: 'cafe', label: 'Cafe Stops' },
        { value: 'shop', label: 'Bike Shop' },
    ],

    init() {
        // Load filters from URL
        const params = new URLSearchParams(window.location.search);
        Object.keys(this.filters).forEach(key => {
            const value = params.get(key);
            if (value) {
                this.filters[key] = key === 'features' ? value.split(',') : value;
            }
        });

        // All features ticked by default (== no feature filter)
        if (this.filters.features.length === 0) {
            this.filters.features = this.availableFeatures.map(f => f.value);
        }

        this.$watch('filters', () => this.applyFilters(), { deep: true });
    },

    applyFilters() {
        const params = new URLSearchParams();
        const allFeatures = this.availableFeatures.map(f => f.value);

        Object.entries(this.filters).forEach(([key, value]) => {
            if (key === 'features') {
                if (Array.isArray(value) && value.length > 0 && value.length < allFeatures.length) {
                    params.set(key, value.join(','));
                }
                return;
            }
            if (value !== '' && value !== null && value !== undefined) {
                if (key === 'sort' && value === 'newest') {
                    return;
                }
                params.set(key, value);
            }
        });

        const qs = params.toString();
        const newUrl = `${window.location.pathname}${qs ? '?' + qs : ''}`;
        const currentUrl = window.location.pathname + window.location.search;

        if (newUrl !== currentUrl) {
            window.location.href = newUrl;
        }
    },

    clearFilters() {
        Object.keys(this.filters).forEach(key => {
            this.filters[key] = Array.isArray(this.filters[key])
                ? this.availableFeatures.map(f => f.value)
                : (key === 'sort' ? 'newest' : '');
        });
    },

    hasActiveFilters() {
        const otherActive = Object.entries(this.filters)
            .filter(([key, value]) => key !== 'features')
            .some(([, value]) => value !== '');

        const featureFilterActive = this.filters.features.length > 0
            && this.filters.features.length < this.availableFeatures.length;

        return otherActive || featureFilterActive;
    },

    toggleFeature(feature) {
        const idx = this.filters.features.indexOf(feature);
        if (idx > -1) {
            this.filters.features.splice(idx, 1);
        } else {
            this.filters.features.push(feature);
        }
    },
}));

// Leaflet Map Component
Alpine.data('routeMap', (config = {}) => ({
    map: null,
    routesLayer: null,
    featuresLayer: null,
    selectedRoute: null,
    sidebarOpen: false,
    userMarker: null,
    userLocated: false,
    routes: config.routes ?? [],
    selectedRouteId: config.selectedRouteId ?? null,
    routeGeometry: config.routeGeometry ?? null,
    difficulty: config.difficulty ?? 'moderate',
    meetingPoint: config.meetingPoint ?? null,

    init() {
        // Map initialization happens on the map element via x-init="initMap($el)".
    },

    initMap(el = null) {
        const container = el || this.$refs.map || this.$el;

        this.map = L.map(container, {
            zoomControl: true,
            scrollWheelZoom: true,
        }).setView([47.0, 8.0], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(this.map);

        this.routesLayer = L.featureGroup().addTo(this.map);
        this.featuresLayer = L.layerGroup().addTo(this.map);

        this.$watch('routes', () => this.renderRoutes());
        this.$watch('selectedRouteId', () => this.highlightSelectedRoute());

        this.renderRoutes();
        this.renderRouteGeometry();
        this.renderMeetingPoint();
        this.locateUser();
    },

    locateUser() {
        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude } = position.coords;
                this.userLocated = true;

                if (this.userMarker) {
                    this.map.removeLayer(this.userMarker);
                }

                this.userMarker = L.circleMarker([latitude, longitude], {
                    radius: 8,
                    color: '#1d4ed8',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.9,
                    weight: 2,
                }).addTo(this.map).bindPopup('You are here');

                this.map.setView([latitude, longitude], 11);
            },
            () => {
                // Permission denied or unavailable: keep the default view.
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    },

    renderRoutes() {
        this.routesLayer.clearLayers();

        if (!this.routes || !this.routes.length) return;

        this.routes.forEach(route => {
            if (!route.geometry) return;

            const color = this.getDifficultyColor(route.difficulty);
            const geojson = L.geoJSON(route.geometry, {
                style: {
                    color: color,
                    weight: 3,
                    opacity: 0.8,
                },
                onEachFeature: (feature, layer) => {
                    layer.on('click', () => {
                        this.selectRoute(route);
                    });
                    layer.bindPopup(this.createRoutePopup(route));
                },
            });
            geojson.addTo(this.routesLayer);
        });

        if (!this.userLocated) {
            this.fitBounds();
        }
    },

    createRoutePopup(route) {
        return `
            <div class="p-2 min-w-[200px]">
                <h3 class="font-semibold text-gray-900">${route.name}</h3>
                <div class="text-sm text-gray-600 mt-1 space-y-1">
                    <div>${route.distance_km} km • ${route.elevation_gain_m}m ↑</div>
                    <div class="capitalize">${route.difficulty}</div>
                    ${route.avg_rating ? `<div>⭐ ${route.avg_rating} (${route.rating_count})</div>` : ''}
                </div>
                <a href="/routes/${route.id}" class="block mt-2 text-sm text-primary hover:underline">View Details</a>
            </div>
        `;
    },

    getDifficultyColor(difficulty) {
        const colors = {
            easy: '#16a34a',
            moderate: '#f59e0b',
            hard: '#ea580c',
            expert: '#dc2626',
        };
        return colors[difficulty] || '#2563eb';
    },

    fitBounds() {
        if (this.routesLayer.getLayers().length > 0) {
            this.map.fitBounds(this.routesLayer.getBounds(), { padding: [20, 20] });
        }
    },

    renderRouteGeometry() {
        if (!this.routeGeometry) return;

        const color = this.getDifficultyColor(this.difficulty);
        L.geoJSON(this.routeGeometry, { style: { color, weight: 4, opacity: 0.9 } }).addTo(this.routesLayer);
        this.map.fitBounds(this.routesLayer.getBounds(), { padding: [20, 20] });
    },

    renderMeetingPoint() {
        if (!this.meetingPoint || !this.meetingPoint.lat || !this.meetingPoint.lng) return;

        L.marker([this.meetingPoint.lat, this.meetingPoint.lng], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: '<div class="text-3xl">📍</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30],
            })
        }).bindPopup('Meeting Point: ' + (this.meetingPoint.name || '')).addTo(this.map);
    },

    selectRoute(route) {
        this.selectedRoute = route;
        this.$dispatch('route-selected', { route });
    },

    highlightSelectedRoute() {
        // Implementation for highlighting selected route
    },

    addFeatureMarker(feature) {
        if (!feature.start_lat || !feature.start_lng) return;

        const icon = this.getFeatureIcon(feature.feature_type);
        const marker = L.marker([feature.start_lat, feature.start_lng], { icon })
            .bindPopup(`<strong>${feature.feature_type}</strong><br>${feature.description || ''}`)
            .addTo(this.featuresLayer);

        return marker;
    },

    getFeatureIcon(type) {
        const icons = {
            gravel: '🪨',
            steep: '📈',
            technical: '🚵',
            scenic: '🌄',
            water: '💧',
            cafe: '☕',
            shop: '🔧',
        };
        return L.divIcon({
            className: 'custom-marker',
            html: `<div class="text-2xl">${icons[type] || '📍'}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 30],
        });
    },

    clearLayers() {
        this.routesLayer?.clearLayers();
        this.featuresLayer?.clearLayers();
    },
}));

// Elevation Chart Component
Alpine.data('elevationChart', (config = {}) => ({
    profile: config.profile ?? [],
    chart: null,

    init() {
        if (!this.$el.querySelector('canvas')) {
            this.$el.appendChild(document.createElement('canvas'));
        }
        this.$watch('profile', () => this.renderChart());
        this.renderChart();
    },

    renderChart() {
        if (!this.profile || !this.profile.length) return;

        const canvas = this.$el.querySelector('canvas') || this.$el;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        if (this.chart) {
            this.chart.destroy();
        }

        import('chart.js/auto').then(({ default: Chart }) => {
            import('chartjs-adapter-date-fns').then(() => {
                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.profile.map(d => d.distance_km),
                        datasets: [{
                            label: 'Elevation (m)',
                            data: this.profile.map(d => d.elevation),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index',
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => `Elevation: ${ctx.raw}m`,
                                    title: ctx => `Distance: ${ctx[0].label} km`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                title: { display: true, text: 'Distance (km)' },
                                grid: { display: false },
                            },
                            y: {
                                title: { display: true, text: 'Elevation (m)' },
                                beginAtZero: false,
                            },
                        },
                    },
                });
            });
        });
    },
}));

// Ride Join Button
Alpine.data('rideJoin', () => ({
    ride: null,
    user: null,
    status: null,
    loading: false,

    init() {
        this.checkStatus();
    },

    async checkStatus() {
        if (!this.rideId || !this.userId) return;

        try {
            const response = await fetch(`/rides/${this.rideId}/attendee-status`);
            const data = await response.json();
            this.status = data.status;
            this.rideVersion = data.ride_version;
        } catch (e) {
            console.error('Failed to check ride status', e);
        }
    },

    async join() {
        if (this.loading) return;
        this.loading = true;

        try {
            const response = await fetch(`/rides/${this.rideId}/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });

            if (response.ok) {
                this.status = 'confirmed';
                this.$dispatch('joined-ride', { rideId: this.rideId });
            } else {
                const error = await response.json();
                alert(error.message || 'Failed to join ride');
            }
        } catch (e) {
            alert('An error occurred');
        } finally {
            this.loading = false;
        }
    },

    async leave() {
        if (this.loading) return;
        this.loading = true;

        try {
            const response = await fetch(`/rides/${this.rideId}/leave`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });

            if (response.ok) {
                this.status = null;
                this.$dispatch('left-ride', { rideId: this.rideId });
            }
        } catch (e) {
            alert('An error occurred');
        } finally {
            this.loading = false;
        }
    },

    toggle() {
        if (this.status) {
            this.leave();
        } else {
            this.join();
        }
    },

    get buttonText() {
        if (this.loading) return '...';
        if (this.status === 'confirmed') return 'Leave Ride';
        if (this.status === 'tentative') return 'Update to Confirmed';
        return "I'm In";
    },

    get buttonClass() {
        if (this.status === 'confirmed') return 'bg-red-600 hover:bg-red-700';
        return 'bg-primary hover:bg-primary-hover';
    },
}));

// Notification Bell
Alpine.data('notificationBell', () => ({
    open: false,
    notifications: [],
    unreadCount: 0,

    init() {
        this.fetchNotifications();
        setInterval(() => this.fetchNotifications(), 60000); // Poll every minute
    },

    async fetchNotifications() {
        try {
            const response = await fetch('/notifications', { headers: { Accept: 'application/json' } });
            const data = await response.json();
            this.notifications = data.notifications;
            this.unreadCount = data.unread_count;
        } catch (e) {
            console.error('Failed to fetch notifications', e);
        }
    },

    async markAsRead(id) {
        try {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    Accept: 'application/json',
                },
            });
            const notif = this.notifications.find(n => n.id === id);
            if (notif) notif.read_at = new Date().toISOString();
            this.unreadCount = Math.max(0, this.unreadCount - 1);
        } catch (e) {
            console.error('Failed to mark notification as read', e);
        }
    },

    async markAllAsRead() {
        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    Accept: 'application/json',
                },
            });
            this.notifications.forEach(n => n.read_at = new Date().toISOString());
            this.unreadCount = 0;
        } catch (e) {
            console.error('Failed to mark all as read', e);
        }
    },

    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return date.toLocaleDateString();
    },
}));

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// Route star rating
Alpine.data('routeRating', (initial = 0, url = '') => ({
    rating: Number(initial) || 0,
    loading: false,
    url,
    async submit() {
        if (!this.rating || this.loading || !this.url) return;

        this.loading = true;
        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ rating: this.rating }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }
            alert('Failed to submit rating');
        } catch (e) {
            alert('Failed to submit rating');
        }
        this.loading = false;
    },
}));

// New top-level comment form
Alpine.data('routeComments', (url = '') => ({
    content: '',
    posting: false,
    url,
    async submit() {
        if (!this.content.trim() || this.posting || !this.url) return;

        this.posting = true;
        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ content: this.content }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }
            alert('Failed to post comment');
        } catch (e) {
            alert('An error occurred');
        }
        this.posting = false;
    },
}));

// Single comment (edit / delete / reply)
Alpine.data('commentItem', () => ({
    editing: false,
    replyOpen: false,
    editContent: '',
    replyContent: '',
    saving: false,
    commentId: null,
    routeId: null,
    originalContent: '',

    init() {
        this.commentId = this.$el.dataset.commentId;
        this.routeId = this.$el.dataset.routeId;
        this.originalContent = this.$el.dataset.content || '';
        this.editContent = this.originalContent;
    },

    toggleEdit() {
        this.editing = !this.editing;
        if (this.editing) {
            this.editContent = this.originalContent;
        }
    },

    async updateComment() {
        if (this.saving) return;

        this.saving = true;
        try {
            const response = await fetch(`/comments/${this.commentId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ content: this.editContent }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }
            alert('Failed to update comment');
        } catch (e) {
            alert('Failed to update comment');
        }
        this.saving = false;
    },

    async deleteComment() {
        if (!confirm('Delete this comment?')) return;

        try {
            const response = await fetch(`/comments/${this.commentId}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (response.ok) {
                window.location.reload();
            }
        } catch (e) {
            alert('Failed to delete comment');
        }
    },

    async submitReply() {
        if (!this.replyContent.trim() || this.saving) return;

        this.saving = true;
        try {
            const response = await fetch(`/routes/${this.routeId}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    content: this.replyContent,
                    parent_id: Number(this.commentId),
                }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }
            alert('Failed to post reply');
        } catch (e) {
            alert('Failed to post reply');
        }
        this.saving = false;
    },
}));

// GPX Upload Preview
Alpine.data('routePreviewMap', () => ({
    map: null,
    layer: null,
    geometry: null,

    init() {
        this.map = L.map(this.$el, {
            zoomControl: true,
            scrollWheelZoom: false,
        }).setView([47.0, 8.0], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(this.map);

        this.layer = L.featureGroup().addTo(this.map);
        this.$watch('geometry', () => this.render());

        if (typeof ResizeObserver !== 'undefined') {
            this.resizeObserver = new ResizeObserver(() => this.queueFit());
            this.resizeObserver.observe(this.$el);
        }

        this.render();
    },

render() {
        this.layer.clearLayers();
        let coordinates = [];
        if (this.geometry) {
            const geojson = typeof this.geometry === 'string' ? JSON.parse(this.geometry) : this.geometry;
            L.geoJSON(geojson, {
                style: { color: '#2563eb', weight: 4, opacity: 0.9 },
            }).addTo(this.layer);
            coordinates = geojson.coordinates || [];
        }
        if (coordinates.length) {
            const start = [coordinates[0][1], coordinates[0][0]];
            L.circleMarker(start, {
                radius: 8,
                color: '#fff',
                weight: 2,
                fillColor: '#16a34a',
                fillOpacity: 1,
            }).bindTooltip('Start').addTo(this.layer);
            if (coordinates.length > 1) {
                const last = coordinates[coordinates.length - 1];
                const end = [last[1], last[0]];
                L.circleMarker(end, {
                    radius: 8,
                    color: '#fff',
                    weight: 2,
                    fillColor: '#dc2626',
                    fillOpacity: 1,
                }).bindTooltip('End').addTo(this.layer);
            }
        }
        this.queueFit();
    },

    queueFit() {
        if (this.fitQueued) return;
        this.fitQueued = true;
        requestAnimationFrame(() => {
            this.fitQueued = false;
            this.fit();
        });
    },

    fit() {
        if (!this.map) return;
        if (this.$el.clientWidth === 0 || this.$el.clientHeight === 0) return;

        this.map.invalidateSize();
        if (this.geometry && this.layer.getLayers().length > 0) {
            this.map.fitBounds(this.layer.getBounds(), { padding: [20, 20] });
        }
    },
}));

Alpine.data('singleRouteMap', (config = {}) => ({
    map: null,
    routeLayer: null,
    featuresLayer: null,
    geometry: config.geometry ?? null,
    difficulty: config.difficulty ?? 'moderate',
    features: config.features ?? [],
    interactive: config.interactive ?? true,

    initMap(el = null) {
        const container = el || this.$el;
        if (this.map) {
            return;
        }
        this.map = L.map(container, this.interactive
            ? { zoomControl: true, scrollWheelZoom: false }
            : { zoomControl: false, attributionControl: false, dragging: false, doubleClickZoom: false, boxZoom: false, keyboard: false, scrollWheelZoom: false, touchZoom: false })
            .setView([47.0, 8.0], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(this.map);

        this.routeLayer = L.featureGroup().addTo(this.map);
        this.featuresLayer = L.layerGroup().addTo(this.map);

        this.$watch('geometry', () => this.renderRoute());
        this.$watch('features', () => this.renderFeatures());
        this.renderRoute();
        this.renderFeatures();
    },

    init() {
        // Map initialization is deferred until the element enters the viewport.
    },


    renderRoute() {
        this.routeLayer.clearLayers();
        if (!this.geometry) return;

        const geojson = typeof this.geometry === 'string' ? JSON.parse(this.geometry) : this.geometry;
        const color = this.getDifficultyColor(this.difficulty);
        L.geoJSON(geojson, {
            style: { color, weight: 4, opacity: 0.9 },
        }).addTo(this.routeLayer);

        if (this.routeLayer.getLayers().length > 0) {
            this.map.fitBounds(this.routeLayer.getBounds(), { padding: [20, 20] });
        }
    },

    renderFeatures() {
        this.featuresLayer.clearLayers();
        if (!this.features || !this.features.length) return;

        this.features.forEach((feature) => this.addFeatureMarker(feature));
    },

    getDifficultyColor(difficulty) {
        const colors = {
            easy: '#16a34a',
            moderate: '#f59e0b',
            hard: '#ea580c',
            expert: '#dc2626',
        };
        return colors[difficulty] || '#2563eb';
    },

    getFeatureIcon(type) {
        const icons = {
            gravel: '🪨',
            steep: '📈',
            technical: '🚵',
            scenic: '🌄',
            water: '💧',
            cafe: '☕',
            shop: '🔧',
        };
        return L.divIcon({
            className: 'custom-marker',
            html: `<div class="text-2xl">${icons[type] || '📍'}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 30],
        });
    },

    addFeatureMarker(feature) {
        if (!feature.start_lat || !feature.start_lng) return;

        const icon = this.getFeatureIcon(feature.feature_type);
        L.marker([feature.start_lat, feature.start_lng], { icon })
            .bindPopup(`<strong>${feature.label || feature.feature_type}</strong><br>${feature.description || ''}`)
            .addTo(this.featuresLayer);
    },
}));

Alpine.data('meetingPointPicker', () => ({
    name: '',
    lat: null,
    lng: null,
    searching: false,
    resolvingName: false,
    map: null,
    markerLayer: null,

    init() {
        this.map = L.map(this.$refs.map, {
            zoomControl: true,
            scrollWheelZoom: false,
        }).setView([47.0, 8.0], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(this.map);

        this.markerLayer = L.layerGroup().addTo(this.map);

        this.map.on('click', (e) => this.setLocation(e.latlng.lat, e.latlng.lng));

        if (this.lat && this.lng) {
            this.setLocation(this.lat, this.lng, false);
        }

        window.addEventListener('meeting-point-default', (e) => {
            if (e.detail && e.detail.lat && e.detail.lng) {
                this.setLocation(e.detail.lat, e.detail.lng);
            }
        });
    },

    setLocation(lat, lng, recenter = true) {
        this.lat = lat;
        this.lng = lng;
        this.markerLayer.clearLayers();
        L.marker([lat, lng]).addTo(this.markerLayer);
        if (recenter) {
            this.map.setView([lat, lng], 15);
        }
        this.resolveName();
    },

    async resolveName() {
        if (this.name || !this.lat || !this.lng) return;

        const lat = this.lat;
        const lng = this.lng;
        this.resolvingName = true;

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`);
            const data = await response.json();

            if (this.name || this.lat !== lat || this.lng !== lng) return;

            this.name = this.formatPlaceName(data);
        } catch (e) {
            console.error('Reverse geocoding failed', e);
        } finally {
            this.resolvingName = false;
        }
    },

    formatPlaceName(data) {
        if (!data) return '';

        const address = data.address || {};
        const parts = [
            data.name,
            address.amenity || address.building || address.leisure || address.tourism,
            address.road,
            address.suburb || address.neighbourhood || address.village || address.town || address.city,
        ].filter(Boolean);

        const unique = [...new Set(parts)];

        return unique.slice(0, 3).join(', ') || data.display_name || '';
    },

    async search() {
        if (!this.name) return;

        this.searching = true;
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.name)}&limit=1`);
            const results = await response.json();
            if (results.length > 0) {
                this.setLocation(parseFloat(results[0].lat), parseFloat(results[0].lon));
            }
        } catch (e) {
            console.error('Geocoding failed', e);
        } finally {
            this.searching = false;
        }
    },
}));

Alpine.data('routeSelect', () => ({
    onRouteChange(e) {
        const opt = e.target.selectedOptions[0];
        if (!opt) return;

        const lat = parseFloat(opt.dataset.startLat);
        const lng = parseFloat(opt.dataset.startLng);

        if (lat && lng) {
            this.$dispatch('meeting-point-default', { lat, lng });
        }
    },
}));

Alpine.data('gpxUpload', () => ({
    file: null,
    preview: null,
    parsing: false,
    error: null,
    customFeature: '',
    customFeatures: [],
    startCrop: 0,
    endCrop: 0,
    fullPoints: [],
    fullGeometry: null,

    async handleFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!file.name.endsWith('.gpx')) {
            this.error = 'Please select a GPX file';
            return;
        }

        this.file = file;
        this.error = null;
        this.parsing = true;

        try {
            const formData = new FormData();
            formData.append('gpx_file', file);

            const response = await fetch('/api/routes/parse-gpx', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            if (response.ok) {
                this.preview = await response.json();
                this.fullGeometry = this.preview.geometry;
                this.fullPoints = [];
                const tracks = (this.preview.gpx_data && this.preview.gpx_data.tracks) || [];
                tracks.forEach((track) => (track.segments || []).forEach((segment) => segment.forEach((point) => {
                    this.fullPoints.push([point[0], point[1], point[2] || 0]);
                })));
                this.startCrop = 0;
                this.endCrop = 0;
                this.recompute();
                this.$dispatch('gpx-parsed', { preview: this.preview });
            } else {
                const error = await response.json();
                this.error = error.message || 'Failed to parse GPX';
            }
        } catch (e) {
            this.error = 'An error occurred while parsing';
        } finally {
            this.parsing = false;
        }
    },

    recompute() {
        if (!this.preview || !this.fullPoints.length) return;

        const n = this.fullPoints.length;
        if (n <= 2) return;

        let startIndex = Math.round(n * this.startCrop / 100);
        let endIndex = Math.round(n * this.endCrop / 100);

        if (n - startIndex - endIndex < 2) {
            endIndex = n - startIndex - 2;
        }
        if (endIndex < 0) return;

        const cropped = this.fullPoints.slice(startIndex, n - endIndex);

        const distance = this.calcDistance(cropped);
        const gain = this.calcElevationGain(cropped);

        this.preview = {
            ...this.preview,
            distance_km: Math.round(distance * 100) / 100,
            elevation_gain_m: gain,
            estimated_time_min: this.calcEstTime(distance, gain),
            difficulty: this.calcDifficulty(distance, gain),
            geometry: {
                type: 'LineString',
                coordinates: cropped.map((point) => [point[0], point[1]]),
            },
        };
    },

    calcDistance(points) {
        let total = 0;
        for (let i = 1; i < points.length; i++) {
            total += this.haversineKm(points[i - 1][1], points[i - 1][0], points[i][1], points[i][0]);
        }
        return total;
    },

    calcElevationGain(points) {
        let gain = 0;
        for (let i = 1; i < points.length; i++) {
            const diff = points[i][2] - points[i - 1][2];
            if (diff > 0) gain += diff;
        }
        return Math.round(gain);
    },

    calcEstTime(distanceKm, gainM) {
        return Math.round((distanceKm / 20 + gainM / 600) * 60);
    },

    calcDifficulty(distanceKm, gainM) {
        const score = distanceKm / 10 + gainM / 200;
        if (score < 3) return 'easy';
        if (score < 6) return 'moderate';
        if (score < 10) return 'hard';
        return 'expert';
    },

    haversineKm(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    },

    resetTrim() {
        this.startCrop = 0;
        this.endCrop = 0;
        this.recompute();
    },

    setTrimStart() {
        this.startCrop = Math.max(0, Math.min(90, Math.round(this.startCrop)));
        if (this.startCrop + this.endCrop > 90) {
            this.endCrop = 90 - this.startCrop;
        }
        this.recompute();
    },

    setTrimEnd() {
        this.endCrop = Math.max(0, Math.min(90, Math.round(this.endCrop)));
        if (this.startCrop + this.endCrop > 90) {
            this.startCrop = 90 - this.endCrop;
        }
        this.recompute();
    },

    clear() {
        this.file = null;
        this.preview = null;
        this.error = null;
        this.customFeatures = [];
        this.customFeature = '';
        this.startCrop = 0;
        this.endCrop = 0;
        this.fullPoints = [];
        this.fullGeometry = null;
        this.$refs.fileInput.value = '';
    },

    addCustomFeature() {
        const value = this.customFeature.trim();
        if (!value) return;

        if (!this.customFeatures.includes(value)) {
            this.customFeatures.push(value);
        }
        this.customFeature = '';
    },
}));

Alpine.start();
