<?php
/**
 * Intelligent Road Safety System
 * Trigger ML Model Training from Admin Panel
 * Syncs database → CSV → Retrains model, returns JSON results
 */

session_start();

// Auth check - support both session formats
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (!isset($_SESSION['admin_logged_in'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

header('Content-Type: application/json');

// Increase execution time for training
set_time_limit(120);

$steps = [];
$mlDir = realpath(dirname(__FILE__) . '/../../machine_learning');

if (!$mlDir) {
    echo json_encode([
        'success' => false,
        'error' => 'Machine learning directory not found'
    ]);
    exit;
}

// ─── Step 1: Export database accidents to training CSV ───
$exportUrl = 'http://localhost/final_project/backend/api/export_to_training.php';

try {
    $exportResult = file_get_contents($exportUrl);
    $exportData = json_decode($exportResult, true);

    if ($exportData && $exportData['success']) {
        $steps[] = [
            'step' => 1,
            'name' => 'Export to CSV',
            'status' => 'success',
            'message' => "Exported {$exportData['new_records_added']} new records to training CSV",
            'total_accidents' => $exportData['total_accidents'],
            'new_records' => $exportData['new_records_added']
        ];
    }
    else {
        $steps[] = [
            'step' => 1,
            'name' => 'Export to CSV',
            'status' => 'warning',
            'message' => $exportData['error'] ?? 'Export returned unexpected result'
        ];
    }
}
catch (Exception $e) {
    $steps[] = [
        'step' => 1,
        'name' => 'Export to CSV',
        'status' => 'error',
        'message' => 'Export failed: ' . $e->getMessage()
    ];
}

// ─── Step 2: Run Python training script ───
$pythonCmd = "python";
$trainScript = $mlDir . DIRECTORY_SEPARATOR . "train_model.py";

if (!file_exists($trainScript)) {
    echo json_encode([
        'success' => false,
        'error' => 'train_model.py not found at: ' . $trainScript,
        'steps' => $steps
    ]);
    exit;
}

// Execute training
$command = escapeshellcmd($pythonCmd) . ' ' . escapeshellarg($trainScript) . ' 2>&1';
$output = [];
$returnCode = 0;

// Change to ML directory so relative paths in train_model.py work
$originalDir = getcwd();
chdir($mlDir);

exec($command, $output, $returnCode);

chdir($originalDir);

$outputText = implode("\n", $output);

if ($returnCode === 0) {
    // Parse accuracy from output
    $accuracy = '';
    $cvAccuracy = '';
    $trainingExamples = '';

    foreach ($output as $line) {
        if (strpos($line, 'Test Set Accuracy') !== false) {
            preg_match('/(\d+\.\d+%)/', $line, $matches);
            if ($matches)
                $accuracy = $matches[1];
        }
        if (strpos($line, 'Cross-Validation Accuracy') !== false) {
            preg_match('/(\d+\.\d+%)/', $line, $matches);
            if ($matches)
                $cvAccuracy = $matches[1];
        }
        if (strpos($line, 'training examples') !== false) {
            preg_match('/(\d+)/', $line, $matches);
            if ($matches)
                $trainingExamples = $matches[1];
        }
    }

    $steps[] = [
        'step' => 2,
        'name' => 'Model Training',
        'status' => 'success',
        'message' => 'Model trained successfully!',
        'accuracy' => $accuracy,
        'cv_accuracy' => $cvAccuracy,
        'training_examples' => $trainingExamples
    ];
}
else {
    $steps[] = [
        'step' => 2,
        'name' => 'Model Training',
        'status' => 'error',
        'message' => 'Training failed',
        'output' => $outputText
    ];

    echo json_encode([
        'success' => false,
        'error' => 'Training script failed with exit code: ' . $returnCode,
        'steps' => $steps,
        'raw_output' => $outputText
    ]);
    exit;
}

// ─── Step 3: Verify model files ───
$modelFile = $mlDir . DIRECTORY_SEPARATOR . 'accident_risk_model.pkl';
$encodersFile = $mlDir . DIRECTORY_SEPARATOR . 'encoders.pkl';

$modelExists = file_exists($modelFile);
$encodersExists = file_exists($encodersFile);

$steps[] = [
    'step' => 3,
    'name' => 'Verification',
    'status' => ($modelExists && $encodersExists) ? 'success' : 'error',
    'message' => ($modelExists && $encodersExists)
    ? 'Model files verified successfully'
    : 'Model files missing after training',
    'model_size' => $modelExists ? round(filesize($modelFile) / 1024, 1) . ' KB' : 'N/A',
    'model_updated' => $modelExists ? date('Y-m-d H:i:s', filemtime($modelFile)) : 'N/A'
];

// Count training data
$csvPath = $mlDir . DIRECTORY_SEPARATOR . 'accident_data.csv';
$datasetSize = 0;
if (file_exists($csvPath)) {
    $datasetSize = count(file($csvPath)) - 1; // Exclude header
}

echo json_encode([
    'success' => true,
    'message' => 'Auto-training completed successfully!',
    'steps' => $steps,
    'summary' => [
        'dataset_size' => $datasetSize,
        'accuracy' => $accuracy,
        'cv_accuracy' => $cvAccuracy,
        'model_size' => $modelExists ? round(filesize($modelFile) / 1024, 1) . ' KB' : 'N/A',
        'trained_at' => date('Y-m-d H:i:s')
    ]
]);
?>
