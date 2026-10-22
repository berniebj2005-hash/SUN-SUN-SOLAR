<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        respond(405, ['error' => 'Method not allowed']);
    }
}

function currentUser(): array
{
    global $db;
    if (empty($_SESSION['user'])) respond(401, ['error' => 'Authentication required']);
    $user = $_SESSION['user'];
    if ($user['role'] === 'customer') { $table = 'customer_information'; $idField = 'User_ID'; $roleSql = "'customer'"; }
    elseif ($user['role'] === 'admin') { $table = 'admin_db'; $idField = 'Admin_id'; $roleSql = "'admin'"; }
    else { $table = 'employee_db'; $idField = 'Employee_ID'; $roleSql = 'Role'; }
    $stmt = $db->prepare("SELECT Account_Status, {$roleSql} AS Role FROM {$table} WHERE {$idField} = ?");
    $stmt->bind_param('i', $user['id']); $stmt->execute(); $account = $stmt->get_result()->fetch_assoc();
    if (!$account || strtolower((string) $account['Account_Status']) !== 'active') {
        $_SESSION = []; session_destroy(); respond(401, ['error' => 'Authentication required']);
    }
    $user['role'] = strtolower((string) $account['Role']); $_SESSION['user'] = $user;
    return $user;
}

function requireRole(array $roles): array
{
    $user = currentUser();
    if (!in_array($user['role'], $roles, true)) {
        respond(403, ['error' => 'Forbidden']);
    }
    return $user;
}

function requiredFields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            respond(422, ['error' => 'Missing required field', 'field' => $field]);
        }
    }
}

function safeUser(array $row, string $role, string $idField): array
{
    return ['id' => (int) $row[$idField], 'username' => $row['Username'], 'role' => $role];
}

function findAccount(mysqli $db, string $username): ?array
{
    foreach ([
        ['customer_information', 'User_ID', 'customer'],
        ['employee_db', 'Employee_ID', 'employee'],
        ['admin_db', 'Admin_id', 'admin'],
    ] as [$table, $id, $role]) {
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE Username = ? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param('s', $username);
        if ($stmt->execute()) {
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                if ($role === 'employee') { $role = strtolower((string) ($row['Role'] ?? 'employee')); }
                return ['row' => $row, 'table' => $table, 'id' => $id, 'role' => $role];
            }
        } else {
            $stmt->close();
        }
    }
    return null;
}

$db = $conn ?? null;
if (!$db instanceof mysqli || $db->connect_errno) {
    respond(503, ['error' => 'Database unavailable']);
}

$action = (string) ($_GET['action'] ?? '');
$data = input();

