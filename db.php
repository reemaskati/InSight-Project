<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
// ── Connection settings ──────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 8889);
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'insightsite');

// ── Create connection ────────────────────────────────────────
function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}



/**
 * Login — checks USER or ADMIN table
 * Returns ['success'=>true,'role'=>'user'|'admin','data'=>[...]]
 *         or ['success'=>false,'message'=>'...']
 */
function login(string $username, string $password, string $role): array {
    $db = getDB();

    if ($role === 'admin') {
        $stmt = $db->prepare("SELECT AdminID AS id, AdminName AS name, Email FROM admin WHERE AdminName=? AND Password=?");
    } else {
        $stmt = $db->prepare("SELECT UserID AS id, Name AS name, Email, Age, Budget, Blocked FROM user WHERE Username=? AND Password=?");
    }

    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    $user = $result->fetch_assoc();

    if ($role === 'user' && $user['Blocked']) {
        return ['success' => false, 'message' => 'Your account has been blocked. Please contact the administrator.'];
    }

    return ['success' => true, 'role' => $role, 'data' => $user];
}

// ============================================================
//  USER FUNCTIONS  (Admin manages users)
// ============================================================

/** Return all users */
function getAllUsers(): array {
    $db = getDB();
    $result = $db->query("
        SELECT u.UserID, u.Username, u.Name, u.Email, u.Age, u.Blocked, u.Budget,
               COUNT(b.BillID) AS BillCount
        FROM user u
        LEFT JOIN bill b ON u.UserID = b.UserID
        GROUP BY u.UserID
        ORDER BY u.UserID
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/** Add a new user (by admin) */
function addUser(string $username, string $name, string $email, string $password, int $age): array {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO user (Username, Name, Email, Password, Age) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $username, $name, $email, $password, $age);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'User created successfully.'];
    }
    return ['success' => false, 'message' => 'Error: ' . $db->error];
}

/** Block / Unblock a user */
function toggleBlockUser(int $userID): array {
    $db = getDB();
    $stmt = $db->prepare("UPDATE user SET Blocked = NOT Blocked WHERE UserID = ?");
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    return ['success' => true];
}

/** Update user profile */
function updateProfile(int $userID, string $name, string $email, int $age): array {
    $db = getDB();
    $stmt = $db->prepare("UPDATE user SET Name=?, Email=?, Age=? WHERE UserID=?");
    $stmt->bind_param('ssii', $name, $email, $age, $userID);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Profile updated.'];
    }
    return ['success' => false, 'message' => $db->error];
}

/** Update monthly budget */
function updateBudget(int $userID, float $budget): array {
    $db = getDB();
    $stmt = $db->prepare("UPDATE user SET Budget=? WHERE UserID=?");
    $stmt->bind_param('di', $budget, $userID);
    $stmt->execute();
    return ['success' => true, 'message' => 'Budget updated.'];
}

// ============================================================
//  BILL FUNCTIONS
// ============================================================

/** Get all bills for a user (with optional search/filter) */
function getBills(int $userID, string $search = '', string $type = '', string $year = ''): array {
    $db = getDB();

    $sql  = "SELECT * FROM bill WHERE UserID = ?";
    $params = [$userID];
    $types  = 'i';

    if ($search !== '') {
        $sql    .= " AND (DATE_FORMAT(BillingMonth,'%M %Y') LIKE ? OR DATE_FORMAT(BillingMonth,'%Y-%m') LIKE ?)";
        $like    = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $types   .= 'ss';
    }
    if ($type !== '') {
        $sql    .= " AND BillType = ?";
        $params[] = $type;
        $types   .= 's';
    }
    if ($year !== '') {
        $sql    .= " AND YEAR(BillingMonth) = ?";
        $params[] = (int)$year;
        $types   .= 'i';
    }

    $sql .= " ORDER BY BillingMonth DESC, BillType";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Get a single bill */
function getBillByID(int $billID, int $userID): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM bill WHERE BillID=? AND UserID=?");
    $stmt->bind_param('ii', $billID, $userID);
    $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

/** Add a new bill */
function addBill(int $userID, string $type, string $month, float $meter, float $cost): array {
    $db   = getDB();
    $date = $month . '-01';   // e.g. "2024-06" → "2024-06-01"
    $stmt = $db->prepare("INSERT INTO bill (UserID, BillType, BillingMonth, MeterReading, TotalCost) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issdd', $userID, $type, $date, $meter, $cost);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Bill added successfully.', 'id' => $db->insert_id];
    }
    return ['success' => false, 'message' => $db->error];
}

/** Update an existing bill */
function updateBill(int $billID, int $userID, string $type, string $month, float $meter, float $cost): array {
    $db   = getDB();
    $date = $month . '-01';
    $stmt = $db->prepare("UPDATE bill SET BillType=?, BillingMonth=?, MeterReading=?, TotalCost=? WHERE BillID=? AND UserID=?");
    $stmt->bind_param('ssddii', $type, $date, $meter, $cost, $billID, $userID);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Bill updated successfully.'];
    }
    return ['success' => false, 'message' => $db->error];
}

