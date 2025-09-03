<?php
// Utility functions for analytics processing

/**
 * Calculate cause distribution from an array of JSON strings.
 * Each element should be a JSON-encoded array of causes.
 * Returns associative array cause => ['count' => int, 'percentage' => float].
 */
function calculate_cause_distribution(array $causeJsonList): array {
    $cause_counts = [];
    $total = 0;
    foreach ($causeJsonList as $jsonStr) {
        $decoded = json_decode($jsonStr, true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $cause) {
            $cause_counts[$cause] = ($cause_counts[$cause] ?? 0) + 1;
            $total++;
        }
    }
    arsort($cause_counts);
    $result = [];
    foreach ($cause_counts as $cause => $count) {
        $result[$cause] = [
            'count' => $count,
            'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0,
        ];
    }
    return $result;
}
