-- Náborová zóna — vlastná evidencia oslovených kandidátov (nezávislá od
-- registra NBS/mapy — kandidát nemusí byť vôbec v tom datasete). Len owner.
CREATE TABLE IF NOT EXISTS formulare_recruit_candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    initiator VARCHAR(10) NOT NULL DEFAULT 'ja',
    status VARCHAR(30) NOT NULL DEFAULT 'novy',
    note TEXT NOT NULL,
    contact_date DATE NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_recruit_candidates_status (status),
    FOREIGN KEY (created_by) REFERENCES formulare_advisors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
