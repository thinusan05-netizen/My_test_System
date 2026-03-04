from flask import Flask, request, jsonify
import pickle
import pandas as pd
import os
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# Load Model and Encoders
# Paths relative to where app.py is run (backend/api)
MODEL_PATH = '../../machine_learning/accident_risk_model.pkl'
ENCODERS_PATH = '../../machine_learning/encoders.pkl'

# Check if model exists
if not os.path.exists(MODEL_PATH):
    print(f"Model not found at {os.path.abspath(MODEL_PATH)}. Please train the model first.")
    model = None
    encoders = None
else:
    with open(MODEL_PATH, 'rb') as f:
        model = pickle.load(f)
    with open(ENCODERS_PATH, 'rb') as f:
        encoders = pickle.load(f)

@app.route('/predict', methods=['POST'])
def predict():
    if not model:
        return jsonify({'error': 'Model not loaded'}), 500

    data = request.json
    try:
        # Extract features
        lat = float(data.get('latitude'))
        lon = float(data.get('longitude'))
        weather = data.get('weather')
        road = data.get('road_condition')
        traffic = data.get('traffic_volume')

        # Encode categorical features
        # Note: In a real app, handle unseen labels (try/except or dedicated 'unknown' token)
        try:
            w_enc = encoders['weather'].transform([weather])[0]
        except ValueError:
            w_enc = encoders['weather'].transform([encoders['weather'].classes_[0]])[0] # Fallback
            
        try:
            r_enc = encoders['road'].transform([road])[0]
        except ValueError:
             r_enc = encoders['road'].transform([encoders['road'].classes_[0]])[0]

        try:
            t_enc = encoders['traffic'].transform([traffic])[0]
        except ValueError:
            t_enc = encoders['traffic'].transform([encoders['traffic'].classes_[0]])[0]

        # Predict
        prediction_encoded = model.predict([[lat, lon, w_enc, r_enc, t_enc]])[0]
        risk_level = encoders['risk'].inverse_transform([prediction_encoded])[0]

        return jsonify({'risk_level': risk_level})
    except Exception as e:
        return jsonify({'error': str(e)}), 400

if __name__ == '__main__':
    app.run(debug=True, port=5000)
