-- Модификация таблицы posts
ALTER TABLE posts
ADD COLUMN complexity_coefficient DECIMAL(3,2) DEFAULT 1.00;

-- Модификация таблицы users
ALTER TABLE users
ADD COLUMN base_hourly_rate DECIMAL(10,2) DEFAULT 120.00;

-- Модификация таблицы shifts
ALTER TABLE shifts
ADD COLUMN rounded_work_hours INT NULL;

-- Создание новой таблицы lifeguard_shift_points
CREATE TABLE lifeguard_shift_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shift_id INT NOT NULL,
    user_id INT NOT NULL,
    rule_id INT NOT NULL,
    points_awarded INT NOT NULL,
    base_points_from_rule INT NOT NULL,
    coefficient_applied DECIMAL(3,2) NOT NULL,
    awarded_by_user_id INT NULL,
    award_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comment TEXT NULL,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES points(id_balls) ON DELETE CASCADE,
    FOREIGN KEY (awarded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Создание индексов для оптимизации
CREATE INDEX idx_shifts_user_id ON shifts(user_id);
CREATE INDEX idx_shifts_post_id ON shifts(post_id);
CREATE INDEX idx_shifts_status ON shifts(status);
CREATE INDEX idx_shifts_end_time ON shifts(end_time);
CREATE INDEX idx_lifeguard_shift_points_shift_id ON lifeguard_shift_points(shift_id);
CREATE INDEX idx_lifeguard_shift_points_user_id ON lifeguard_shift_points(user_id);
CREATE INDEX idx_lifeguard_shift_points_rule_id ON lifeguard_shift_points(rule_id);

-- Удалить поле current_month_points из users
ALTER TABLE users DROP COLUMN current_month_points; 