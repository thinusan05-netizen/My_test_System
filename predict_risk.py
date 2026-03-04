"""
Intelligent Road Safety System
Real-time Risk Prediction API
Accepts location and conditions, returns ML-predicted risk level
"""

import pickle
import numpy as np
import sys
import json
from math import radians, sin, cos, sqrt, atan2

# ---------------------------------------------------------------------------
# Haversine helper – mirrors the distance formula used in training
# ---------------------------------------------------------------------------
EARTH_RADIUS_KM = 6371.0

def haversine_km(lat1, lng1, lat2, lng2):
    """Return great-circle distance in km between two (lat, lng) points."""
    lat1, lng1, lat2, lng2 = map(radians, [lat1, lng1, lat2, lng2])
    dlat = lat2 - lat1
    dlng = lng2 - lng1
    a = sin(dlat / 2)**2 + cos(lat1) * cos(lat2) * sin(dlng / 2)**2
    return EARTH_RADIUS_KM * 2 * atan2(sqrt(a), sqrt(1 - a))

def load_model():
    """Load trained model and encoders"""
    try:
        with open('accident_risk_model.pkl', 'rb') as f:
            model = pickle.load(f)
        with open('encoders.pkl', 'rb') as f:
            encoders = pickle.load(f)
        return model, encoders
    except FileNotFoundError:
        print(json.dumps({
            'success': False,
            'error': 'Model not found. Please run train_model.py first.'
        }))
        sys.exit(1)

def predict_risk(latitude, longitude, weather='Clear', road='Dry', traffic='Medium'):
    """
    Predict accident risk level for given location and conditions
    
    Args:
        latitude (float): Latitude coordinate
        longitude (float): Longitude coordinate
        weather (str): Weather condition (Clear, Rainy, Cloudy)
        road (str): Road condition (Dry, Wet)
        traffic (str): Traffic volume (Low, Medium, High)
    
    Returns:
        dict: Prediction result with risk level and confidence
    """
    model, encoders = load_model()
    
    try:
        # Map weather types to known categories if needed
        known_weather = list(encoders['weather'].classes_)
        if weather not in known_weather:
            # Fallback mapping for unknown weather types
            weather_map = {
                'Foggy': 'Cloudy',
                'Stormy': 'Rainy',
                'Sunny': 'Clear',
                'Snowy': 'Rainy',
                'Windy': 'Cloudy'
            }
            weather = weather_map.get(weather, 'Clear')
        
        # Encode categorical features
        weather_encoded = encoders['weather'].transform([weather])[0]
        road_encoded = encoders['road'].transform([road])[0]
        traffic_encoded = encoders['traffic'].transform([traffic])[0]
        
        # Calculate spatial feature – Haversine distance (km) from Colombo
        colombo_lat, colombo_lng = encoders['colombo_coords']
        dist_from_colombo = haversine_km(latitude, longitude,
                                         colombo_lat, colombo_lng)
        
        # Calculate interaction features
        weather_road_interaction = weather_encoded * road_encoded
        traffic_weather_interaction = traffic_encoded * weather_encoded
        
        # Prepare feature vector (must match training order)
        features = np.array([[
            latitude, longitude,
            weather_encoded, road_encoded, traffic_encoded,
            dist_from_colombo,
            weather_road_interaction, traffic_weather_interaction
        ]])
        
        # Predict
        prediction = model.predict(features)[0]
        probabilities = model.predict_proba(features)[0]
        
        # Decode risk level
        risk_level = encoders['risk'].inverse_transform([prediction])[0]
        confidence = float(probabilities[prediction])
        
        return {
            'success': True,
            'risk_level': risk_level,
            'confidence': round(confidence * 100, 2),
            'probabilities': {
                encoders['risk'].classes_[i]: round(float(probabilities[i]) * 100, 2)
                for i in range(len(probabilities))
            },
            'location': {
                'latitude': latitude,
                'longitude': longitude,
                'weather': weather,
                'road': road,
                'traffic': traffic
            }
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

if __name__ == '__main__':
    # Command-line interface
    if len(sys.argv) < 3:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python predict_risk.py <latitude> <longitude> [weather] [road] [traffic]'
        }))
        sys.exit(1)
    
    lat = float(sys.argv[1])
    lng = float(sys.argv[2])
    weather = sys.argv[3] if len(sys.argv) > 3 else 'Clear'
    road = sys.argv[4] if len(sys.argv) > 4 else 'Dry'
    traffic = sys.argv[5] if len(sys.argv) > 5 else 'Medium'
    
    result = predict_risk(lat, lng, weather, road, traffic)
    print(json.dumps(result, indent=2))
