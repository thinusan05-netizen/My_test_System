<?php
/**
 * Intelligent Road Safety System
 * Voice Alert Messages API
 * Returns appropriate voice message based on risk level
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get parameters
$riskLevel = isset($_GET['risk_level']) ? $_GET['risk_level'] : null;

if (!$riskLevel) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing risk_level parameter'
    ]);
    exit;
}

// Load voice messages from JSON file
$messagesFile = dirname(__FILE__) . '/../../frontend/voice_messages.json';

if (!file_exists($messagesFile)) {
    echo json_encode([
        'success' => false,
        'error' => 'Voice messages file not found'
    ]);
    exit;
}

$messagesJson = file_get_contents($messagesFile);
$messages = json_decode($messagesJson, true);

// Normalize risk level to lowercase key
$riskKey = strtolower(str_replace(' ', '_', $riskLevel)) . '_risk';

if (!isset($messages[$riskKey])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid risk level: ' . $riskLevel,
        'valid_levels' => ['High', 'Medium', 'Low']
    ]);
    exit;
}

// Get random message from array
$messageArray = $messages[$riskKey];
$randomMessage = $messageArray[array_rand($messageArray)];

// Determine tone based on risk level
$tone = 'friendly';
$speechRate = 1.0;
$speechPitch = 1.0;

if ($riskKey === 'high_risk') {
    $tone = 'urgent';
    $speechRate = 1.2; // Faster
    $speechPitch = 1.1; // Higher pitch
}
elseif ($riskKey === 'medium_risk') {
    $tone = 'cautious';
    $speechRate = 1.0; // Normal
    $speechPitch = 1.0;
}
else {
    $tone = 'friendly';
    $speechRate = 0.9; // Slower
    $speechPitch = 0.95;
}

echo json_encode([
    'success' => true,
    'risk_level' => $riskLevel,
    'message' => $randomMessage,
    'tone' => $tone,
    'speech_rate' => $speechRate,
    'speech_pitch' => $speechPitch,
    'total_messages' => count($messageArray)
]);
?>
