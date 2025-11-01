/**
 * Map/Location Fieldtype JavaScript
 * Uses Leaflet with OpenStreetMap (no API key required)
 */
(function() {
    'use strict';
    
    let mapInstances = {};
    
    function initMapField(fieldName) {
        const container = document.getElementById('field_' + fieldName);
        if (!container) return;
        
        const mapDiv = document.getElementById('map_' + fieldName);
        const latInput = document.getElementById('map_lat_' + fieldName);
        const lngInput = document.getElementById('map_lng_' + fieldName);
        const addressInput = document.getElementById('map_address_' + fieldName);
        const hiddenInput = document.getElementById('field_' + fieldName + '_value');
        const searchBtn = document.getElementById('map_search_' + fieldName);
        const getLocationBtn = document.getElementById('map_get_location_' + fieldName);
        
        if (!mapDiv || !latInput || !lngInput || !hiddenInput) return;
        
        // Load Leaflet CSS if not already loaded
        if (!document.querySelector('link[href*="leaflet"]')) {
            const leafletCSS = document.createElement('link');
            leafletCSS.rel = 'stylesheet';
            leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(leafletCSS);
        }
        
        // Load Leaflet JS if not already loaded
        if (typeof L === 'undefined') {
            const leafletJS = document.createElement('script');
            leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            leafletJS.onload = function() {
                initMap();
            };
            document.head.appendChild(leafletJS);
        } else {
            initMap();
        }
        
        function initMap() {
            // Get initial location
            let lat = parseFloat(latInput.value) || 51.505;
            let lng = parseFloat(lngInput.value) || -0.09;
            
            // Initialize map
            const map = L.map(mapDiv).setView([lat, lng], 13);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add marker
            let marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            
            // Store map instance
            mapInstances[fieldName] = { map, marker };
            
            // Update location when marker is dragged
            marker.on('dragend', function(e) {
                const position = marker.getLatLng();
                latInput.value = position.lat.toFixed(6);
                lngInput.value = position.lng.toFixed(6);
                updateHiddenInput();
            });
            
            // Update marker when inputs change
            function updateMarker() {
                const newLat = parseFloat(latInput.value);
                const newLng = parseFloat(lngInput.value);
                
                if (!isNaN(newLat) && !isNaN(newLng)) {
                    const newPosition = [newLat, newLng];
                    marker.setLatLng(newPosition);
                    map.setView(newPosition, map.getZoom());
                    updateHiddenInput();
                }
            }
            
            latInput.addEventListener('change', updateMarker);
            lngInput.addEventListener('change', updateMarker);
            
            // Update marker on map click
            map.on('click', function(e) {
                const position = e.latlng;
                latInput.value = position.lat.toFixed(6);
                lngInput.value = position.lng.toFixed(6);
                marker.setLatLng(position);
                updateHiddenInput();
            });
            
            // Search address (geocoding)
            if (searchBtn && addressInput) {
                searchBtn.addEventListener('click', function() {
                    const address = addressInput.value.trim();
                    if (!address) {
                        alert('Please enter an address to search');
                        return;
                    }
                    
                    searchBtn.disabled = true;
                    searchBtn.textContent = 'Searching...';
                    
                    // Use Nominatim (OpenStreetMap geocoding) - free, no API key
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`)
                        .then(response => response.json())
                        .then(data => {
                            searchBtn.disabled = false;
                            searchBtn.textContent = 'Search Location';
                            
                            if (data && data.length > 0) {
                                const result = data[0];
                                const lat = parseFloat(result.lat);
                                const lng = parseFloat(result.lon);
                                
                                latInput.value = lat.toFixed(6);
                                lngInput.value = lng.toFixed(6);
                                
                                marker.setLatLng([lat, lng]);
                                map.setView([lat, lng], 15);
                                updateHiddenInput();
                            } else {
                                alert('Address not found. Please try a different address.');
                            }
                        })
                        .catch(error => {
                            console.error('Geocoding error:', error);
                            searchBtn.disabled = false;
                            searchBtn.textContent = 'Search Location';
                            alert('Error searching for address: ' + error.message);
                        });
                });
            }
            
            // Get current location
            if (getLocationBtn) {
                getLocationBtn.addEventListener('click', function() {
                    if (!navigator.geolocation) {
                        alert('Geolocation is not supported by your browser');
                        return;
                    }
                    
                    getLocationBtn.disabled = true;
                    getLocationBtn.textContent = 'Getting location...';
                    
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            latInput.value = lat.toFixed(6);
                            lngInput.value = lng.toFixed(6);
                            
                            marker.setLatLng([lat, lng]);
                            map.setView([lat, lng], 15);
                            updateHiddenInput();
                            
                            getLocationBtn.disabled = false;
                            getLocationBtn.textContent = 'Get Current Location';
                        },
                        function(error) {
                            console.error('Geolocation error:', error);
                            getLocationBtn.disabled = false;
                            getLocationBtn.textContent = 'Get Current Location';
                            alert('Error getting location: ' + error.message);
                        }
                    );
                });
            }
            
            function updateHiddenInput() {
                const lat = parseFloat(latInput.value);
                const lng = parseFloat(lngInput.value);
                const address = addressInput ? addressInput.value.trim() : '';
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    const location = { lat, lng };
                    if (address) {
                        location.address = address;
                    }
                    hiddenInput.value = JSON.stringify(location);
                }
            }
            
            // Initial update
            updateHiddenInput();
        }
    }
    
    // Initialize all map fields
    function initAllMapFields() {
        document.querySelectorAll('.map-field').forEach(function(container) {
            const fieldName = container.id.replace('field_', '');
            if (fieldName) {
                initMapField(fieldName);
            }
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllMapFields);
    } else {
        // Wait a bit for Leaflet to potentially load
        setTimeout(initAllMapFields, 100);
    }
    
    // Also initialize on custom event for dynamic fields
    document.addEventListener('fieldtype:init', function(e) {
        if (e.detail && e.detail.fieldtype === 'map') {
            const fieldName = e.detail.fieldName || e.detail.element?.id?.replace('field_', '');
            if (fieldName) {
                setTimeout(() => initMapField(fieldName), 100);
            }
        }
    });
    
    // Expose for manual initialization
    window.initMapField = initMapField;
})();

