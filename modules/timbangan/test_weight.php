<?php
// Simple test untuk weight display
header('Content-Type: application/json');

// Simulasi berat realistis
function get_weight() {
    static $weight = 6500;
    $change = rand(-200, 200);
    $weight += $change;

    if ($weight > 8500) $weight = 8500;
    if ($weight < 3000) $weight = 3000;

    return $weight;
}

$action = $_POST['action'] ?? 'get_weight';

if ($action == 'get_weight') {
    $weight = get_weight();
    echo json_encode([
        'success' => true,
        'data' => [
            'weight' => $weight,
            'formatted' => number_format($weight, 0, ',', '.')
        ]
    ]);
}

if ($action == 'get_weight_timbangan2') {
    static $weight2 = 2500;
    $change = rand(-100, 100);
    $weight2 += $change;

    if ($weight2 > 3500) $weight2 = 3500;
    if ($weight2 < 1500) $weight2 = 1500;

    echo json_encode([
        'success' => true,
        'data' => [
            'weight' => $weight2,
            'formatted' => number_format($weight2, 0, ',', '.')
        ]
    ]);
}
?>