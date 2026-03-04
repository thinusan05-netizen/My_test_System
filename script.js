// --- CONFIG ---
const API_BASE = 'http://localhost/final_project/backend/api';
// Force Sri Lanka bounds for search context (approx)
const SRI_LANKA_BOUNDS = '5.916667,79.516667,9.850000,81.850000'; // S,W,N,E

// --- STATE ---
const State = {
    map: null,
    startMarker: null,     // Green
    endMarker: null,       // Red
    userMarker: null,      // The moving car

    startLatLng: null,
    endLatLng: null,

    routePolyline: null,   // The blue line
    riskZones: [],
    accidentMarkers: [],   // Store accident markers
    trainingAccidentMarkers: [], // Store training dataset accident markers

    // Modes
    audioEnabled: false,
    navMode: 'IDLE',       // IDLE, SIMULATION, DRIVING

    // Internals
    watchId: null,
    simInterval: null,
    lastSpeakTime: 0,
    routePath: [],
    alertedLocations: new Map(), // Track alerted locations with cooldown
    proximityCheckInterval: null,

    isRerouting: false, // Prevent multiple reroute attempts at once
    lastRerouteTime: 0,  // Cooldown for rerouting

    // Speed & Tracking
    currentSpeed: 0,
    lastPosition: null,
    lastSpeedAlertTime: 0,
    lastLocationUpdate: 0,
    currentAreaRisk: 'Safe', // Safe, Warning, Danger

    // Weather
    currentWeather: null,
    lastWeatherUpdate: 0
};

// --- INIT ---
document.addEventListener('DOMContentLoaded', () => {
    initMap();
    fetchTrainingAccidents(); // Load training dataset accidents
    locateUser(); // Try GPS once on load

    // Enter key listener
    document.getElementById('end-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') planRoute();
    });

    // Toggle Button Listener
    const toggleBtn = document.getElementById('toggle-panel-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', togglePanel);
    }
});

