import pandas as pd
import pickle
import numpy as np
from math import radians, sin, cos, sqrt, atan2
from sklearn.model_selection import train_test_split, cross_val_score, GridSearchCV, StratifiedKFold
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.svm import SVC
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.metrics import accuracy_score, classification_report
from sklearn.pipeline import Pipeline
import warnings
warnings.filterwarnings('ignore')

try:
    from xgboost import XGBClassifier
    XGBOOST_AVAILABLE = True
except ImportError:
    XGBOOST_AVAILABLE = False
    print("[WARN] xgboost not installed – skipping XGBoost. Run: pip install xgboost")

print("=" * 65)
print("  INTELLIGENT ROAD SAFETY – ML MODEL TRAINING (Enhanced)")
print("=" * 65)

# ---------------------------------------------------------------------------
# 1. Load dataset
# ---------------------------------------------------------------------------
df = pd.read_csv('accident_data.csv')
print(f"\n[OK] Loaded {len(df)} training examples")
print(f"     Risk Distribution: {dict(df['risk_level'].value_counts())}")

# ---------------------------------------------------------------------------
# 2. Encode categorical features
#    Pre-fit encoders with ALL possible categories so prediction never breaks
# ---------------------------------------------------------------------------
le_weather = LabelEncoder()
le_road    = LabelEncoder()
le_traffic = LabelEncoder()
le_risk    = LabelEncoder()

all_weather = ['Clear', 'Cloudy', 'Foggy', 'Rainy', 'Stormy']
all_road    = ['Dry', 'Wet']
all_traffic = ['High', 'Low', 'Medium']
all_risk    = ['High', 'Low', 'Medium']

le_weather.fit(all_weather)
le_road.fit(all_road)
le_traffic.fit(all_traffic)
le_risk.fit(all_risk)

df['weather_encoded']  = le_weather.transform(df['weather_condition'])
df['road_encoded']     = le_road.transform(df['road_condition'])
df['traffic_encoded']  = le_traffic.transform(df['traffic_volume'])
df['risk_encoded']     = le_risk.transform(df['risk_level'])

# ---------------------------------------------------------------------------
# 3. Spatial feature – Haversine distance from Colombo (replaces Euclidean)
#    Haversine gives the great-circle distance in km, which is geographically
#    accurate over spherical Earth instead of flat-plane approximation.
# ---------------------------------------------------------------------------
colombo_lat, colombo_lng = 6.9271, 79.8612
EARTH_RADIUS_KM = 6371.0

def haversine_km(lat1, lng1, lat2, lng2):
    """Return the great-circle distance in km between two (lat, lng) points."""
    lat1, lng1, lat2, lng2 = map(radians, [lat1, lng1, lat2, lng2])
    dlat = lat2 - lat1
    dlng = lng2 - lng1
    a = sin(dlat / 2)**2 + cos(lat1) * cos(lat2) * sin(dlng / 2)**2
    return EARTH_RADIUS_KM * 2 * atan2(sqrt(a), sqrt(1 - a))

df['dist_from_colombo'] = df.apply(
    lambda row: haversine_km(row['latitude'], row['longitude'],
                             colombo_lat, colombo_lng), axis=1
)

# Interaction features
df['weather_road_interaction']    = df['weather_encoded'] * df['road_encoded']
df['traffic_weather_interaction'] = df['traffic_encoded'] * df['weather_encoded']

feature_cols = [
    'latitude', 'longitude',
    'weather_encoded', 'road_encoded', 'traffic_encoded',
    'dist_from_colombo',
    'weather_road_interaction', 'traffic_weather_interaction'
]

X = df[feature_cols]
y = df['risk_encoded']

print(f"\n[OK] Engineered {len(feature_cols)} features")
print(f"     - dist_from_colombo now uses Haversine formula (km)")

# ---------------------------------------------------------------------------
# 4. Train / test split
# ---------------------------------------------------------------------------
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42, stratify=y
)

# ---------------------------------------------------------------------------
# 5. Algorithm comparison
#    Compare 4 classifiers on 5-fold CV before choosing the best one.
# ---------------------------------------------------------------------------
print("\n" + "=" * 65)
print("  ALGORITHM COMPARISON (5-Fold Cross-Validation)")
print("=" * 65)

cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)

candidates = {
    'Logistic Regression': Pipeline([
        ('scaler', StandardScaler()),
        ('clf', LogisticRegression(max_iter=1000, random_state=42,
                                   class_weight='balanced'))
    ]),
    'Random Forest': RandomForestClassifier(
        n_estimators=100, random_state=42, class_weight='balanced'
    ),
    'SVM': Pipeline([
        ('scaler', StandardScaler()),
        ('clf', SVC(probability=True, random_state=42, class_weight='balanced'))
    ]),
}
if XGBOOST_AVAILABLE:
    candidates['XGBoost'] = XGBClassifier(
        use_label_encoder=False, eval_metric='mlogloss',
        random_state=42, verbosity=0
    )

