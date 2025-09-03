<?php
// includes/models/posts_analytics.php

require_once __DIR__ . '/../analytics_helpers.php';

/**
 * Fetch analytics data for posts between two timestamps.
 * @param PDO    $pdo       Database connection.
 * @param string $date_start Start datetime in 'Y-m-d H:i:s' format.
 * @param string $date_end   End datetime in 'Y-m-d H:i:s' format.
 *
 * @return array Structured analytics data.
 */
function get_posts_analytics(PDO $pdo, string $date_start, string $date_end): array {
    $total_stats = [];
    $critical_swimmer_details = [];
    $incidents_by_category_data = [];
    $visitor_stats_per_post = [];
    $post_danger_rating = [];
    $lifeguard_performance_hours = [];
    $lifeguard_performance_incidents = [];
    $critical_swimmer_avg_age = null;
    $critical_swimmer_top_posts = [];
    $critical_swimmer_top_causes = [];
    $critical_details_map = [];

    try {
        $pdo->beginTransaction();

        // Query 1: aggregated shift report stats
        $stmt_report_totals = $pdo->prepare(
            "SELECT\n                COUNT(s.id) as total_shifts,\n                COALESCE(SUM(sr.people_on_beach_estimated), 0) as total_on_beach,\n                COALESCE(SUM(sr.people_in_water_estimated), 0) as total_in_water,\n                COALESCE(SUM(sr.suspicious_swimmers_count), 0) as suspicious_swimmers_count,\n                COALESCE(SUM(sr.visitor_inquiries_count), 0) as visitor_inquiries_count,\n                COALESCE(SUM(sr.bridge_jumpers_count), 0) as bridge_jumpers_count,\n                COALESCE(SUM(sr.alcohol_water_prevented_count), 0) as alcohol_water_prevented_count,\n                COALESCE(SUM(sr.alcohol_drinking_prevented_count), 0) as alcohol_drinking_prevented_count,\n                COALESCE(SUM(sr.watercraft_stopped_count), 0) as watercraft_stopped_count,\n                COALESCE(SUM(sr.preventive_actions_count), 0) as preventive_actions_count,\n                COALESCE(SUM(sr.educational_activities_count), 0) as educational_activities_count\n            FROM shifts s\n            LEFT JOIN shift_reports sr ON s.id = sr.shift_id\n            WHERE s.start_time BETWEEN :date_start AND :date_end"
        );
        $stmt_report_totals->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $report_stats = $stmt_report_totals->fetch(PDO::FETCH_ASSOC);

        // Query 2: incidents aggregation
        $stmt_incident_totals = $pdo->prepare(
            "SELECT\n                COUNT(ri.id) as total_incidents,\n                COALESCE(SUM(CASE WHEN ri.incident_type = 'police_call' THEN 1 ELSE 0 END), 0) as police_calls,\n                COALESCE(SUM(CASE WHEN ri.incident_type = 'ambulance_call' THEN 1 ELSE 0 END), 0) as ambulance_calls,\n                COALESCE(SUM(CASE WHEN ri.incident_type = 'lost_child' THEN 1 ELSE 0 END), 0) as lost_child,\n                COALESCE(SUM(CASE WHEN ri.incident_type = 'medical_aid' THEN 1 ELSE 0 END), 0) as medical_aid,\n                COALESCE(SUM(CASE WHEN ri.incident_type = 'critical_swimmer' THEN 1 ELSE 0 END), 0) as critical_swimmer\n            FROM report_incidents ri\n            JOIN shift_reports sr ON ri.shift_report_id = sr.id\n            JOIN shifts s ON sr.shift_id = s.id\n            WHERE s.start_time BETWEEN :date_start AND :date_end"
        );
        $stmt_incident_totals->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $incident_stats = $stmt_incident_totals->fetch(PDO::FETCH_ASSOC);

        $total_stats = array_merge($report_stats ?: [], $incident_stats ?: []);

        // Query 3: critical situation details
        $stmt_critical_details = $pdo->prepare(
            "SELECT\n                ri.incident_type,\n                COUNT(ri.id) as total_count,\n                COALESCE(SUM(CASE WHEN ri.subject_gender = 'Чоловік' AND (ri.subject_age > 14 OR ri.subject_age IS NULL) THEN 1 ELSE 0 END), 0) as male_count,\n                COALESCE(SUM(CASE WHEN ri.subject_gender = 'Жінка' AND (ri.subject_age > 14 OR ri.subject_age IS NULL) THEN 1 ELSE 0 END), 0) as female_count,\n                COALESCE(SUM(CASE WHEN ri.subject_age <= 14 THEN 1 ELSE 0 END), 0) as child_count\n            FROM report_incidents ri\n            JOIN shift_reports sr ON ri.shift_report_id = sr.id\n            JOIN shifts s ON sr.shift_id = s.id\n            WHERE s.start_time BETWEEN :date_start AND :date_end\n              AND ri.incident_type IN ('critical_swimmer', 'medical_aid', 'police_call', 'ambulance_call')\n            GROUP BY ri.incident_type"
        );
        $stmt_critical_details->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $critical_details_raw = $stmt_critical_details->fetchAll(PDO::FETCH_ASSOC);
        $critical_details_map = [];
        foreach ($critical_details_raw as $row) {
            $critical_details_map[$row['incident_type']] = $row;
        }
        $critical_swimmer_details = $critical_details_map['critical_swimmer'] ?? [
            'total_count' => 0, 'male_count' => 0, 'female_count' => 0, 'child_count' => 0
        ];

        // Detailed analysis of critical swimmers
        $stmt_critical_swimmer_analysis = $pdo->prepare(
            "SELECT\n                p.name AS post_name,\n                COUNT(ri.id) AS total_incidents,\n                AVG(ri.subject_age) AS avg_age,\n                GROUP_CONCAT(ri.cause_details SEPARATOR '||') AS causes_json\n            FROM report_incidents ri\n            JOIN shift_reports sr ON ri.shift_report_id = sr.id\n            JOIN shifts s ON sr.shift_id = s.id\n            JOIN posts p ON s.post_id = p.id\n            WHERE s.start_time BETWEEN :date_start AND :date_end\n              AND ri.incident_type = 'critical_swimmer'\n            GROUP BY p.name\n            ORDER BY total_incidents DESC"
        );
        $stmt_critical_swimmer_analysis->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $critical_swimmer_analysis_raw = $stmt_critical_swimmer_analysis->fetchAll(PDO::FETCH_ASSOC);

        $total_age_sum = 0;
        $total_incidents_cs = 0;
        $causes_raw = [];
        foreach ($critical_swimmer_analysis_raw as $row) {
            $count = (int)$row['total_incidents'];
            if ($row['avg_age'] !== null) {
                $total_age_sum += $row['avg_age'] * $count;
            }
            $total_incidents_cs += $count;
            if (!empty($row['causes_json'])) {
                $causes_raw = array_merge($causes_raw, explode('||', $row['causes_json']));
            }
        }
        if ($total_incidents_cs > 0) {
            $critical_swimmer_avg_age = $total_age_sum / $total_incidents_cs;
        }
        $critical_swimmer_cause_stats = calculate_cause_distribution($causes_raw);
        $critical_swimmer_top_causes = array_slice($critical_swimmer_cause_stats, 0, 5, true);
        $critical_swimmer_top_posts = array_slice($critical_swimmer_analysis_raw, 0, 5);

        // Incidents by category
        $stmt_incidents_cat = $pdo->prepare(
            "SELECT\n                ri.incident_type as category,\n                COUNT(ri.id) as incidents_count\n            FROM report_incidents ri\n            JOIN shift_reports sr ON ri.shift_report_id = sr.id\n            JOIN shifts s ON sr.shift_id = s.id\n            WHERE\n                s.start_time BETWEEN :date_start AND :date_end\n                AND ri.incident_type IS NOT NULL\n            GROUP BY ri.incident_type\n            ORDER BY incidents_count DESC"
        );
        $stmt_incidents_cat->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $incidents_by_category_data = $stmt_incidents_cat->fetchAll(PDO::FETCH_ASSOC);

        // Visitors per post
        $stmt_visitors_per_post = $pdo->prepare(
            "SELECT p.name, COALESCE(SUM(sr.people_on_beach_estimated), 0) as total_on_beach, COALESCE(SUM(sr.people_in_water_estimated), 0) as total_in_water\n             FROM posts p\n             LEFT JOIN shifts s ON p.id = s.post_id AND s.start_time BETWEEN :date_start AND :date_end\n             LEFT JOIN shift_reports sr ON s.id = sr.shift_id\n             GROUP BY p.id, p.name\n             ORDER BY (COALESCE(SUM(sr.people_on_beach_estimated), 0) + COALESCE(SUM(sr.people_in_water_estimated), 0)) DESC"
        );
        $stmt_visitors_per_post->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $visitor_stats_per_post = $stmt_visitors_per_post->fetchAll(PDO::FETCH_ASSOC);

        // Post danger rating
        $stmt_danger_index = $pdo->prepare(
            "SELECT p.name, p.complexity_coefficient,\n                (SELECT COUNT(ri.id) FROM report_incidents ri\n                    JOIN shift_reports sr ON ri.shift_report_id = sr.id\n                    JOIN shifts s_inner ON sr.shift_id = s_inner.id\n                    WHERE s_inner.post_id = p.id AND s_inner.start_time BETWEEN :date_start AND :date_end) as total_incidents,\n                (SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, s_inner.start_time, s_inner.end_time)), 0) FROM shifts s_inner\n                    WHERE s_inner.post_id = p.id AND s_inner.start_time BETWEEN :date_start AND :date_end) as total_hours\n             FROM posts p\n             ORDER BY p.name"
        );
        $stmt_danger_index->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $post_danger_rating_raw = $stmt_danger_index->fetchAll(PDO::FETCH_ASSOC);
        foreach ($post_danger_rating_raw as $post) {
            $danger_index = ($post['total_hours'] > 0) ? (($post['total_incidents'] / $post['total_hours']) * $post['complexity_coefficient']) : 0;
            $post['danger_index'] = $danger_index;
            $post_danger_rating[] = $post;
        }
        usort($post_danger_rating, function($a, $b) { return $b['danger_index'] <=> $a['danger_index']; });

        // Lifeguard performance by hours
        $stmt_lifeguard_hours = $pdo->prepare(
            "SELECT u.full_name, COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) as total_hours\n             FROM users u\n             JOIN shifts s ON u.id = s.user_id\n             WHERE s.start_time BETWEEN :date_start AND :date_end AND u.role IN ('lifeguard', 'director')\n             GROUP BY u.id, u.full_name\n             ORDER BY total_hours DESC\n             LIMIT 10"
        );
        $stmt_lifeguard_hours->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $lifeguard_performance_hours = $stmt_lifeguard_hours->fetchAll(PDO::FETCH_ASSOC);

        // Lifeguard performance by incidents
        $stmt_lifeguard_incidents = $pdo->prepare(
            "SELECT u.full_name, COUNT(DISTINCT ri.id) as total_incidents\n             FROM users u\n             JOIN report_incidents ri ON u.id = ri.involved_lifeguard_id\n             JOIN shift_reports sr ON ri.shift_report_id = sr.id\n             JOIN shifts s ON sr.shift_id = s.id\n             WHERE s.start_time BETWEEN :date_start AND :date_end AND u.role IN ('lifeguard', 'director')\n             GROUP BY u.id, u.full_name\n             ORDER BY total_incidents DESC\n             LIMIT 10"
        );
        $stmt_lifeguard_incidents->execute([':date_start' => $date_start, ':date_end' => $date_end]);
        $lifeguard_performance_incidents = $stmt_lifeguard_incidents->fetchAll(PDO::FETCH_ASSOC);

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'error' => $e->getMessage(),
            'total_stats' => [],
            'critical_swimmer_details' => [],
            'critical_swimmer_avg_age' => null,
            'critical_swimmer_top_posts' => [],
            'critical_swimmer_top_causes' => [],
            'critical_details_map' => [],
            'incidents_by_category_data' => [],
            'visitor_stats_per_post' => [],
            'post_danger_rating' => [],
            'lifeguard_performance_hours' => [],
            'lifeguard_performance_incidents' => [],
            'incidents_chart' => ['labels' => [], 'data' => []],
            'visitors_chart' => ['labels' => [], 'beach_data' => [], 'water_data' => []],
        ];
    }

    $incidents_chart = [
        'labels' => array_map('translate_incident_category_uk', array_column($incidents_by_category_data, 'category')),
        'data' => array_column($incidents_by_category_data, 'incidents_count')
    ];

    $visitors_chart = [
        'labels' => array_column($visitor_stats_per_post, 'name'),
        'beach_data' => array_column($visitor_stats_per_post, 'total_on_beach'),
        'water_data' => array_column($visitor_stats_per_post, 'total_in_water')
    ];

    return compact(
        'total_stats',
        'critical_swimmer_details',
        'critical_swimmer_avg_age',
        'critical_swimmer_top_posts',
        'critical_swimmer_top_causes',
        'incidents_by_category_data',
        'visitor_stats_per_post',
        'post_danger_rating',
        'lifeguard_performance_hours',
        'lifeguard_performance_incidents',
        'incidents_chart',
        'visitors_chart',
        'critical_details_map'
    );
}

