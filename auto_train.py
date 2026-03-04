"""
Intelligent Road Safety System
Auto-Trainer: Sync Database → CSV → Retrain Model
Complete workflow automation
"""

import subprocess
import sys
import json
import os
from pathlib import Path

print("=" * 70)
print("INTELLIGENT ROAD SAFETY - AUTO-TRAINER")
print("=" * 70)

# Get script directory
script_dir = Path(__file__).parent

# Step 1: Export database accidents to CSV
print("\n[STEP 1/3] Exporting database accidents to training CSV...")
print("-" * 70)

try:
    # Call PHP export script
    php_url = "http://localhost/final_project/backend/api/export_to_training.php"
    
    # Use curl or requests to call the API
    import urllib.request
    with urllib.request.urlopen(php_url) as response:
        result = json.loads(response.read().decode())
        
    if result['success']:
        print(f"✓ Export successful!")
        print(f"  Total accidents in database: {result['total_accidents']}")
        print(f"  New records added to CSV: {result['new_records_added']}")
        print(f"  CSV location: {result['csv_path']}")
    else:
        print(f"✗ Export failed: {result.get('error', 'Unknown error')}")
        sys.exit(1)
        
except Exception as e:
    print(f"✗ Export failed: {str(e)}")
    print("  Make sure XAMPP is running and the database is accessible")
    sys.exit(1)

# Step 2: Retrain the model
print("\n[STEP 2/3] Retraining ML model with updated data...")
print("-" * 70)

try:
    # Run training script
    train_script = script_dir / "train_model.py"
    result = subprocess.run(
        [sys.executable, str(train_script)],
        cwd=str(script_dir),
        capture_output=True,
        text=True
    )
    
    if result.returncode == 0:
        print(result.stdout)
    else:
        print(f"✗ Training failed:")
        print(result.stderr)
        sys.exit(1)
        
except Exception as e:
    print(f"✗ Training failed: {str(e)}")
    sys.exit(1)

# Step 3: Summary
print("\n[STEP 3/3] Verification")
print("-" * 70)

# Check if model files exist
model_file = script_dir / "accident_risk_model.pkl"
encoders_file = script_dir / "encoders.pkl"

if model_file.exists() and encoders_file.exists():
    print("✓ Model files verified")
    print(f"  - {model_file.name}")
    print(f"  - {encoders_file.name}")
else:
    print("✗ Model files not found!")
    sys.exit(1)

# Count training data
csv_file = script_dir / "accident_data.csv"
if csv_file.exists():
    with open(csv_file, 'r') as f:
        lines = len(f.readlines()) - 1  # Exclude header
    print(f"✓ Training dataset: {lines} examples")

print("\n" + "=" * 70)
print("AUTO-TRAINING COMPLETE!")
print("=" * 70)
print("\nNext steps:")
print("1. Refresh your web application")
print("2. New training data markers will appear on the map")
print("3. ML predictions will use the updated model")
print("\nURL: http://localhost/final_project/index.html")
print("=" * 70)