// --- CORE MAP FUNCTIONS ---
function initMap() {
    // Center of Sri Lanka
    State.map = L.map('map', { zoomControl: false }).setView([7.8731, 80.7718], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(State.map);

    L.control.zoom({ position: 'bottomright' }).addTo(State.map);

    // Click map to set START or END? 
    // Let's make click set Destination for ease of use if start is already set
    State.map.on('click', (e) => {
        if (State.navMode === 'IDLE') {
            setEndLocation(e.latlng, "Map Point " + e.latlng.lat.toFixed(3));
        }
    });
}

function createMarker(latlng, type) {
    const color = type === 'START' ? 'green' : 'red';
    const iconUrl = type === 'START'
        ? 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png'
        : 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png';

    const icon = L.icon({
        iconUrl: iconUrl,
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    return L.marker(latlng, { icon: icon });
}

function setStartLocation(latlng, name) {
    if (State.startMarker) State.map.removeLayer(State.startMarker);
    State.startMarker = createMarker(latlng, 'START').addTo(State.map).bindPopup("Start: " + name).openPopup();
    State.startLatLng = latlng;
    document.getElementById('start-input').value = name;

    // Also update Car position
    if (State.userMarker) State.map.removeLayer(State.userMarker);
    const carIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/741/741407.png',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
    State.userMarker = L.marker(latlng, { icon: carIcon, zIndexOffset: 1000 }).addTo(State.map);
}

function setEndLocation(latlng, name) {
    if (State.endMarker) State.map.removeLayer(State.endMarker);
    State.endMarker = createMarker(latlng, 'END').addTo(State.map).bindPopup("Dest: " + name).openPopup();
    State.endLatLng = latlng;
    document.getElementById('end-input').value = name;
}


// --- GPS & AUDIO ---
function locateUser() {
    const badge = document.getElementById('gps-badge');
    badge.innerText = "GPS: Searching...";
    badge.className = "badge";

    if (!navigator.geolocation) {
        badge.innerText = "GPS: N/A";
        badge.classList.add('error');
        // Default to Colombo
        setStartLocation(L.latLng(6.9271, 79.8612), "Colombo (Default)");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const latlng = L.latLng(pos.coords.latitude, pos.coords.longitude);
            State.map.setView(latlng, 15);
            setStartLocation(latlng, "Your GPS Location");

            badge.innerText = "GPS: Locked";
            badge.classList.add('active');
            updateLocationArea(latlng);
        },
        (err) => {
            console.warn("GPS Fail", err);
            badge.innerText = "GPS: Failed";
            badge.classList.add('error');
            alert("Could not get GPS. Defaulting to Colombo.");
            setStartLocation(L.latLng(6.9271, 79.8612), "Colombo (Default)");
        },
        { enableHighAccuracy: true, timeout: 5000 }
    );
}

function enableAudio() {
    const badge = document.getElementById('voice-badge');
    const u = new SpeechSynthesisUtterance("Audio Systems Online");
    window.speechSynthesis.speak(u);

    State.audioEnabled = true;
    badge.innerText = "Audio: ON";
    badge.classList.add('active');
    badge.onclick = null;
}

// --- DATA ---
async function fetchRiskZones() {
    // Hidden as per user request (show only trained data)
    console.log("Risk Zones hidden - only showing trained data");
}

// Fetch and display accident records
async function fetchAccidentRecords() {
    // Hidden as per user request (show only trained data)
    console.log("Manual Accident Records hidden - only showing trained data");
}

// Fetch and display training dataset accidents with ML predictions
async function fetchTrainingAccidents() {
    try {
        const res = await fetch(`${API_BASE}/get_training_accidents.php`);
        const json = await res.json();

        if (json.success && json.accidents) {
            console.log(`Loading ${json.count} training accidents on map...`);

            // Clear old markers if any
            State.trainingAccidentMarkers.forEach(m => State.map.removeLayer(m));
            State.trainingAccidentMarkers = [];

            json.accidents.forEach(accident => {
                // Determine marker color based on risk level
                let markerColor, iconUrl;
                if (accident.risk_level === 'High') {
                    markerColor = '#d32f2f';
                    iconUrl = 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png';
                } else if (accident.risk_level === 'Medium') {
                    markerColor = '#ff9800';
                    iconUrl = 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png';
                } else {
                    markerColor = '#388e3c';
                    iconUrl = 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png';
                }

                // Create custom icon
                const icon = L.icon({
                    iconUrl: iconUrl,
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                const marker = L.marker([accident.latitude, accident.longitude], {
                    icon: icon,
                    zIndexOffset: 500
                }).addTo(State.map);

                // Create popup with accident details and ML prediction
                let popupContent = `<div style="min-width: 200px;">
                    <h4 style="margin: 0 0 10px 0; color: ${markerColor};">
                        📍 Training Data Point #${accident.id}
                    </h4>
                    <p style="margin: 5px 0;"><strong>Risk Level:</strong> <span style="color: ${markerColor}; font-weight: bold;">${accident.risk_level}</span></p>
                    <p style="margin: 5px 0;"><strong>Weather:</strong> ${accident.weather_condition}</p>
                    <p style="margin: 5px 0;"><strong>Road:</strong> ${accident.road_condition}</p>
                    <p style="margin: 5px 0;"><strong>Traffic:</strong> ${accident.traffic_volume}</p>
                </div>`;

                marker.bindPopup(popupContent);
                State.trainingAccidentMarkers.push(marker);
            });

            console.log(`✓ Loaded ${json.count} training accidents`);
        }
    } catch (e) {
        console.error("Training accident fetch error", e);
    }
}

// --- ROUTING ---
async function planRoute() {
    const endText = document.getElementById('end-input').value;
    if (!endText) return alert("Enter a destination!");

    // 1. Geocode Destination (WITH SRI LANKA CONTEXT)
    // viewbox=<x1>,<y1>,<x2>,<y2> & bounded=1 restricts search to box
    const geoUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(endText)}&countrycodes=lk&limit=1`;

    try {
        const res = await fetch(geoUrl);
        const data = await res.json();

        if (!data.length) return alert(`Could not find "${endText}" in Sri Lanka.`);

        const dest = data[0];
        const destLatLng = L.latLng(dest.lat, dest.lon);

        // Explicitly set the END marker so user sees it
        setEndLocation(destLatLng, dest.display_name.split(',')[0]);

        // 2. Fetch OSRM Route
        const start = State.startLatLng;
        const end = State.endLatLng;

        if (!start) return alert("Start location not needed.");

        // Clear Old Route
        if (State.routePolyline) State.map.removeLayer(State.routePolyline);

        const osrm = `https://router.project-osrm.org/route/v1/driving/${start.lng},${start.lat};${end.lng},${end.lat}?overview=full&geometries=geojson`;

        const routeRes = await fetch(osrm);
        const routeData = await routeRes.json();

        if (routeData.code !== 'Ok') throw new Error("No road path found. Are start/end points connected by road?");

        const route = routeData.routes[0];
        const coords = route.geometry.coordinates.map(c => [c[1], c[0]]); // Flip to LatLng
        State.routePath = coords; // For simulation

        // Draw Line
        State.routePolyline = L.polyline(coords, {
            color: '#4285F4', weight: 6, opacity: 0.8
        }).addTo(State.map);

        State.map.fitBounds(State.routePolyline.getBounds(), { padding: [50, 50] });

        // Update UI
        document.getElementById('route-stats').classList.remove('hidden');
        document.getElementById('dist-val').innerText = (route.distance / 1000).toFixed(1) + " km";
        document.getElementById('time-val').innerText = (route.duration / 60).toFixed(0) + " min";

        document.getElementById('btn-sim').disabled = false;
        document.getElementById('btn-drive').disabled = false;

    } catch (e) {
        alert("Routing Error: " + e.message);
    }
}

// --- SIMULATION ---
function startSimulation() {
    if (!State.routePath.length) return;
    setNavMode('SIMULATION');
    minimizePanel(); // Auto-hide panel

    let idx = 0;
    const path = State.routePath;

    speak("Starting simulation.");

    State.simInterval = setInterval(() => {
        if (idx >= path.length) {
            stopNavigation();
            speak("You have arrived.");
            return;
        }

        const pt = path[idx];
        const latlng = L.latLng(pt[0], pt[1]);

        // In simulation, we fake the speed
        const simulatedSpeed = 50 + Math.random() * 40; // 50-90 km/h
        updateSpeedUI(simulatedSpeed);
        checkSpeedAlerts(simulatedSpeed);

        State.userMarker.setLatLng(latlng);
        State.map.panTo(latlng);

        checkRisks(latlng);
        if (idx % 10 === 0) updateLocationArea(latlng); // Update every 10 steps in sim

        // Skip points based on distance to speed up sim
        idx += 2;
    }, 100); // 100ms update
}

// --- REAL DRIVE ---
function startRealDrive() {
    if (!navigator.geolocation) return alert("No GPS!");
    setNavMode('DRIVING');
    minimizePanel(); // Auto-hide panel

    speak("Navigation started.");

    State.watchId = navigator.geolocation.watchPosition(
        (pos) => {
            const latlng = L.latLng(pos.coords.latitude, pos.coords.longitude);
            State.userMarker.setLatLng(latlng);
            State.map.setView(latlng, 18); // Tight zoom

            // Speed calculation
            let speed = pos.coords.speed; // Speed in m/s
            if (speed === null || speed === undefined) {
                // Calculate from distance
                if (State.lastPosition) {
                    const d = calculateDistance(
                        State.lastPosition.lat, State.lastPosition.lng,
                        latlng.lat, latlng.lng
                    );
                    const dt = (Date.now() - State.lastLocationUpdate) / 1000; // seconds
                    if (dt > 0) speed = d / dt;
                }
            }

            if (speed !== null) {
                const speedKmh = speed * 3.6;
                updateSpeedUI(speedKmh);
                checkSpeedAlerts(speedKmh);
            }

            State.lastPosition = latlng;
            State.lastLocationUpdate = Date.now();

            checkRisks(latlng);
            updateLocationArea(latlng);
        },
        (err) => console.warn(err),
        { enableHighAccuracy: true, maximumAge: 0 }
    );
}

// --- SHARED NAV HELPERS ---
function setNavMode(mode) {
    State.navMode = mode;
    document.getElementById('nav-status').classList.remove('hidden');
    document.getElementById('nav-msg').innerText = mode === 'SIMULATION' ? "Simulating..." : "Driving (GPS)";

    // Disable inputs
    document.getElementById('start-input').disabled = true;
    document.getElementById('btn-sim').disabled = true;
    document.getElementById('btn-drive').disabled = true;
}

function stopNavigation() {
    clearInterval(State.simInterval);
    if (State.watchId) navigator.geolocation.clearWatch(State.watchId);

    State.navMode = 'IDLE';
    document.getElementById('nav-status').classList.add('hidden');
    document.getElementById('start-input').disabled = false;
    document.getElementById('btn-sim').disabled = false;
    document.getElementById('btn-drive').disabled = false;
}

// Enhanced proximity detection using API with ML integration
async function checkRisks(latlng) {
    const now = Date.now();

    try {
        // Call API to get nearby alerts within 300m AND ML prediction
        const res = await fetch(`${API_BASE}/get_nearby_alerts.php?lat=${latlng.lat}&lng=${latlng.lng}&radius=300`);
        const data = await res.json();

        if (!data.success) return;

        // --- SCOUT AHEAD FOR REROUTING (New) ---
        if (State.navMode !== 'IDLE' && !State.isRerouting) {
            await scoutPathAhead(latlng);
        }

        // --- ML PREDICTION ALERT (Priority: Check ML prediction first) ---
        if (data.ml_prediction && data.ml_prediction.risk_level) {
            const mlRiskLevel = data.ml_prediction.risk_level;
            const mlKey = `ml_${mlRiskLevel}_${Math.floor(latlng.lat * 1000)}_${Math.floor(latlng.lng * 1000)}`;

            // Check cooldown for ML prediction (60 seconds)
            const lastMlAlert = State.alertedLocations.get(mlKey);
            if (!lastMlAlert || (now - lastMlAlert) >= 60000) {
                // Fetch voice alert message from API
                try {
                    const voiceRes = await fetch(`${API_BASE}/get_voice_alert.php?risk_level=${mlRiskLevel}`);
                    const voiceData = await voiceRes.json();

                    if (voiceData.success) {
                        const isUrgent = mlRiskLevel === 'High';
                        showAlert(voiceData.message, isUrgent);
                        speakWithTone(voiceData.message, voiceData.speech_rate, voiceData.speech_pitch);
                        updateMLRiskIndicator(mlRiskLevel, data.ml_prediction.confidence);
                        State.alertedLocations.set(mlKey, now);
                        State.lastSpeakTime = now;
                        return; // ML prediction takes priority, skip zone alerts
                    }
                } catch (e) {
                    console.warn('Voice alert API failed, using fallback message', e);
                }
            }
        }

        // --- TRAINING DATA POINT ALERTS (Check proximity to training accidents) ---
        await checkTrainingDataProximity(latlng, now);

        // --- DATABASE ZONE/ACCIDENT ALERTS ---
        if (!data.alerts || data.alerts.length === 0) return;

        // Process each alert
        for (const alert of data.alerts) {
            const alertKey = `${alert.type}_${alert.id}`;

            // Check cooldown (60 seconds per location)
            const lastAlert = State.alertedLocations.get(alertKey);
            if (lastAlert && (now - lastAlert) < 60000) continue;

            // Generate appropriate message based on type and severity
            let message = '';
            let riskLevel = 'Medium';

            if (alert.type === 'risk_zone') {
                const distance = Math.round(alert.distance);
                riskLevel = alert.severity;
                if (alert.severity === 'High') {
                    message = `Warning! High danger zone ahead in ${distance} meters near ${alert.name}. Please drive carefully.`;
                } else if (alert.severity === 'Medium') {
                    message = `Caution! Medium risk area in ${distance} meters near ${alert.name}.`;
                } else {
                    message = `Notice: Low risk zone in ${distance} meters near ${alert.name}.`;
                }
            } else if (alert.type === 'accident') {
                const distance = Math.round(alert.distance);
                if (alert.severity === 'Fatal' || alert.severity === 'High') {
                    riskLevel = 'High';
                    message = `Alert! Accident location ahead in ${distance} meters. Extreme caution required!`;
                } else {
                    riskLevel = 'Medium';
                    message = `Caution! Accident reported ahead in ${distance} meters.`;
                }
            }

            // Fetch voice alert for this risk level
            if (message) {
                try {
                    const voiceRes = await fetch(`${API_BASE}/get_voice_alert.php?risk_level=${riskLevel}`);
                    const voiceData = await voiceRes.json();

                    if (voiceData.success) {
                        const isUrgent = riskLevel === 'High';
                        showAlert(voiceData.message, isUrgent);
                        speakWithTone(voiceData.message, voiceData.speech_rate, voiceData.speech_pitch);
                    } else {
                        // Fallback to original message
                        showAlert(message, riskLevel === 'High');
                        speak(message);
                    }
                } catch (e) {
                    // Fallback to original message
                    showAlert(message, riskLevel === 'High');
                    speak(message);
                }

                State.alertedLocations.set(alertKey, now);
                State.lastSpeakTime = now;
                break; // Only alert for closest one at a time
            }
        }

    } catch (error) {
        console.error('Proximity check error:', error);
    }
}

// Scout for risks in the upcoming segment of the path
async function scoutPathAhead(currentLatLng) {
    if (!State.routePath || State.routePath.length === 0) return;

    const now = Date.now();
    if (now - State.lastRerouteTime < 15000) return; // 15s cooldown between reroute checks

    // 1. Find the current position index on the routePath
    let currentIndex = -1;
    let minDist = Infinity;

    // Find closest point on path to current location
    for (let i = 0; i < State.routePath.length; i++) {
        const d = calculateDistance(currentLatLng.lat, currentLatLng.lng, State.routePath[i][0], State.routePath[i][1]);
        if (d < minDist) {
            minDist = d;
            currentIndex = i;
        }
    }

    if (currentIndex === -1) return;

    // 2. Scan ahead (approx 500m or next 20 points)
    const scanSegment = State.routePath.slice(currentIndex + 1, currentIndex + 30);
    if (scanSegment.length === 0) return;

    // 3. Get all training data points (cached or fetch)
    const res = await fetch(`${API_BASE}/get_training_accidents.php`);
    const json = await res.json();
    if (!json.success || !json.accidents) return;

    const highRiskPoints = json.accidents.filter(a => a.risk_level === 'High');

    // 4. Check if any upcoming path points are too close to high-risk points
    let dangerFound = false;
    for (const pathPt of scanSegment) {
        for (const riskPt of highRiskPoints) {
            const dist = calculateDistance(pathPt[0], pathPt[1], riskPt.latitude, riskPt.longitude);
            if (dist < 150) { // If path goes within 150m of a HIGH risk training point
                dangerFound = true;
                break;
            }
        }
        if (dangerFound) break;
    }

    if (dangerFound) {
        console.warn("High risk detected on upcoming path! Triggering smart reroute...");
        await triggerSmartReroute();
    }
}

async function triggerSmartReroute() {
    if (State.isRerouting) return;
    State.isRerouting = true;
    State.lastRerouteTime = Date.now();

    try {
        const start = State.userMarker.getLatLng();
        const end = State.endLatLng;
        if (!start || !end) {
            State.isRerouting = false;
            return;
        }

        showAlert("High risk ahead. Searching for safer route...", true);
        speak("Higher risk detected ahead. Rerouting to a safer path.");

        // Fetch alternatives from OSRM
        const osrm = `https://router.project-osrm.org/route/v1/driving/${start.lng},${start.lat};${end.lng},${end.lat}?overview=full&geometries=geojson&alternatives=true`;
        const res = await fetch(osrm);
        const data = await res.json();

        if (data.code !== 'Ok' || data.routes.length < 2) {
            console.log("No alternative routes found to avoid risk.");
            State.isRerouting = false;
            return;
        }

        // Evaluate routes
        const alternatives = data.routes.slice(1);
        const bestAlt = alternatives[0];
        const newCoords = bestAlt.geometry.coordinates.map(c => [c[1], c[0]]);

        // Update active route
        State.routePath = newCoords;
        if (State.routePolyline) State.map.removeLayer(State.routePolyline);

        State.routePolyline = L.polyline(newCoords, {
            color: '#34A853', // Green for safer reroute
            weight: 8,
            opacity: 0.9,
            dashArray: '10, 10'
        }).addTo(State.map);

        // Flash and then settle
        const el = State.routePolyline.getElement();
        if (el) el.classList.add('route-update-flash');

        setTimeout(() => {
            if (State.routePolyline) {
                const el2 = State.routePolyline.getElement();
                if (el2) el2.classList.remove('route-update-flash');
                State.routePolyline.setStyle({ color: '#4285F4', weight: 6, dashArray: null });
            }
        }, 3000);

        // Update stats
        document.getElementById('dist-val').innerText = (bestAlt.distance / 1000).toFixed(1) + " km";
        document.getElementById('time-val').innerText = (bestAlt.duration / 60).toFixed(0) + " min";

        showRerouteBadge("Safer Route Found", "fa-check-circle");
        showAlert("Route updated successfully!", false);

    } catch (e) {
        console.error("Rerouting failed", e);
    } finally {
        State.isRerouting = false;
        setTimeout(() => {
            const badge = document.getElementById('reroute-badge');
            if (badge) badge.style.display = 'none';
        }, 5000);
    }
}

function showRerouteBadge(text, iconClass) {
    let badge = document.getElementById('reroute-badge');
    if (!badge) {
        badge = document.createElement('div');
        badge.id = 'reroute-badge';
        document.body.appendChild(badge);
    }
    badge.innerHTML = `<i class="fas ${iconClass}"></i> ${text}`;
    badge.style.display = 'flex';
}

// Check proximity to training data points
async function checkTrainingDataProximity(currentLatLng, now) {
    try {
        // Fetch training accidents from API
        const res = await fetch(`${API_BASE}/get_training_accidents.php`);
        const json = await res.json();

        if (!json.success || !json.accidents) return;

        // Calculate distances to all training points
        const nearbyTrainingPoints = json.accidents
            .map(accident => {
                const distance = calculateDistance(
                    currentLatLng.lat,
                    currentLatLng.lng,
                    accident.latitude,
                    accident.longitude
                );
                return { ...accident, distance };
            })
            .filter(accident => accident.distance <= 300) // Within 300 meters
            .sort((a, b) => a.distance - b.distance); // Closest first

        // Alert for closest training point if any
        if (nearbyTrainingPoints.length > 0) {
            const closest = nearbyTrainingPoints[0];
            const trainingKey = `training_${closest.id}`;

            // Check cooldown (60 seconds)
            const lastAlert = State.alertedLocations.get(trainingKey);
            if (!lastAlert || (now - lastAlert) >= 60000) {
                const distance = Math.round(closest.distance);
                const riskLevel = closest.risk_level;

                // Fetch voice alert message
                try {
                    const voiceRes = await fetch(`${API_BASE}/get_voice_alert.php?risk_level=${riskLevel}`);
                    const voiceData = await voiceRes.json();

                    if (voiceData.success) {
                        // Customize message to mention it's a learned pattern
                        const customMessage = `Approaching training data location. ${voiceData.message}`;
                        const isUrgent = riskLevel === 'High';

                        showAlert(`📍 ${distance}m ahead - ML Training Point: ${riskLevel} Risk (${closest.weather_condition}, ${closest.road_condition}, ${closest.traffic_volume} traffic)`, isUrgent);
                        speakWithTone(customMessage, voiceData.speech_rate, voiceData.speech_pitch);

                        State.alertedLocations.set(trainingKey, now);
                        State.lastSpeakTime = now;
                    }
                } catch (e) {
                    console.warn('Training data voice alert failed', e);
                }
            }
        }
    } catch (error) {
        console.warn('Training data proximity check failed:', error);
    }
}

// Helper function to calculate distance between two coordinates (Haversine formula)
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000; // Earth's radius in meters
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;

    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLng / 2) * Math.sin(dLng / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c; // Distance in meters
}

function showAlert(msg, isHigh) {
    const el = document.getElementById('alert-toast');
    document.getElementById('alert-msg').innerText = msg;
    el.className = isHigh ? 'high' : '';
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

function speak(text) {
    if (!State.audioEnabled) return;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(new SpeechSynthesisUtterance(text));
}

// Enhanced speak function with tone control
function speakWithTone(text, rate = 1.0, pitch = 1.0) {
    if (!State.audioEnabled) return;
    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = rate;    // Speed: 0.1 to 10 (1 is normal)
    utterance.pitch = pitch;  // Pitch: 0 to 2 (1 is normal)
    utterance.volume = 1.0;   // Volume: 0 to 1

    window.speechSynthesis.speak(utterance);
}

// Update ML risk indicator in UI
function updateMLRiskIndicator(riskLevel, confidence) {
    // Create or update ML risk badge in nav status area
    let badge = document.getElementById('ml-risk-badge');
    if (!badge) {
        badge = document.createElement('div');
        badge.id = 'ml-risk-badge';
        badge.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        `;
        document.body.appendChild(badge);
    }

    // Set color based on risk level
    let bgColor, textColor;
    if (riskLevel === 'High') {
        bgColor = '#d32f2f';
        textColor = '#fff';
    } else if (riskLevel === 'Medium') {
        bgColor = '#f57c00';
        textColor = '#fff';
    } else {
        bgColor = '#388e3c';
        textColor = '#fff';
    }

    badge.style.backgroundColor = bgColor;
    badge.style.color = textColor;
    badge.innerHTML = `🤖 ML: ${riskLevel} Risk<br><small>${confidence}% confidence</small>`;
    badge.style.display = 'block';

    // Auto-hide after 10 seconds for Low risk
    if (riskLevel === 'Low') {
        setTimeout(() => {
            badge.style.display = 'none';
        }, 10000);
    }
}

function togglePanel() {
    console.log("Toggling panel...");
    const panel = document.querySelector('.control-panel');
    panel.classList.toggle('minimized');
}

// Auto-hide helper
function minimizePanel() {
    const panel = document.querySelector('.control-panel');
    if (!panel.classList.contains('minimized')) {
        togglePanel();
    }
}

// --- GPS & SPEED HELPERS ---

function updateSpeedUI(speedKmh) {
    const speedBadge = document.getElementById('speed-badge');
    if (speedBadge) {
        speedBadge.innerText = Math.round(speedKmh) + " km/h";
    }
}

function checkSpeedAlerts(speedKmh) {
    const now = Date.now();
    // 10 second cooldown for speed alerts to avoid spamming
    if (now - State.lastSpeedAlertTime < 10000) return;

    if (speedKmh > 80) {
        speak("Warning! You are overspeeding. Please slow down.");
        State.lastSpeedAlertTime = now;
    } else if (speedKmh > 5 && speedKmh < 20) {
        speak("You are driving very slow");
        State.lastSpeedAlertTime = now;
    } else if (speedKmh >= 40 && speedKmh <= 60) {
        // Safe speed alert - maybe less frequent (30 seconds)
        if (now - State.lastSpeedAlertTime > 30000) {
            speak("You are driving at a safe speed");
            State.lastSpeedAlertTime = now;
        }
    }
}

async function updateLocationArea(latlng) {
    const now = Date.now();

    // 1. First, check area safety status (always check frequency for UI responsiveness)
    await checkAreaSafety(latlng);

    // 2. Update Weather (every 30 minutes to save API, or on first lock)
    if (now - State.lastWeatherUpdate > 1800000 || !State.currentWeather) {
        await updateWeather(latlng);
    }

    // 3. Then, update the address name (less frequent to save API calls)
    if (now - State.lastLocationUpdate < 15000 && document.getElementById('location-area').querySelector('span').innerText !== "Initializing GPS...") {
        return;
    }

    try {
        const geoUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&zoom=18&addressdetails=1`;
        const res = await fetch(geoUrl);
        const data = await res.json();

        if (data && data.display_name) {
            const road = data.address.road || data.address.suburb || data.address.city || "Unknown Road";
            const area = `[${State.currentAreaRisk}] ${road}`;
            document.getElementById('location-area').querySelector('span').innerText = area;
            State.lastLocationUpdate = now;
        }
    } catch (e) {
        console.warn("Reverse Geocode Fail", e);
    }
}

async function checkAreaSafety(latlng) {
    try {
        // Query nearby risk zones and training data
        const res = await fetch(`${API_BASE}/get_nearby_alerts.php?lat=${latlng.lat}&lng=${latlng.lng}&radius=200`);
        const data = await res.json();

        let riskLevel = 'Safe';

        // ML Prediction is the highest priority for area status
        if (data.ml_prediction && data.ml_prediction.risk_level === 'High') {
            riskLevel = 'Danger';
        } else if (data.ml_prediction && data.ml_prediction.risk_level === 'Medium') {
            riskLevel = 'Warning';
        } else if (data.alerts && data.alerts.length > 0) {
            // Check manual database alerts
            const hasHigh = data.alerts.some(a => a.severity === 'High' || a.severity === 'Fatal');
            riskLevel = hasHigh ? 'Danger' : 'Warning';
        }

        // Update UI if status changed
        const areaEl = document.getElementById('location-area');
        if (State.currentAreaRisk !== riskLevel) {
            const oldRisk = State.currentAreaRisk;
            State.currentAreaRisk = riskLevel;

            // Update CSS Classes
            areaEl.classList.remove('safe', 'warning', 'danger');
            areaEl.classList.add(riskLevel.toLowerCase());

            // Voice Alert for transition
            if (riskLevel === 'Danger') {
                speak("Attention! You are entering a High Incident Area. Use extreme caution.");
            } else if (riskLevel === 'Warning' && oldRisk === 'Safe') {
                speak("Notice: Entering moderate risk zone.");
            } else if (riskLevel === 'Safe' && oldRisk !== 'Safe') {
                speak("Area safety cleared. Drive safely.");
            }

            // Immediately update text if we have a road name
            const currentText = areaEl.querySelector('span').innerText;
            if (currentText !== "Initializing GPS...") {
                const roadName = currentText.includes(']') ? currentText.split('] ')[1] : currentText;
                areaEl.querySelector('span').innerText = `[${riskLevel}] ${roadName}`;
            }
        }
    } catch (e) {
        console.warn("Safety check failed", e);
    }
}

// --- WEATHER HELPERS ---

async function updateWeather(latlng) {
    try {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${latlng.lat}&longitude=${latlng.lng}&current_weather=true`;
        const res = await fetch(url);
        const data = await res.json();

        if (data && data.current_weather) {
            const temp = Math.round(data.current_weather.temperature);
            const code = data.current_weather.weathercode;
            const mood = getWeatherMood(code);

            const badge = document.getElementById('weather-badge');
            badge.innerHTML = `<i class="fas ${mood.icon}"></i> ${temp}°C ${mood.label}`;

            State.currentWeather = mood.label;
            State.lastWeatherUpdate = Date.now();

            // Check for hazardous weather alerts
            checkWeatherAlerts(mood.label);
        }
    } catch (e) {
        console.warn("Weather Update Fail", e);
    }
}

function checkWeatherAlerts(condition) {
    const dangerous = ['Rain', 'Storm', 'Fog', 'Snow'];
    if (dangerous.includes(condition)) {
        speak(`Caution: Hazardous weather detected. ${condition} ahead. Please drive carefully on wet or low-visibility roads.`);
    }
}

function getWeatherMood(code) {
    // WMO Weather interpretation codes
    if (code === 0) return { label: 'Clear', icon: 'fa-sun' };
    if (code <= 3) return { label: 'Cloudy', icon: 'fa-cloud' };
    if (code <= 48) return { label: 'Fog', icon: 'fa-smog' };
    if (code <= 67) return { label: 'Rain', icon: 'fa-cloud-showers-heavy' };
    if (code <= 77) return { label: 'Snow', icon: 'fa-snowflake' };
    if (code <= 82) return { label: 'Rain', icon: 'fa-cloud-rain' };
    if (code <= 99) return { label: 'Storm', icon: 'fa-bolt' };
    return { label: 'Cloudy', icon: 'fa-cloud' };
}