results = {}
for name, clf in candidates.items():
    scores = cross_val_score(clf, X_train, y_train, cv=cv, scoring='accuracy', n_jobs=-1)
    results[name] = scores
    print(f"  {name:<25} CV Acc: {scores.mean():.4f} ± {scores.std():.4f}")

best_name = max(results, key=lambda k: results[k].mean())
print(f"\n[WINNER] Best algorithm: {best_name}  (CV Acc = {results[best_name].mean():.4f})")

# ---------------------------------------------------------------------------
# 6. Hyperparameter tuning with GridSearchCV on the winning algorithm
# ---------------------------------------------------------------------------
print("\n" + "=" * 65)
print(f"  HYPERPARAMETER TUNING (GridSearchCV) – {best_name}")
print("=" * 65)

param_grids = {
    'Logistic Regression': {
        'clf__C': [0.01, 0.1, 1, 10, 100],
        'clf__solver': ['lbfgs', 'saga']
    },
    'Random Forest': {
        'n_estimators': [50, 100, 200],
        'max_depth': [5, 10, 15, None],
        'min_samples_split': [2, 5, 10],
        'max_features': ['sqrt', 'log2']
    },
    'SVM': {
        'clf__C': [0.1, 1, 10],
        'clf__kernel': ['rbf', 'linear'],
        'clf__gamma': ['scale', 'auto']
    },
    'XGBoost': {
        'n_estimators': [50, 100, 200],
        'max_depth': [3, 6, 9],
        'learning_rate': [0.01, 0.1, 0.2],
        'subsample': [0.8, 1.0]
    }
}

base_clf    = candidates[best_name]
param_grid  = param_grids[best_name]

grid_search = GridSearchCV(
    estimator=base_clf,
    param_grid=param_grid,
    cv=cv,
    scoring='accuracy',
    n_jobs=-1,
    verbose=1
)
grid_search.fit(X_train, y_train)

print(f"\n[OK] Best params: {grid_search.best_params_}")
print(f"[OK] Best CV accuracy: {grid_search.best_score_:.4f}")

model = grid_search.best_estimator_

# ---------------------------------------------------------------------------
# 7. Final evaluation on held-out test set
# ---------------------------------------------------------------------------
print("\n" + "=" * 65)
print("  FINAL MODEL EVALUATION (Test Set)")
print("=" * 65)

y_pred   = model.predict(X_test)
accuracy = accuracy_score(y_test, y_pred)
print(f"\n[OK] Test Set Accuracy : {accuracy:.4f}  ({accuracy:.2%})")

cv_final = cross_val_score(model, X, y, cv=5, scoring='accuracy')
print(f"[OK] Full 5-Fold CV    : {cv_final.mean():.4f} ± {cv_final.std():.4f}")

print("\n" + "-" * 65)
print("CLASSIFICATION REPORT")
print("-" * 65)
risk_labels = le_risk.classes_
print(classification_report(y_test, y_pred, target_names=risk_labels, zero_division=0))

# Feature importance (only for tree-based models that expose it directly)
try:
    raw = model  # may be Pipeline or direct estimator
    importances = (raw.named_steps['clf'].feature_importances_
                   if hasattr(raw, 'named_steps')
                   else raw.feature_importances_)
    fi = pd.DataFrame({'feature': feature_cols, 'importance': importances})
    fi = fi.sort_values('importance', ascending=False)
    print("-" * 65)
    print("FEATURE IMPORTANCE")
    print("-" * 65)
    for _, row in fi.iterrows():
        print(f"  {row['feature']:<35} {row['importance']:.4f}")
except AttributeError:
    pass  # Logistic Regression / SVM don't have feature_importances_

# ---------------------------------------------------------------------------
# 8. Save model & encoders
#    colombo_coords is retained so predict_risk.py can reproduce the feature
# ---------------------------------------------------------------------------
with open('accident_risk_model.pkl', 'wb') as f:
    pickle.dump(model, f)

encoders_dict = {
    'weather'        : le_weather,
    'road'           : le_road,
    'traffic'        : le_traffic,
    'risk'           : le_risk,
    'feature_cols'   : feature_cols,
    'colombo_coords' : (colombo_lat, colombo_lng),
    'best_algorithm' : best_name,
    'haversine'      : True          # flag so predict_risk.py uses Haversine
}

with open('encoders.pkl', 'wb') as f:
    pickle.dump(encoders_dict, f)

print("\n" + "=" * 65)
print(f"[DONE] Best model   : {best_name}")
print(f"[DONE] Test accuracy: {accuracy:.2%}")
print("[DONE] Saved  >>  accident_risk_model.pkl  &  encoders.pkl")
print("=" * 65)
