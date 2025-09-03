<?php
require_once __DIR__ . '/../includes/models/posts_analytics.php';

// In-memory SQLite for basic testing
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, name TEXT, complexity_coefficient REAL);");
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, role TEXT);");
$pdo->exec("CREATE TABLE shifts (id INTEGER PRIMARY KEY, post_id INTEGER, user_id INTEGER, start_time TEXT, end_time TEXT);");
$pdo->exec("CREATE TABLE shift_reports (id INTEGER PRIMARY KEY, shift_id INTEGER, people_on_beach_estimated INTEGER, people_in_water_estimated INTEGER, suspicious_swimmers_count INTEGER, visitor_inquiries_count INTEGER, bridge_jumpers_count INTEGER, alcohol_water_prevented_count INTEGER, alcohol_drinking_prevented_count INTEGER, watercraft_stopped_count INTEGER, preventive_actions_count INTEGER, educational_activities_count INTEGER);");
$pdo->exec("CREATE TABLE report_incidents (id INTEGER PRIMARY KEY, shift_report_id INTEGER, incident_type TEXT, subject_gender TEXT, subject_age INTEGER, cause_details TEXT, involved_lifeguard_id INTEGER);");

$pdo->exec("INSERT INTO posts (id, name, complexity_coefficient) VALUES (1,'Post A',1.0),(2,'Post B',2.0);");
$pdo->exec("INSERT INTO users (id, full_name, role) VALUES (1,'Lifeguard A','lifeguard'),(2,'Lifeguard B','lifeguard');");
$pdo->exec("INSERT INTO shifts (id, post_id, user_id, start_time, end_time) VALUES
    (1,1,1,'2024-01-01 08:00:00','2024-01-01 20:00:00'),
    (2,2,2,'2024-01-02 08:00:00','2024-01-02 20:00:00');");
$pdo->exec("INSERT INTO shift_reports (id, shift_id, people_on_beach_estimated, people_in_water_estimated, suspicious_swimmers_count, visitor_inquiries_count, bridge_jumpers_count, alcohol_water_prevented_count, alcohol_drinking_prevented_count, watercraft_stopped_count, preventive_actions_count, educational_activities_count) VALUES
    (1,1,100,20,1,2,0,0,0,0,1,1),
    (2,2,150,30,0,1,1,0,0,0,2,0);");
$pdo->exec("INSERT INTO report_incidents (id, shift_report_id, incident_type, subject_gender, subject_age, cause_details, involved_lifeguard_id) VALUES
    (1,1,'critical_swimmer','Чоловік',30,'[\"alcohol\"]',1),
    (2,2,'medical_aid','Жінка',25,'[\"exhaustion\"]',2);");

$data = get_posts_analytics($pdo, '2024-01-01 00:00:00', '2024-01-31 23:59:59');
if (!empty($data['error'])) {
    echo "Error: {$data['error']}\n";
} else {
    print_r($data['total_stats']);
    print_r($data['critical_swimmer_details']);
}
