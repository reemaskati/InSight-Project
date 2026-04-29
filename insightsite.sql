-- ============================================================
--  inSight — MySQL Database (lowercase tables for MAMP)
--  IT320 Course Project | King Saud University | Group 7
-- ============================================================

CREATE DATABASE IF NOT EXISTS insight_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE insight_db;

-- ── admin ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin (
    AdminID   INT          NOT NULL AUTO_INCREMENT,
    AdminName VARCHAR(20)  NOT NULL UNIQUE,
    Email     VARCHAR(100) NOT NULL UNIQUE,
    Password  VARCHAR(255) NOT NULL,
    CreatedAt DATE         NOT NULL,
    PRIMARY KEY (AdminID)
) ENGINE=InnoDB;

-- ── user ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user (
    UserID    INT           NOT NULL AUTO_INCREMENT,
    Username  VARCHAR(20)   NOT NULL UNIQUE,
    Name      VARCHAR(100)  NOT NULL,
    Email     VARCHAR(100)  NOT NULL UNIQUE,
    Password  VARCHAR(255)  NOT NULL,
    Age       INT           NOT NULL,
    Blocked   TINYINT(1)    NOT NULL DEFAULT 0,
    Budget    DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    CreatedAt DATE          NOT NULL,
    PRIMARY KEY (UserID)
) ENGINE=InnoDB;

-- ── bill ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bill (
    BillID       INT                        NOT NULL AUTO_INCREMENT,
    UserID       INT                        NOT NULL,
    BillType     ENUM('electricity','water') NOT NULL,
    BillingMonth DATE                        NOT NULL,
    MeterReading DECIMAL(10,2)              NOT NULL DEFAULT 0.00,
    TotalCost    DECIMAL(10,2)              NOT NULL DEFAULT 0.00,
    PRIMARY KEY (BillID),
    FOREIGN KEY (UserID) REFERENCES user(UserID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── saving_tip ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS saving_tip (
    TipID   INT                                    NOT NULL AUTO_INCREMENT,
    TipType ENUM('electricity','water','general')   NOT NULL,
    Content TEXT                                    NOT NULL,
    Min     DECIMAL(10,2) DEFAULT NULL,
    Max     DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (TipID)
) ENGINE=InnoDB;

-- ── Indexes ──────────────────────────────────────────────────
CREATE INDEX idx_bill_user  ON bill (UserID);
CREATE INDEX idx_bill_month ON bill (BillingMonth);
CREATE INDEX idx_bill_type  ON bill (BillType);

-- ============================================================
--  SEED DATA
-- ============================================================

INSERT INTO admin (AdminName, Email, Password, CreatedAt) VALUES
('admin', 'admin@insight.sa', 'admin123', '2025-01-01');

INSERT INTO user (Username, Name, Email, Password, Age, Blocked, Budget, CreatedAt) VALUES
('user',  'Sara Al-Rashidi',  'sara@example.com',  'user123',  29, 0, 500.00, '2025-01-05'),
('feras', 'Feras Al-Otaibi', 'feras@example.com', 'feras123', 40, 0, 700.00, '2025-01-10'),
('leen',  'Leen Al-Harbi',   'leen@example.com',  'leen123',  27, 0, 300.00, '2025-01-15');

INSERT INTO bill (UserID, BillType, BillingMonth, MeterReading, TotalCost) VALUES
(1, 'electricity', '2024-01-01', 320.00, 180.50),
(1, 'electricity', '2024-02-01', 310.00, 174.30),
(1, 'electricity', '2024-03-01', 290.00, 163.40),
(1, 'electricity', '2024-04-01', 350.00, 197.20),
(1, 'electricity', '2024-05-01', 480.00, 270.60),
(1, 'electricity', '2024-06-01', 510.00, 287.40),
(1, 'water',       '2024-01-01',  18.00,  55.00),
(1, 'water',       '2024-02-01',  17.50,  53.50),
(1, 'water',       '2024-03-01',  19.00,  58.00),
(1, 'water',       '2024-06-01',  32.00,  97.60),
(2, 'electricity', '2024-03-01', 420.00, 236.40),
(2, 'water',       '2024-03-01',  22.00,  67.10);

INSERT INTO saving_tip (TipType, Content, Min, Max) VALUES
('electricity', 'Your electricity usage is within a normal range. Consider setting your AC to 24 degrees instead of 20 to save up to 15% on cooling costs.', 0, 350.00),
('electricity', 'Your electricity consumption is above average. Make sure all lights are off when leaving a room, and unplug devices on standby.', 350.01, 450.00),
('electricity', 'High electricity usage detected! Consider servicing your AC unit — a dirty filter can increase consumption by 20%.', 450.01, 9999.00),
('water', 'Your water usage looks healthy. Fix dripping taps — a slow drip wastes up to 20 litres per day.', 0, 20.00),
('water', 'Your water consumption is a bit high. Check for hidden leaks under sinks and around toilets.', 20.01, 30.00),
('water', 'Unusually high water usage! Please inspect your home for leaks immediately.', 30.01, 9999.00),
('general', 'Run your dishwasher and washing machine only when fully loaded to save up to 30%.', NULL, NULL),
('general', 'Switch to LED lighting — uses up to 80% less electricity than traditional bulbs.', NULL, NULL),
('general', 'Install smart power strips to eliminate phantom loads from TVs and chargers.', NULL, NULL),
('general', 'Vision 2030 Tip: Households that actively monitor utility usage reduce consumption by an average of 20%.', NULL, NULL);
