<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Accident CSV - Quick Upload</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .upload-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: #d32f2f;
        }
        .info-box ul {
            margin: 10px 0 0 20px;
            font-size: 14px;
        }
        .info-box li {
            margin: 5px 0;
            color: #555;
        }
        .file-input-wrapper {
            position: relative;
            margin: 25px 0;
        }
        .file-input-label {
            display: block;
            padding: 50px 20px;
            border: 3px dashed #ddd;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .file-input-label:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .file-input-label i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .file-input-label p {
            color: #666;
            font-size: 16px;
        }
        #csv-file {
            display: none;
        }
        .file-name {
            margin: 15px 0;
            padding: 15px;
            background: #e8f5e9;
            border-radius: 8px;
            display: none;
        }
        .file-name.show {
            display: block;
        }
        .file-name i {
            color: #4caf50;
            margin-right: 10px;
        }
        .upload-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .upload-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .progress-container {
            margin: 20px 0;
            display: none;
        }
        .progress-container.show {
            display: block;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #81c784);
            width: 0%;
            transition: width 0.3s ease;
        }
        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            display: none;
        }
        .status-message.show {
            display: block;
        }
        .status-message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .status-message.error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Road Safety Admin</span>
            </div>
            <nav>
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="manage_zones.php"><i class="fas fa-map-marked-alt"></i> Risk Zones</a>
                <a href="manage_accidents.php"><i class="fas fa-car-crash"></i> Accidents</a>
                <a href="manage_training_data.php"><i class="fas fa-brain"></i> Training Data</a>
                <a href="upload_csv.php" class="active"><i class="fas fa-upload"></i> Bulk Upload</a>
                <a href="manage_users.php"><i class="fas fa-users"></i> Users</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="upload-container" style="max-width: 800px; margin: 0 auto;">
        <h1><i class="fas fa-file-csv"></i> Upload Accident CSV</h1>
        <p class="subtitle">Bulk import accident data for ML training</p>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> CSV Format Required</h3>
            <code>latitude,longitude,weather_condition,road_condition,traffic_volume,risk_level</code>
            <p style="margin: 10px 0 5px 0;"><strong>Example:</strong></p>
            <code>6.927079,79.861244,Rainy,Wet,High,High</code>
            <ul>
                <li><strong>weather_condition:</strong> Clear, Rainy, Cloudy</li>
                <li><strong>road_condition:</strong> Dry, Wet</li>
                <li><strong>traffic_volume:</strong> Low, Medium, High</li>
                <li><strong>risk_level:</strong> Low, Medium, High</li>
            </ul>
        </div>

        <form id="upload-form">
            <div class="file-input-wrapper">
                <label for="csv-file" class="file-input-label">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Click to select CSV file</strong><br>or drag and drop here</p>
                </label>
                <input type="file" id="csv-file" name="csv_file" accept=".csv" required>
            </div>

            <div class="file-name" id="file-name">
                <i class="fas fa-file-csv"></i>
                <span id="file-name-text"></span>
            </div>

            <div class="progress-container" id="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <p id="progress-text" style="text-align: center; color: #666;">Uploading...</p>
            </div>

            <div class="status-message" id="status-message"></div>

            <button type="submit" class="upload-btn" id="upload-btn">
                <i class="fas fa-upload"></i> Upload & Import to Training Dataset
            </button>
        </form>

        <a href="manage_accidents.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Manage Accidents
        </a>
    </div>

    <script>
        const fileInput =document.getElementById('csv-file');
        const fileName = document.getElementById('file-name');
        const fileNameText = document.getElementById('file-name-text');
        const uploadForm = document.getElementById('upload-form');
        const progressContainer = document.getElementById('progress-container');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const statusMessage = document.getElementById('status-message');
        const uploadBtn = document.getElementById('upload-btn');

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileNameText.textContent = this.files[0].name;
                fileName.classList.add('show');
                statusMessage.classList.remove('show');
            }
        });

        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const file = fileInput.files[0];
            if (!file) {
                alert('Please select a CSV file');
                return;
            }

            // Show progress
            progressContainer.classList.add('show');
            uploadBtn.disabled = true;
            statusMessage.classList.remove('show');
            progressFill.style.width = '30%';
            progressText.textContent = 'Uploading file...';

            const formData = new FormData();
            formData.append('csv_file', file);

            try {
                progressFill.style.width = '60%';
                progressText.textContent = 'Processing records...';

                const response = await fetch('../api/upload_accident_csv.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                progressFill.style.width = '100%';
                progressText.textContent = 'Complete!';

                if (result.success) {
                    statusMessage.className = 'status-message success show';
                    statusMessage.innerHTML = `
                        <strong><i class="fas fa-check-circle"></i> Upload Successful!</strong><br><br>
                        Total rows processed: ${result.total_rows}<br>
                        Records added: ${result.added}<br>
                        Records skipped: ${result.skipped}
                        ${result.errors && result.errors.length > 0 ? '<br><br><strong>Errors:</strong><br>' + result.errors.join('<br>') : ''}
                        <br><br>
                        <button onclick="autoTrainModel(this)" class="upload-btn" style="margin-top: 10px; background: linear-gradient(135deg, #4caf50, #45a049);">
                            <i class="fas fa-brain"></i> Auto Train ML Model Now
                        </button>
                        <div id="train-status" style="margin-top: 15px; display: none;"></div>
                    `;

                    // Reset form after 3 seconds
                    setTimeout(() => {
                        uploadForm.reset();
                        fileName.classList.remove('show');
                        progressContainer.classList.remove('show');
                        uploadBtn.disabled = false;
                        progressFill.style.width = '0%';
                    }, 5000);
                } else {
                    throw new Error(result.error || 'Upload failed');
                }
            } catch (error) {
                progressFill.style.width = '100%';
                progressFill.style.background = '#f44336';
                
                statusMessage.className = 'status-message error show';
                statusMessage.innerHTML = `
                    <strong><i class="fas fa-exclamation-circle"></i> Upload Failed!</strong><br><br>
                    ${error.message}
                `;
                
                uploadBtn.disabled = false;
            }
        });
    </script>

    <script>
        async function autoTrainModel(btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Training in progress...';

            const trainStatus = document.getElementById('train-status');
            trainStatus.style.display = 'block';
            trainStatus.style.padding = '15px';
            trainStatus.style.background = '#e3f2fd';
            trainStatus.style.borderRadius = '8px';
            trainStatus.style.borderLeft = '4px solid #2196f3';
            trainStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting data and training model... This may take a moment.';

            try {
                const response = await fetch('../api/trigger_training.php');
                const result = await response.json();

                if (result.success) {
                    trainStatus.style.background = '#e8f5e9';
                    trainStatus.style.borderLeft = '4px solid #4caf50';

                    let stepsHtml = '';
                    result.steps.forEach(step => {
                        const icon = step.status === 'success' ? '✓' : step.status === 'warning' ? '⚠' : '✗';
                        const color = step.status === 'success' ? '#4caf50' : step.status === 'warning' ? '#ff9800' : '#f44336';
                        stepsHtml += `<div style="margin: 4px 0;"><span style="color: ${color}; font-weight: bold;">${icon}</span> ${step.name}: ${step.message}</div>`;
                    });

                    trainStatus.innerHTML = `
                        <strong style="color: #2e7d32;"><i class="fas fa-check-circle"></i> Training Complete!</strong>
                        <div style="margin: 10px 0;">${stepsHtml}</div>
                        <div style="background: rgba(0,0,0,0.05); padding: 10px; border-radius: 6px; margin-top: 8px; font-size: 14px;">
                            <strong>Results:</strong> ${result.summary.dataset_size} examples · 
                            Accuracy: ${result.summary.accuracy} · 
                            Model: ${result.summary.model_size}
                        </div>
                    `;
                    btn.style.display = 'none';
                } else {
                    throw new Error(result.error || 'Training failed');
                }
            } catch (error) {
                trainStatus.style.background = '#ffebee';
                trainStatus.style.borderLeft = '4px solid #f44336';
                trainStatus.innerHTML = `<strong style="color: #c62828;"><i class="fas fa-exclamation-circle"></i> Training Failed</strong><br>${error.message}`;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-brain"></i> Retry Training';
            }
        }
    </script>
        </main>
    </div>
</body>
</html>