try {
    switch ($action) {
        case 'register':
            requireMethod('POST');
            requiredFields($data, ['First_Name','Middle_Name','Last_Name','Birth_date','Gender','Email','Phone_Number','Address','Username','Password']);
            $email = filter_var(trim((string) $data['Email']), FILTER_VALIDATE_EMAIL);
            if (!$email) respond(422, ['error' => 'Invalid email address']);
            if (!preg_match('/^[0-9+() -]{7,24}$/', (string) $data['Phone_Number'])) respond(422, ['error' => 'Invalid phone number']);
            $date = DateTime::createFromFormat('!Y-m-d', (string) $data['Birth_date']);
            if (!$date || $date->format('Y-m-d') !== $data['Birth_date']) respond(422, ['error' => 'Invalid birth date']);
            $username = trim((string) $data['Username']);
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) respond(422, ['error' => 'Invalid username']);
            if (strlen((string) $data['Password']) < 10) respond(422, ['error' => 'Password must contain at least 10 characters']);
            $exists = $db->prepare('SELECT User_ID FROM customer_information WHERE Username = ? OR Email = ? LIMIT 1');
            $exists->bind_param('ss', $username, $email);
            $exists->execute();
            if ($exists->get_result()->num_rows) respond(409, ['error' => 'Username or email already exists']);
            $exists->close();
            $hash = password_hash((string) $data['Password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO customer_information (First_Name,Middle_Name,Last_Name,Birth_date,Gender,Email,Phone_Number,Address,Username,Password) VALUES (?,?,?,?,?,?,?,?,?,?)');
            if (!$stmt) respond(500, ['error' => 'Unable to process registration']);
            $first = trim((string) $data['First_Name']); $middle = trim((string) $data['Middle_Name']); $last = trim((string) $data['Last_Name']);
            $gender = trim((string) $data['Gender']); $phone = trim((string) $data['Phone_Number']); $address = trim((string) $data['Address']);
            $birthDate = $date->format('Y-m-d');
            $stmt->bind_param('ssssssssss', $first, $middle, $last, $birthDate, $gender, $email, $phone, $address, $username, $hash);
            if (!$stmt->execute()) respond(500, ['error' => 'Unable to complete registration']);
            respond(201, ['message' => 'Customer registered']);

        case 'login':
            requireMethod('POST');
            requiredFields($data, ['Username','Password']);
            $account = findAccount($db, trim((string) $data['Username']));
            if (!$account || !password_verify((string) $data['Password'], (string) $account['row']['Password'])) {
                respond(401, ['error' => 'Invalid credentials']);
            }
            if (strtolower((string) ($account['row']['Account_Status'] ?? 'active')) !== 'active') {
                respond(403, ['error' => 'Account is deactivated']);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = safeUser($account['row'], $account['role'], $account['id']);
            respond(200, ['user' => $_SESSION['user']]);

        case 'logout':
            requireMethod('POST');
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            respond(200, ['message' => 'Logged out']);

        case 'me':
            requireMethod('GET');
            respond(200, ['user' => currentUser()]);

        case 'services':
            requireMethod('GET');
            $result = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY service_id');
            if (!$result) respond(500, ['error' => 'Unable to retrieve services']);
            respond(200, ['services' => $result->fetch_all(MYSQLI_ASSOC)]);

        case 'bookings':
            $user = currentUser();
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                if ($user['role'] === 'customer') {
                    $stmt = $db->prepare('SELECT * FROM bookings WHERE customer_id = ? ORDER BY created_at DESC');
                    $stmt->bind_param('i', $user['id']);
                } elseif (in_array($user['role'], ['admin','dispatcher'], true)) {
                    $stmt = $db->prepare('SELECT * FROM bookings ORDER BY created_at DESC');
                } else respond(403, ['error' => 'Forbidden']);
                $stmt->execute();
                respond(200, ['bookings' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('POST');
            if ($user['role'] !== 'customer') respond(403, ['error' => 'Customers only']);
            requiredFields($data, ['service_id','requested_at','address','details']);
            $stmt = $db->prepare("INSERT INTO bookings (customer_id, service_id, requested_at, address, details, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $sid = (int) $data['service_id']; $requested = (string) $data['requested_at']; $address = (string) $data['address']; $details = (string) $data['details'];
            $stmt->bind_param('iisss', $user['id'], $sid, $requested, $address, $details);
            if (!$stmt->execute()) respond(422, ['error' => 'Invalid service or booking details']);
            respond(201, ['booking_id' => $db->insert_id, 'status' => 'Pending']);

        case 'checkin':
            requireMethod('POST');
            $user = requireRole(['technician']);
            requiredFields($data, ['booking_id','latitude','longitude']);
            $lat = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT); $lng = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) respond(422, ['error' => 'Invalid coordinates']);
            $booking = (int) $data['booking_id'];
            $stmt = $db->prepare('SELECT assignment_id FROM technician_assignments WHERE booking_id = ? AND employee_id = ? LIMIT 1');
            $stmt->bind_param('ii', $booking, $user['id']); $stmt->execute();
            $assignment = $stmt->get_result()->fetch_assoc();
            if (!$assignment) respond(403, ['error' => 'Job is not assigned to this technician']);
            $accuracy = isset($data['accuracy']) ? filter_var($data['accuracy'], FILTER_VALIDATE_FLOAT) : null;
            if (isset($data['accuracy']) && ($accuracy === false || $accuracy < 0)) respond(422, ['error' => 'Invalid accuracy value']);
            $stmt = $db->prepare('INSERT INTO technician_checkins (employee_id, assignment_id, latitude, longitude, accuracy) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiddd', $user['id'], $assignment['assignment_id'], $lat, $lng, $accuracy);
            if (!$stmt->execute()) respond(409, ['error' => 'Check-in could not be recorded']);
            respond(201, ['message' => 'Check-in recorded', 'checked_in_at' => gmdate('c')]);

        case 'report':
            requireMethod('POST');
            $user = requireRole(['technician']);
            requiredFields($data, ['booking_id','category','description']);
            $assignment = $db->prepare('SELECT assignment_id FROM technician_assignments WHERE booking_id = ? AND employee_id = ? LIMIT 1');
            $booking = (int) $data['booking_id']; $assignment->bind_param('ii', $booking, $user['id']); $assignment->execute();
            $row = $assignment->get_result()->fetch_assoc();
            if (!$row) respond(403, ['error' => 'Job is not assigned to this technician']);
            $allowed = ['Equipment issue','Installation problem','Maintenance concern','Customer concern','Safety issue','Other'];
            if (!in_array($data['category'], $allowed, true)) respond(422, ['error' => 'Invalid report category']);
            $stmt = $db->prepare("INSERT INTO technician_reports (assignment_id, category, description, status) VALUES (?, ?, ?, 'Open')");
            $stmt->bind_param('iss', $row['assignment_id'], $data['category'], $data['description']);
            if (!$stmt->execute()) respond(500, ['error' => 'Unable to save report']);
            respond(201, ['report_id' => $db->insert_id, 'status' => 'Open']);

        case 'tickets':
            $user = currentUser();
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                if ($user['role'] === 'customer') {
                    $stmt = $db->prepare('SELECT * FROM support_tickets WHERE customer_id = ? ORDER BY created_at DESC'); $stmt->bind_param('i', $user['id']);
                } elseif (in_array($user['role'], ['admin','support'], true)) $stmt = $db->prepare('SELECT * FROM support_tickets ORDER BY created_at DESC');
                else respond(403, ['error' => 'Forbidden']);
                $stmt->execute(); respond(200, ['tickets' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('POST');
            $user = requireRole(['customer']); requiredFields($data, ['subject','description']);
            $category = (string) ($data['category'] ?? 'General'); $stmt = $db->prepare("INSERT INTO support_tickets (customer_id, subject, description, category, status) VALUES (?, ?, ?, ?, 'Open')");
            $stmt->bind_param('isss', $user['id'], $data['subject'], $data['description'], $category);
            if (!$stmt->execute()) respond(500, ['error' => 'Unable to create ticket']);
            respond(201, ['ticket_id' => $db->insert_id, 'status' => 'Open']);

        default:
            require_once __DIR__ . '/extended.php';
            handle_extended_api($db, $action, $data, (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }
} catch (Throwable $error) {
    error_log('Backend API failure: ' . $error->getMessage());
    respond(500, ['error' => 'Request could not be completed']);
}