function translate_incident_category_uk($key) {
    $translations = [
        'medical_aid' => 'Мед. допомога',
        'preventive_work' => 'Профілактика',
        'lost_child' => 'Загублена дитина',
        'drowning_prevention' => 'Попередження утоплення',
        'safety_violation' => 'Порушення безпеки',
        'found_object' => 'Знайдено річ',
        'other' => 'Інше',
        'critical_swimmer' => 'Крит. плавець',
        'police_call' => 'Виклик поліції',
        'ambulance_call' => 'Виклик швидкої'
    ];
    return $translations[$key] ?? ucfirst(str_replace('_', ' ', $key));
}

function translate_cause_detail_uk($key) {
    $translations = [
        'alcohol' => "Алкогольне сп'яніння",
        'exhaustion' => 'Фізичне виснаження',
        'forbidden_zone' => 'Купання в забороненому місці',
        'mental_illness' => 'Психічне захворювання',
        'cramp' => 'Судома',
        'water_injury' => 'Травмування у воді',
        'entangled_seaweed' => 'Заплутався у водоростях',
        'drowning_swallowed' => 'Захлинувся/Потопав',
        'rule_violation' => 'Порушення правил',
        'hypothermia' => 'Переохолодження',
        'disability' => 'Інвалідність',
        'other' => 'Інше',
    ];
    return $translations[$key] ?? ucfirst(str_replace('_', ' ', $key));
}
