/**
 * Map/Location Fieldtype JavaScript
 * Coordinates, address search and "use my location" work on their own; the
 * Leaflet map (OpenStreetMap tiles, no API key) is shown once Leaflet loads.
 */
(function() {
    'use strict';

    // Leaflet 1.9.4 (BSD-2) ships in this fieldtype: leaflet/.
    function vendorUrl(file) {
        const path = '/fieldtypes/Map/leaflet/' + file;
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }
    const LEAFLET_CSS = vendorUrl('leaflet.css');
    const LEAFLET_JS = vendorUrl('leaflet.js');
    let leafletPromise = null;

    function loadLeaflet() {
        if (typeof L !== 'undefined') {
            return Promise.resolve(true);
        }
        if (leafletPromise) {
            return leafletPromise;
        }
        leafletPromise = new Promise(function(resolve) {
            if (!document.querySelector('link[href*="leaflet"]')) {
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = LEAFLET_CSS;
                document.head.appendChild(css);
            }
            const js = document.createElement('script');
            js.src = LEAFLET_JS;
            js.onload = function() { resolve(typeof L !== 'undefined'); };
            js.onerror = function() { resolve(false); };
            document.head.appendChild(js);
        });
        return leafletPromise;
    }

    function notify(message) {
        if (window.BirklyFeedback && typeof window.BirklyFeedback.toast === 'function') {
            window.BirklyFeedback.toast(message, 'error');
        } else {
            alert(message);
        }
    }

    function initMapField(fieldName) {
        const container = document.getElementById('field_' + fieldName);
        if (!container || container.dataset.mapReady === '1') return;

        const mapDiv = document.getElementById('map_' + fieldName);
        const latInput = document.getElementById('map_lat_' + fieldName);
        const lngInput = document.getElementById('map_lng_' + fieldName);
        const addressInput = document.getElementById('map_address_' + fieldName);
        const hiddenInput = document.getElementById('field_' + fieldName + '_value');
        const searchBtn = document.getElementById('map_search_' + fieldName);
        const getLocationBtn = document.getElementById('map_get_location_' + fieldName);

        if (!latInput || !lngInput || !hiddenInput) return;
        container.dataset.mapReady = '1';

        let map = null;
        let marker = null;

        function updateHiddenInput() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            const address = addressInput ? addressInput.value.trim() : '';
            if (!isNaN(lat) && !isNaN(lng)) {
                const location = { lat: lat, lng: lng };
                if (address) {
                    location.address = address;
                }
                hiddenInput.value = JSON.stringify(location);
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function setPosition(lat, lng, zoom) {
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
            if (map && marker) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], zoom || map.getZoom());
            }
            updateHiddenInput();
        }

        function onCoordinatesChanged() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                setPosition(lat, lng);
            }
        }

        latInput.addEventListener('change', onCoordinatesChanged);
        lngInput.addEventListener('change', onCoordinatesChanged);
        if (addressInput) {
            addressInput.addEventListener('change', updateHiddenInput);
        }

        if (searchBtn && addressInput) {
            searchBtn.addEventListener('click', function() {
                const address = addressInput.value.trim();
                if (!address) {
                    notify('Enter an address to search for.');
                    return;
                }
                searchBtn.disabled = true;
                fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(address))
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data && data.length > 0) {
                            setPosition(parseFloat(data[0].lat), parseFloat(data[0].lon), 15);
                        } else {
                            notify('Address not found. Try a different address.');
                        }
                    })
                    .catch(function(error) {
                        console.error('Geocoding error:', error);
                        notify('Address search failed: ' + error.message);
                    })
                    .finally(function() {
                        searchBtn.disabled = false;
                    });
            });
        }

        if (getLocationBtn) {
            getLocationBtn.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    notify('This browser cannot share its location.');
                    return;
                }
                getLocationBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        setPosition(position.coords.latitude, position.coords.longitude, 15);
                        getLocationBtn.disabled = false;
                    },
                    function(error) {
                        console.error('Geolocation error:', error);
                        getLocationBtn.disabled = false;
                        notify('Could not get your location: ' + error.message);
                    }
                );
            });
        }

        if (!mapDiv) return;
        loadLeaflet().then(function(available) {
            if (!available) return;
            mapDiv.hidden = false;
            const lat = parseFloat(latInput.value) || 51.505;
            const lng = parseFloat(lngInput.value) || -0.09;
            map = L.map(mapDiv).setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function() {
                const position = marker.getLatLng();
                setPosition(position.lat, position.lng);
            });
            map.on('click', function(e) {
                setPosition(e.latlng.lat, e.latlng.lng);
            });
        });
    }

    function initAllMapFields() {
        document.querySelectorAll('.map-field').forEach(function(container) {
            const fieldName = container.id.replace('field_', '');
            if (fieldName) {
                initMapField(fieldName);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllMapFields);
    } else {
        initAllMapFields();
    }

    document.addEventListener('fieldtype:init', function(e) {
        if (e.detail && e.detail.fieldtype === 'map') {
            const fieldName = e.detail.fieldName || (e.detail.element && e.detail.element.id ? e.detail.element.id.replace('field_', '') : '');
            if (fieldName) {
                initMapField(fieldName);
            }
        }
    });

    window.initMapField = initMapField;
})();