/** Delete a bill */
function deleteBill(int $billID, int $userID): array {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM bill WHERE BillID=? AND UserID=?");
    $stmt->bind_param('ii', $billID, $userID);
    $stmt->execute();
    return ['success' => true, 'message' => 'Bill deleted.'];
}

// ============================================================
//  DASHBOARD FUNCTIONS
// ============================================================

/** Summary stats for the dashboard */
function getDashboardStats(int $userID): array {
    $db = getDB();

    // Total spent this year
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN BillType='electricity' THEN TotalCost ELSE 0 END) AS totalElec,
            SUM(CASE WHEN BillType='water'       THEN TotalCost ELSE 0 END) AS totalWater,
            SUM(TotalCost) AS grandTotal,
            COUNT(BillID)  AS billCount
        FROM bill
        WHERE UserID=? AND YEAR(BillingMonth)=YEAR(CURDATE())
    ");
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();

    // Monthly data for chart (last 6 months)
    $stmt2 = $db->prepare("
        SELECT
            DATE_FORMAT(BillingMonth,'%b %Y') AS label,
            BillingMonth,
            SUM(CASE WHEN BillType='electricity' THEN TotalCost ELSE 0 END) AS elec,
            SUM(CASE WHEN BillType='water'       THEN TotalCost ELSE 0 END) AS water,
            SUM(TotalCost) AS total
        FROM bill
        WHERE UserID=? AND BillingMonth >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY BillingMonth
        ORDER BY BillingMonth ASC
    ");
    $stmt2->bind_param('i', $userID);
    $stmt2->execute();
    $monthly = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

    // User budget
    $stmt3 = $db->prepare("SELECT Budget FROM user WHERE UserID=?");
    $stmt3->bind_param('i', $userID);
    $stmt3->execute();
    $budgetRow = $stmt3->get_result()->fetch_assoc();

    return [
        'totals'  => $totals,
        'monthly' => $monthly,
        'budget'  => $budgetRow['Budget'] ?? 500,
    ];
}

// ============================================================
//  SAVING TIPS FUNCTIONS
// ============================================================

/**
 * Get personalised tips based on the user's last month average
 * Always includes general tips too
 */
function getSavingTips(int $userID): array {
    $db = getDB();

    // Last month averages per type
    $stmt = $db->prepare("
        SELECT BillType, AVG(MeterReading) AS avgReading
        FROM bill
        WHERE UserID=?
          AND BillingMonth >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY BillType
    ");
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $avgs = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $avgs[$row['BillType']] = (float)$row['avgReading'];
    }

    $tips = [];

    // Electricity tip
    $elecAvg = $avgs['electricity'] ?? null;
    if ($elecAvg !== null) {
        $stmt2 = $db->prepare("
            SELECT Content FROM saving_tip
            WHERE TipType='electricity'
              AND (Min IS NULL OR Min <= ?)
              AND (Max IS NULL OR Max >= ?)
            LIMIT 1
        ");
        $stmt2->bind_param('dd', $elecAvg, $elecAvg);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        if ($row) $tips[] = ['type' => 'electricity', 'content' => $row['Content']];
    }

    // Water tip
    $waterAvg = $avgs['water'] ?? null;
    if ($waterAvg !== null) {
        $stmt3 = $db->prepare("
            SELECT Content FROM saving_tip
            WHERE TipType='water'
              AND (Min IS NULL OR Min <= ?)
              AND (Max IS NULL OR Max >= ?)
            LIMIT 1
        ");
        $stmt3->bind_param('dd', $waterAvg, $waterAvg);
        $stmt3->execute();
        $row = $stmt3->get_result()->fetch_assoc();
        if ($row) $tips[] = ['type' => 'water', 'content' => $row['Content']];
    }

    // General tips (always show 2)
    $gen = $db->query("SELECT Content FROM saving_tip WHERE TipType='general' ORDER BY RAND() LIMIT 2");
    foreach ($gen->fetch_all(MYSQLI_ASSOC) as $row) {
        $tips[] = ['type' => 'general', 'content' => $row['Content']];
    }

    return $tips;
}

// ============================================================
//  SESSION HELPERS
// ============================================================

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireLogin(): void {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    startSecureSession();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function setUserSession(array $data): void {
    startSecureSession();
    $_SESSION['user_id']   = $data['id'];
    $_SESSION['user_name'] = $data['name'];
    $_SESSION['user_email']= $data['Email'];
    $_SESSION['user_age']  = $data['Age'];
    $_SESSION['user_budget']= $data['Budget'];
}

function setAdminSession(array $data): void {
    startSecureSession();
    $_SESSION['admin_id']   = $data['id'];
    $_SESSION['admin_name'] = $data['name'];
}

function logout(): void {
    startSecureSession();
    session_destroy();
    header('Location: index.php');
    exit;
}