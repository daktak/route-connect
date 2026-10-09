import Alpine from 'alpinejs';
import 'leaflet/dist/leaflet.css';
import 'leaflet';
import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

window.Alpine = Alpine;
window.Chart = Chart;
window.L = require('leaflet');

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
                if (key === 'features') {
                    this.filters[key] = value.split(',');
                } else {
                    this.filters[key] = value;
                }
            }
        });

        this.$watch('filters', () => this.updateUrl(), { deep: true });
    },

    updateUrl() {
        const params = new URLSearchParams();
        Object.entries(this.filters).forEach(([key, value]) => {
            if (value && (Array.isArray(value) ? value.length : value)) {
                params.set(key, Array.isArray(value) ? value.join(',') : value);
            }
        });
        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.replaceState({}, '', newUrl);
        this.$dispatch('filters-changed', { filters: this.filters });
    },

    clearFilters() {
        Object.keys(this.filters).forEach(key => {
            this.filters[key] = Array.isArray(this.filters[key]) ? [] : '';
        });
    },

    hasActiveFilters() {
        return Object.values(this.filters).some(v =>
            Array.isArray(v) ? v.length > 0 : v !== ''
        );
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
Alpine.data('routeMap', () => ({
    map: null,
    routesLayer: null,
    featuresLayer: null,
    selectedRoute: null,

    init() {
        this.initMap();
    },

    initMap() {
        this.map = L.map(this.$el, {
            zoomControl: true,
            scrollWheelZoom: true,
        }).setView([47.0, 8.0], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(this.map);

        this.routesLayer = L.layerGroup().addTo(this.map);
        this.featuresLayer = L.layerGroup().addTo(this.map);

        this.$watch('routes', () => this.renderRoutes());
        this.$watch('selectedRouteId', () => this.highlightSelectedRoute());
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

        this.fitBounds();
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
Alpine.data('elevationChart', () => ({
    chart: null,
    chartData: null,

    init() {
        this.$watch('data', () => this.renderChart());
    },

    renderChart() {
        if (!this.data || !this.data.length) return;

        const ctx = this.$el.getContext('2d');

        if (this.chart) {
            this.chart.destroy();
        }

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.data.map(d => d.distance_km),
                datasets: [{
                    label: 'Elevation (m)',
                    data: this.data.map(d => d.elevation),
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
            const response = await fetch(`/api/rides/${this.rideId}/attendee-status`);
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
            const response = await fetch(`/api/rides/${this.rideId}/join`, {
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
            const response = await fetch(`/api/rides/${this.rideId}/leave`, {
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

        this.layer = L.layerGroup().addTo(this.map);
        this.$watch('geometry', () => this.render());
        this.render();
    },

    render() {
        this.layer.clearLayers();
        if (!this.geometry) return;

        const geojson = typeof this.geometry === 'string' ? JSON.parse(this.geometry) : this.geometry;
        L.geoJSON(geojson, {
            style: { color: '#2563eb', weight: 4, opacity: 0.9 },
        }).addTo(this.layer);

        if (this.layer.getLayers().length > 0) {
            this.map.fitBounds(this.layer.getBounds(), { padding: [20, 20] });
        }
    },
}));

Alpine.data('singleRouteMap', () => ({
    map: null,
    routeLayer: null,
    featuresLayer: null,
    geometry: null,
    difficulty: 'moderate',
    features: [],

    init() {
        this.map = L.map(this.$el, {
            zoomControl: true,
            scrollWheelZoom: false,
        }).setView([47.0, 8.0], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(this.map);

        this.routeLayer = L.layerGroup().addTo(this.map);
        this.featuresLayer = L.layerGroup().addTo(this.map);

        this.$watch('geometry', () => this.renderRoute());
        this.$watch('features', () => this.renderFeatures());
        this.renderRoute();
        this.renderFeatures();
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
    },

    setLocation(lat, lng, recenter = true) {
        this.lat = lat;
        this.lng = lng;
        this.markerLayer.clearLayers();
        L.marker([lat, lng]).addTo(this.markerLayer);
        if (recenter) {
            this.map.setView([lat, lng], 15);
        }
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

Alpine.data('gpxUpload', () => ({
    file: null,
    preview: null,
    parsing: false,
    error: null,
    customFeature: '',
    customFeatures: [],

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
                body: formData,
            });

            if (response.ok) {
                this.preview = await response.json();
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

    clear() {
        this.file = null;
        this.preview = null;
        this.error = null;
        this.customFeatures = [];
        this.customFeature = '';
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