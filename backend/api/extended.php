<?php

declare(strict_types=1);

/** Extended operations for the legacy mysqli API. The session identity is always authoritative. */
function apiBoolean(mixed $value): ?int
{
    if (in_array($value, [true, 1, '1'], true)) return 1;
    if (in_array($value, [false, 0, '0'], true)) return 0;
    return null;
}
function apiDateTime(mixed $value): ?string
{
    if (!is_string($value)) return null;
    $date = DateTime::createFromFormat('!Y-m-d H:i:s', $value);
    return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
}

function apiAudit(mysqli $db, array $actor, string $action, string $entity, ?int $entityId): void
{
    $stmt = $db->prepare('INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) throw new RuntimeException('Audit log unavailable');
    $type = (string) $actor['role']; $id = (int) $actor['id'];
    $stmt->bind_param('sissi', $type, $id, $action, $entity, $entityId);
    if (!$stmt->execute()) throw new RuntimeException('Audit log unavailable');
}

function apiRequireDateRange(array $data): array
{
    requiredFields($data, ['date_from', 'date_to']);
    $from = DateTime::createFromFormat('!Y-m-d', (string) $data['date_from']);
    $to = DateTime::createFromFormat('!Y-m-d', (string) $data['date_to']);
    if (!$from || !$to || $from->format('Y-m-d') !== $data['date_from'] || $to->format('Y-m-d') !== $data['date_to'] || $from > $to) {
        respond(422, ['error' => 'Invalid date range']);
    }
    return [$from->format('Y-m-d 00:00:00'), $to->format('Y-m-d 23:59:59')];
}

function handle_extended_api(mysqli $db, string $action, array $data, string $method): void
{
    $departments = ['Administration','IT','Dispatch','Accounting','HR','Marketing','Sales','Customer Service'];
    $employeeRoles = ['employee','technician','dispatcher','support','hr','accounting'];

    switch ($action) {
        case 'profile':
            requireMethod($method === 'GET' ? 'GET' : 'PUT');
            $user = requireRole(['customer']);
            if ($method === 'GET') {
                $stmt = $db->prepare('SELECT User_ID, First_Name, Middle_Name, Last_Name, Birth_date, Gender, Email, Phone_Number, Address, Username, Account_Status FROM customer_information WHERE User_ID = ?');
                $stmt->bind_param('i', $user['id']); $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if (!$row) respond(404, ['error' => 'Profile not found']);
                respond(200, ['profile' => $row]);
            }
            $allowed = ['First_Name','Middle_Name','Last_Name','Birth_date','Gender','Email','Phone_Number','Address','Username'];
            $sets = []; $values = []; $types = '';
            foreach ($allowed as $field) {
                if (!array_key_exists($field, $data)) continue;
                $value = trim((string) $data[$field]);
                if ($value === '') respond(422, ['error' => 'Profile fields cannot be empty', 'field' => $field]);
                if ($field === 'Email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) respond(422, ['error' => 'Invalid email address']);
                if ($field === 'Username' && !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $value)) respond(422, ['error' => 'Invalid username']);
                if ($field === 'Phone_Number' && !preg_match('/^[0-9+() -]{7,24}$/', $value)) respond(422, ['error' => 'Invalid phone number']);
                if ($field === 'Birth_date') {
                    $date = DateTime::createFromFormat('!Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) respond(422, ['error' => 'Invalid birth date']);
                }
                $sets[] = "`{$field}` = ?"; $values[] = $value; $types .= 's';
            }
            if (!$sets) respond(422, ['error' => 'No profile fields supplied']);
            $values[] = $user['id']; $types .= 'i';
            $stmt = $db->prepare('UPDATE customer_information SET ' . implode(', ', $sets) . ' WHERE User_ID = ?');
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) respond($db->errno === 1062 ? 409 : 422, ['error' => 'Profile could not be updated']);
            respond(200, ['message' => 'Profile updated']);

        case 'password':
            requireMethod('POST');
            $user = requireRole(['customer']); requiredFields($data, ['current_password','new_password']);
            if (strlen((string) $data['new_password']) < 10) respond(422, ['error' => 'Password must contain at least 10 characters']);
            $stmt = $db->prepare('SELECT Password FROM customer_information WHERE User_ID = ?');
            $stmt->bind_param('i', $user['id']); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc();
            if (!$row || !password_verify((string) $data['current_password'], (string) $row['Password'])) respond(401, ['error' => 'Current password is incorrect']);
            $hash = password_hash((string) $data['new_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE customer_information SET Password = ? WHERE User_ID = ?');
            $stmt->bind_param('si', $hash, $user['id']);
            if (!$stmt->execute()) respond(500, ['error' => 'Password could not be changed']);
            respond(200, ['message' => 'Password changed']);

        case 'employees':
            if ($method === 'GET') {
                $actor = requireRole(['admin','hr']);
                $result = $db->query('SELECT Employee_ID, First_name, Middle_name, Last_name, Email, Phone_Number, Department, Role, Account_Status FROM employee_db ORDER BY Employee_ID');
                if (!$result) respond(500, ['error' => 'Unable to list employees']);
                respond(200, ['employees' => $result->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('POST');
            $actor = requireRole(['admin','hr']);
            requiredFields($data, ['First_Name','Middle_Name','Last_Name','Birth_date','Gender','Email','Phone_Number','Address','Username','Password','Department']);
            $department = trim((string) $data['Department']);
            if (!in_array($department, $departments, true)) respond(422, ['error' => 'Invalid department']);
            $email = filter_var(trim((string) $data['Email']), FILTER_VALIDATE_EMAIL);
            if (!$email) respond(422, ['error' => 'Invalid email address']);
            $date = DateTime::createFromFormat('!Y-m-d', (string) $data['Birth_date']);
            if (!$date || $date->format('Y-m-d') !== $data['Birth_date']) respond(422, ['error' => 'Invalid birth date']);
            $username = trim((string) $data['Username']);
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || strlen((string) $data['Password']) < 10) respond(422, ['error' => 'Invalid username or password']);
            if (!preg_match('/^[0-9+() -]{7,24}$/', (string) $data['Phone_Number'])) respond(422, ['error' => 'Invalid phone number']);
            foreach ([['customer_information','Username','Email'],['employee_db','Username','Email'],['admin_db','Username',null]] as [$table,$usernameCol,$emailCol]) {
                $sql = $emailCol ? "SELECT 1 FROM {$table} WHERE {$usernameCol} = ? OR {$emailCol} = ? LIMIT 1" : "SELECT 1 FROM {$table} WHERE {$usernameCol} = ? LIMIT 1";
                $check = $db->prepare($sql);
                if ($emailCol) $check->bind_param('ss', $username, $email); else $check->bind_param('s', $username);
                $check->execute(); if ($check->get_result()->num_rows) respond(409, ['error' => 'Username or email already exists']);
            }
            $role = 'employee';
            if ($actor['role'] === 'admin' && isset($data['role'])) {
                $role = strtolower(trim((string) $data['role']));
                if (!in_array($role, $employeeRoles, true)) respond(422, ['error' => 'Invalid employee role']);
            } elseif (isset($data['role']) && strtolower((string) $data['role']) !== 'employee') {
                respond(403, ['error' => 'Only an administrator can assign elevated employee roles']);
            }
            $hash = password_hash((string) $data['Password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO employee_db (First_name, Middle_name, Last_name, Birth_date, Gender, Email, Phone_Number, Address, Username, Password, Department, Role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $first = trim((string) $data['First_Name']); $middle = trim((string) $data['Middle_Name']); $last = trim((string) $data['Last_Name']);
            $gender = trim((string) $data['Gender']); $phone = trim((string) $data['Phone_Number']); $address = trim((string) $data['Address']);
            $birthDate = $date->format('Y-m-d'); $stmt->bind_param('ssssssssssss', $first, $middle, $last, $birthDate, $gender, $email, $phone, $address, $username, $hash, $department, $role);
            if (!$stmt->execute()) respond($db->errno === 1062 ? 409 : 500, ['error' => 'Employee account could not be created']);
            $id = (int) $db->insert_id; apiAudit($db, $actor, 'employee.create', 'employee', $id);
            respond(201, ['employee_id' => $id, 'role' => $role, 'department' => $department]);

        case 'booking-status':
            requireMethod('POST'); $actor = requireRole(['admin','dispatcher']);
            requiredFields($data, ['booking_id','status']);
            $bookingId = (int) $data['booking_id']; $next = (string) $data['status'];
            if (!in_array($next, ['Approved','Declined','Rescheduled'], true)) respond(422, ['error' => 'Invalid booking status']);
            $lock = $db->query("SELECT GET_LOCK('sun_solar_booking_schedule', 5) AS acquired");
            if (!$lock || (int)$lock->fetch_assoc()['acquired'] !== 1) respond(503, ['error' => 'Schedule is busy; retry the request']);
            $db->begin_transaction();
            $stmt = $db->prepare('SELECT booking_id, customer_id, service_id, requested_at, duration_minutes, status FROM bookings WHERE booking_id = ? FOR UPDATE');
            $stmt->bind_param('i', $bookingId); $stmt->execute(); $booking = $stmt->get_result()->fetch_assoc();
            if (!$booking) { $db->rollback(); respond(404, ['error' => 'Booking not found']); }
            if ($next === 'Declined') {
                if (!in_array($booking['status'], ['Pending','Approved'], true)) { $db->rollback(); respond(409, ['error' => 'Booking cannot be declined in its current state']); }
                $update = $db->prepare("UPDATE bookings SET status = 'Declined' WHERE booking_id = ?"); $update->bind_param('i', $bookingId);
            } else {
                if (!in_array($booking['status'], ['Pending','Approved','Rescheduled'], true)) { $db->rollback(); respond(409, ['error' => 'Booking cannot be scheduled in its current state']); }
                $requested = $booking['requested_at'];
                if ($next === 'Rescheduled') {
                    requiredFields($data, ['requested_at']); $requested = apiDateTime($data['requested_at']);
                    if (!$requested) { $db->rollback(); respond(422, ['error' => 'requested_at must use YYYY-MM-DD HH:MM:SS']); }
                }
                $check = $db->prepare('SELECT availability_id FROM service_availability WHERE service_id = ? AND day_of_week = DAYOFWEEK(?) - 1 AND is_available = 1 AND TIME(?) >= opens_at AND ADDTIME(TIME(?), SEC_TO_TIME(? * 60)) <= closes_at LIMIT 1');
                $duration = (int) $booking['duration_minutes'];
                $check->bind_param('isssi', $booking['service_id'], $requested, $requested, $requested, $duration); $check->execute();
                if (!$check->get_result()->num_rows) { $db->rollback(); respond(409, ['error' => 'No configured service availability for this time']); }
                $conflict = $db->prepare("SELECT booking_id FROM bookings WHERE booking_id <> ? AND status IN ('Approved','Assigned','In Progress','Rescheduled') AND requested_at < DATE_ADD(?, INTERVAL ? MINUTE) AND DATE_ADD(requested_at, INTERVAL duration_minutes MINUTE) > ? LIMIT 1");
                $conflict->bind_param('isis', $bookingId, $requested, $duration, $requested);
                if (!$conflict->execute()) { $db->rollback(); respond(500, ['error' => 'Schedule could not be checked']); }
                if ($conflict->get_result()->num_rows) { $db->rollback(); respond(409, ['error' => 'Requested time conflicts with another booking']); }
                $update = $db->prepare('UPDATE bookings SET requested_at = ?, status = ? WHERE booking_id = ?');
                $update->bind_param('ssi', $requested, $next, $bookingId);
            }
            if (!$update->execute()) { $db->rollback(); respond(500, ['error' => 'Booking could not be updated']); }
            $notice = $db->prepare("INSERT INTO notifications (recipient_type,recipient_id,title,body) VALUES ('customer',?,?,?)");
            $noticeTitle = 'Booking ' . strtolower($next); $noticeBody = 'Booking #' . $bookingId . ' status: ' . $next;
            $notice->bind_param('iss', $booking['customer_id'], $noticeTitle, $noticeBody); $notice->execute();
            apiAudit($db, $actor, 'booking.' . strtolower($next), 'booking', $bookingId); $db->commit();
            $db->query("SELECT RELEASE_LOCK('sun_solar_booking_schedule')");
            respond(200, ['booking_id' => $bookingId, 'status' => $next]);

        case 'assignments':
            $actor = currentUser();
            if ($method === 'GET') {
                if ($actor['role'] === 'technician') {
                    $stmt = $db->prepare('SELECT a.assignment_id, a.booking_id, a.status, a.assigned_at, b.requested_at, b.duration_minutes, b.address, b.details, c.First_Name, c.Last_Name, c.Phone_Number FROM technician_assignments a JOIN bookings b ON b.booking_id=a.booking_id JOIN customer_information c ON c.User_ID=b.customer_id WHERE a.employee_id=? ORDER BY b.requested_at');
                    $stmt->bind_param('i', $actor['id']);
                } elseif (in_array($actor['role'], ['admin','dispatcher'], true)) {
                    $stmt = $db->prepare('SELECT a.assignment_id,a.booking_id,a.employee_id,a.status,a.assigned_at,b.requested_at,b.address FROM technician_assignments a JOIN bookings b ON b.booking_id=a.booking_id ORDER BY b.requested_at');
                } else respond(403, ['error' => 'Forbidden']);
                $stmt->execute(); respond(200, ['assignments' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('POST'); $actor = requireRole(['admin','dispatcher']); requiredFields($data, ['booking_id','employee_id']);
            $bookingId = (int) $data['booking_id']; $employeeId = (int) $data['employee_id'];
            $lockName = 'sun_solar_technician_' . $employeeId; $lockStmt = $db->prepare('SELECT GET_LOCK(?, 5) AS acquired'); $lockStmt->bind_param('s', $lockName); $lockStmt->execute();
            if ((int)$lockStmt->get_result()->fetch_assoc()['acquired'] !== 1) respond(503, ['error' => 'Technician schedule is busy; retry the request']);
            $db->begin_transaction();
            $bookingStmt = $db->prepare("SELECT booking_id, requested_at, duration_minutes FROM bookings WHERE booking_id=? AND status IN ('Approved','Assigned') FOR UPDATE");
            $bookingStmt->bind_param('i', $bookingId); $bookingStmt->execute(); $booking = $bookingStmt->get_result()->fetch_assoc();
            if (!$booking) { $db->rollback(); respond(409, ['error' => 'Booking must be approved before assignment']); }
            $tech = $db->prepare("SELECT Employee_ID FROM employee_db WHERE Employee_ID=? AND Role='technician' AND Account_Status='Active'");
            $tech->bind_param('i', $employeeId); $tech->execute();
            if (!$tech->get_result()->num_rows) { $db->rollback(); respond(422, ['error' => 'Active technician not found']); }
            $conflict = $db->prepare("SELECT a.assignment_id FROM technician_assignments a JOIN bookings b ON b.booking_id=a.booking_id WHERE a.employee_id=? AND a.status IN ('Assigned','In Progress') AND b.status IN ('Assigned','In Progress') AND b.booking_id<>? AND b.requested_at < DATE_ADD(?, INTERVAL ? MINUTE) AND DATE_ADD(b.requested_at, INTERVAL b.duration_minutes MINUTE) > ? LIMIT 1");
            $duration = (int) $booking['duration_minutes']; $requested = $booking['requested_at'];
            $conflict->bind_param('iisis', $employeeId, $bookingId, $requested, $duration, $requested); $conflict->execute();
            if ($conflict->get_result()->num_rows) { $db->rollback(); respond(409, ['error' => 'Technician has a scheduling conflict']); }
            $stmt = $db->prepare("INSERT INTO technician_assignments (booking_id, employee_id, status) VALUES (?, ?, 'Assigned')");
            $stmt->bind_param('ii', $bookingId, $employeeId);
            if (!$stmt->execute()) { $db->rollback(); respond(409, ['error' => 'Technician is already assigned to this booking']); }
            $id = (int) $db->insert_id; $upd = $db->prepare("UPDATE bookings SET status='Assigned' WHERE booking_id=?"); $upd->bind_param('i', $bookingId); $upd->execute();
            $notice = $db->prepare("INSERT INTO notifications (recipient_type,recipient_id,title,body) VALUES ('employee',?,?,?)");
            $noticeTitle='New service assignment'; $noticeBody='Booking #' . $bookingId . ' has been assigned to you.'; $notice->bind_param('iss',$employeeId,$noticeTitle,$noticeBody); $notice->execute();
            apiAudit($db, $actor, 'technician.assign', 'assignment', $id); $db->commit();
            $release=$db->prepare('SELECT RELEASE_LOCK(?)'); $release->bind_param('s',$lockName); $release->execute();
            respond(201, ['assignment_id' => $id, 'booking_id' => $bookingId, 'employee_id' => $employeeId]);

        case 'service-availability':
            if ($method === 'GET') {
                requiredFields($data, ['service_id']); $id = (int) $data['service_id'];
                $stmt = $db->prepare('SELECT day_of_week, opens_at, closes_at, is_available FROM service_availability WHERE service_id=? ORDER BY day_of_week, opens_at');
                $stmt->bind_param('i', $id); $stmt->execute(); respond(200, ['availability' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('PUT'); $actor = requireRole(['admin']);
            requiredFields($data, ['service_id','day_of_week','opens_at','closes_at','is_available']);
            $sid=(int)$data['service_id']; $day=(int)$data['day_of_week']; $open=(string)$data['opens_at']; $close=(string)$data['closes_at']; $active=apiBoolean($data['is_available']); if ($active===null) respond(422,['error'=>'is_available must be boolean']);
            if ($day<0 || $day>6 || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',$open) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',$close) || $close <= $open) respond(422,['error'=>'Invalid availability window']);
            $stmt=$db->prepare('INSERT INTO service_availability (service_id,day_of_week,opens_at,closes_at,is_available) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE closes_at=VALUES(closes_at),is_available=VALUES(is_available)');
            $stmt->bind_param('iissi',$sid,$day,$open,$close,$active); if (!$stmt->execute()) respond(422,['error'=>'Availability could not be saved']);
            apiAudit($db,$actor,'service.availability','service',$sid); respond(200,['message'=>'Availability saved']);

        case 'notifications':
            $actor = currentUser();
            if ($method === 'GET') {
                if ($actor['role'] === 'customer') {
                    $stmt=$db->prepare("SELECT notification_id,title,body,is_read,created_at FROM notifications WHERE (recipient_type='customer' AND recipient_id=?) ORDER BY created_at DESC"); $stmt->bind_param('i',$actor['id']);
                } elseif ($actor['role'] === 'admin') {
                    $stmt=$db->prepare("SELECT notification_id,title,body,is_read,created_at FROM notifications WHERE recipient_type IN ('all_employees','department') ORDER BY created_at DESC");
                } elseif ($actor['role'] === 'employee' || in_array($actor['role'],['technician','dispatcher','support','hr','accounting'],true)) {
                    $deptStmt=$db->prepare('SELECT Department FROM employee_db WHERE Employee_ID=?'); $deptStmt->bind_param('i',$actor['id']); $deptStmt->execute(); $department=(string)($deptStmt->get_result()->fetch_assoc()['Department'] ?? '');
                    $stmt=$db->prepare("SELECT notification_id,title,body,is_read,created_at FROM notifications WHERE (recipient_type='employee' AND recipient_id=?) OR (recipient_type='all_employees') OR (recipient_type='department' AND recipient_department=?) ORDER BY created_at DESC"); $stmt->bind_param('is',$actor['id'],$department);
                } else respond(403,['error'=>'Forbidden']);
                $stmt->execute(); respond(200,['notifications'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            }
            if ($method === 'PATCH') {
                requiredFields($data,['notification_id','is_read']); $id=(int)$data['notification_id']; $read=apiBoolean($data['is_read']); if ($read===null) respond(422,['error'=>'is_read must be boolean']);
                if ($actor['role']==='customer') {
                    $stmt=$db->prepare("UPDATE notifications SET is_read=? WHERE notification_id=? AND recipient_type='customer' AND recipient_id=?");$stmt->bind_param('iii',$read,$id,$actor['id']);
                } elseif ($actor['role']==='admin') {
                    $stmt=$db->prepare("UPDATE notifications SET is_read=? WHERE notification_id=? AND recipient_type IN ('all_employees','department')");$stmt->bind_param('ii',$read,$id);
                } elseif (in_array($actor['role'],['employee','technician','dispatcher','support','hr','accounting'],true)) {
                    $stmt=$db->prepare("UPDATE notifications SET is_read=? WHERE notification_id=? AND ((recipient_type='employee' AND recipient_id=?) OR recipient_type='all_employees' OR (recipient_type='department' AND recipient_department=(SELECT Department FROM employee_db WHERE Employee_ID=?)))");$stmt->bind_param('iiii',$read,$id,$actor['id'],$actor['id']);
                } else respond(403,['error'=>'Forbidden']);
                $stmt->execute(); if (!$stmt->affected_rows) respond(404,['error'=>'Notification not found']); respond(200,['message'=>'Notification updated']);
            }
            requireMethod('POST'); $creator=requireRole(['admin','hr']); requiredFields($data,['audience','title','body']);
            $audience=(string)$data['audience']; $title=trim((string)$data['title']); $body=trim((string)$data['body']);
            if (strlen($title)>160 || $title==='' || $body==='') respond(422,['error'=>'Invalid notification content']);
            if ($audience==='customer') {
                if ($creator['role']!=='admin') respond(403,['error'=>'Only administrators may send customer notifications']);
                requiredFields($data,['recipient_id']); $rid=(int)$data['recipient_id'];
                $stmt=$db->prepare("INSERT INTO notifications (recipient_type,recipient_id,title,body) VALUES ('customer',?,?,?)"); $stmt->bind_param('iss',$rid,$title,$body);
            } elseif ($audience==='employees') {
                $stmt=$db->prepare("INSERT INTO notifications (recipient_type,title,body,created_by_employee_id) VALUES ('all_employees',?,?,?)"); $creatorId=$creator['role']==='hr'?$creator['id']:null; $stmt->bind_param('ssi',$title,$body,$creatorId);
            } elseif ($audience==='department') {
                requiredFields($data,['department']); $dept=(string)$data['department']; if (!in_array($dept,$departments,true)) respond(422,['error'=>'Invalid department']);
                $stmt=$db->prepare("INSERT INTO notifications (recipient_type,recipient_department,title,body,created_by_employee_id) VALUES ('department',?,?,?,?)"); $creatorId=$creator['role']==='hr'?$creator['id']:null; $stmt->bind_param('sssi',$dept,$title,$body,$creatorId);
            } else respond(422,['error'=>'Invalid announcement audience']);
            if (!$stmt->execute()) respond(500,['error'=>'Notification could not be created']); $id=(int)$db->insert_id;
            apiAudit($db,$creator,'notification.create','notification',$id); respond(201,['notification_id'=>$id]);

        case 'payments':
            $actor=currentUser();
            if ($method==='GET') {
                if ($actor['role']==='customer') {$stmt=$db->prepare('SELECT p.payment_id,p.booking_id,p.amount,p.currency,p.method,p.status,p.transaction_at,r.receipt_number,r.issued_at FROM payments p LEFT JOIN receipts r ON r.payment_id=p.payment_id WHERE p.customer_id=? ORDER BY p.transaction_at DESC');$stmt->bind_param('i',$actor['id']);}
                elseif (in_array($actor['role'],['admin','accounting'],true)) $stmt=$db->prepare('SELECT p.payment_id,p.booking_id,p.customer_id,p.amount,p.currency,p.method,p.reference,p.status,p.transaction_at,r.receipt_number,r.issued_at FROM payments p LEFT JOIN receipts r ON r.payment_id=p.payment_id ORDER BY p.transaction_at DESC');
                else respond(403,['error'=>'Forbidden']);
                $stmt->execute(); respond(200,['payments'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('POST'); $actor=requireRole(['admin','accounting']); requiredFields($data,['booking_id','amount','currency','method','status','transaction_at']);
            $bookingId=(int)$data['booking_id']; $amount=filter_var($data['amount'],FILTER_VALIDATE_FLOAT); $currency=strtoupper((string)$data['currency']); $methodName=trim((string)$data['method']); $status=(string)$data['status']; $at=apiDateTime($data['transaction_at']);
            if ($amount===false || $amount<=0 || !preg_match('/^[A-Z]{3}$/',$currency) || $methodName==='' || !in_array($status,['paid','pending','void','refunded'],true) || !$at) respond(422,['error'=>'Invalid payment fields']);
            $find=$db->prepare('SELECT customer_id FROM bookings WHERE booking_id=?');$find->bind_param('i',$bookingId);$find->execute();$booking=$find->get_result()->fetch_assoc(); if (!$booking) respond(404,['error'=>'Booking not found']);
            $db->begin_transaction(); $customerId=(int)$booking['customer_id'];
            $stmt=$db->prepare('INSERT INTO payments (booking_id,customer_id,amount,currency,method,reference,status,transaction_at,created_by_role,created_by_account_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $reference=isset($data['reference'])?trim((string)$data['reference']):null; $actorRole=(string)$actor['role']; $stmt->bind_param('iidssssssi',$bookingId,$customerId,$amount,$currency,$methodName,$reference,$status,$at,$actorRole,$actor['id']);
            if (!$stmt->execute()) {$db->rollback();respond(500,['error'=>'Payment record could not be saved']);}
            $paymentId=(int)$db->insert_id; $receipt=null;
            if ($status==='paid') {
                $receipt=sprintf('RCPT-%s-%08d',date('Y'),$paymentId);
                $receiptStmt=$db->prepare('INSERT INTO receipts (payment_id,receipt_number) VALUES (?,?)');$receiptStmt->bind_param('is',$paymentId,$receipt);
                if (!$receiptStmt->execute()) {$db->rollback();respond(500,['error'=>'Receipt could not be issued']);}
            }
            apiAudit($db,$actor,'payment.record','payment',$paymentId);$db->commit(); respond(201,['payment_id'=>$paymentId,'receipt_number'=>$receipt,'status'=>$status]);

        case 'reports':
            requireMethod('GET'); $actor=requireRole(['admin']); [$from,$to]=apiRequireDateRange($data);
            $customers=$db->prepare("SELECT COUNT(*) total,SUM(Account_Status='Active') active,SUM(created_at BETWEEN ? AND ?) new_in_range FROM customer_information");$customers->bind_param('ss',$from,$to);$customers->execute();
            $bookings=$db->prepare('SELECT status,COUNT(*) count FROM bookings WHERE created_at BETWEEN ? AND ? GROUP BY status');$bookings->bind_param('ss',$from,$to);$bookings->execute();
            $services=$db->prepare('SELECT s.service_id,s.name,COUNT(b.booking_id) bookings FROM services s LEFT JOIN bookings b ON b.service_id=s.service_id AND b.created_at BETWEEN ? AND ? GROUP BY s.service_id,s.name ORDER BY bookings DESC');$services->bind_param('ss',$from,$to);$services->execute();
            $assignments=$db->prepare('SELECT employee_id,status,COUNT(*) assignments FROM technician_assignments WHERE assigned_at BETWEEN ? AND ? GROUP BY employee_id,status');$assignments->bind_param('ss',$from,$to);$assignments->execute();
            $attendance=$db->prepare('SELECT employee_id,COUNT(*) checkins FROM technician_checkins WHERE checked_in_at BETWEEN ? AND ? GROUP BY employee_id ORDER BY checkins DESC');$attendance->bind_param('ss',$from,$to);$attendance->execute();
            $issues=$db->prepare('SELECT r.category,COUNT(*) reports FROM technician_reports r WHERE r.submitted_at BETWEEN ? AND ? GROUP BY r.category');$issues->bind_param('ss',$from,$to);$issues->execute();
            $tickets=$db->prepare('SELECT status,COUNT(*) count FROM support_tickets WHERE created_at BETWEEN ? AND ? GROUP BY status');$tickets->bind_param('ss',$from,$to);$tickets->execute();
            $revenue=$db->prepare("SELECT currency,SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) paid_recorded,SUM(CASE WHEN status='refunded' THEN amount ELSE 0 END) refunded_recorded FROM payments WHERE transaction_at BETWEEN ? AND ? GROUP BY currency");$revenue->bind_param('ss',$from,$to);$revenue->execute();
            respond(200,['range'=>['from'=>$from,'to'=>$to],'customers'=>$customers->get_result()->fetch_assoc(),'bookings'=>$bookings->get_result()->fetch_all(MYSQLI_ASSOC),'service_usage'=>$services->get_result()->fetch_all(MYSQLI_ASSOC),'technician_assignments'=>$assignments->get_result()->fetch_all(MYSQLI_ASSOC),'technician_checkins'=>$attendance->get_result()->fetch_all(MYSQLI_ASSOC),'technician_reports'=>$issues->get_result()->fetch_all(MYSQLI_ASSOC),'support_tickets'=>$tickets->get_result()->fetch_all(MYSQLI_ASSOC),'recorded_payments'=>$revenue->get_result()->fetch_all(MYSQLI_ASSOC)]);
        case 'ticket-update':
            requireMethod('PATCH');$actor=requireRole(['admin','support']);requiredFields($data,['ticket_id','status']);
            $id=(int)$data['ticket_id'];$status=(string)$data['status'];$response=isset($data['response'])?trim((string)$data['response']):null;
            if (!in_array($status,['Open','In Progress','Resolved','Closed'],true)) respond(422,['error'=>'Invalid ticket status']);
            $stmt=$db->prepare('UPDATE support_tickets SET status=?,response=? WHERE ticket_id=?');$stmt->bind_param('ssi',$status,$response,$id);
            if (!$stmt->execute() || !$stmt->affected_rows) respond(404,['error'=>'Ticket not found or unchanged']);apiAudit($db,$actor,'ticket.update','ticket',$id);respond(200,['ticket_id'=>$id,'status'=>$status]);

        case 'service-manage':
            $actor = requireRole(['admin']);
            if ($method === 'POST') {
                requiredFields($data,['name','category','description']);
                $name=trim((string)$data['name']); $category=trim((string)$data['category']); $description=trim((string)$data['description']);
                $price=isset($data['price']) && $data['price']!=='' ? filter_var($data['price'],FILTER_VALIDATE_FLOAT) : null;
                if ($name==='' || strlen($name)>100 || $category==='' || strlen($category)>80 || $description==='' || ($price!==null && ($price===false || $price<0))) respond(422,['error'=>'Invalid service fields']);
                $stmt=$db->prepare('INSERT INTO services (name,category,description,price,is_active) VALUES (?,?,?,?,1)');$stmt->bind_param('ssss',$name,$category,$description,$price);
                if (!$stmt->execute()) respond(500,['error'=>'Service could not be created']);$id=(int)$db->insert_id;apiAudit($db,$actor,'service.create','service',$id);respond(201,['service_id'=>$id]);
            }
            requireMethod('PUT'); requiredFields($data,['service_id','name','category','description','is_active']);
            $id=(int)$data['service_id'];$name=trim((string)$data['name']);$category=trim((string)$data['category']);$description=trim((string)$data['description']);
            $price=isset($data['price']) && $data['price']!=='' ? filter_var($data['price'],FILTER_VALIDATE_FLOAT) : null;$active=apiBoolean($data['is_active']); if ($active===null) respond(422,['error'=>'is_active must be boolean']);
            if ($name==='' || strlen($name)>100 || $category==='' || strlen($category)>80 || $description==='' || ($price!==null && ($price===false || $price<0))) respond(422,['error'=>'Invalid service fields']);
            $stmt=$db->prepare('UPDATE services SET name=?,category=?,description=?,price=?,is_active=? WHERE service_id=?');$stmt->bind_param('ssssii',$name,$category,$description,$price,$active,$id);
            if (!$stmt->execute() || !$stmt->affected_rows) respond(404,['error'=>'Service not found or unchanged']);apiAudit($db,$actor,'service.update','service',$id);respond(200,['service_id'=>$id,'is_active'=>(bool)$active]);

        case 'assignment-update':
            requireMethod('PATCH');$actor=currentUser();requiredFields($data,['assignment_id','status']);$id=(int)$data['assignment_id'];$status=(string)$data['status'];
            if (!in_array($status,['In Progress','Completed','Cancelled'],true)) respond(422,['error'=>'Invalid assignment status']);
            if ($actor['role']==='technician') {$stmt=$db->prepare("UPDATE technician_assignments SET status=? WHERE assignment_id=? AND employee_id=? AND status IN ('Assigned','In Progress')");$stmt->bind_param('sii',$status,$id,$actor['id']);}
            elseif (in_array($actor['role'],['admin','dispatcher'],true)) {$stmt=$db->prepare("UPDATE technician_assignments SET status=? WHERE assignment_id=? AND status IN ('Assigned','In Progress')");$stmt->bind_param('si',$status,$id);}
            else respond(403,['error'=>'Forbidden']);
            if (!$stmt->execute() || !$stmt->affected_rows) respond(404,['error'=>'Assignment not found or unavailable']);
            $booking=$db->prepare('SELECT booking_id FROM technician_assignments WHERE assignment_id=?');$booking->bind_param('i',$id);$booking->execute();$row=$booking->get_result()->fetch_assoc();
            if ($status==='Cancelled') {$remaining=$db->prepare("SELECT COUNT(*) n FROM technician_assignments WHERE booking_id=? AND status IN ('Assigned','In Progress')");$remaining->bind_param('i',$row['booking_id']);$remaining->execute();if ((int)$remaining->get_result()->fetch_assoc()['n']===0) {$b=$db->prepare("UPDATE bookings SET status='Approved' WHERE booking_id=? AND status='Assigned'");$b->bind_param('i',$row['booking_id']);$b->execute();}}
            if ($status==='In Progress') {$b=$db->prepare("UPDATE bookings SET status='In Progress' WHERE booking_id=? AND status='Assigned'");$b->bind_param('i',$row['booking_id']);$b->execute();}
            if ($status==='Completed') {$remaining=$db->prepare("SELECT COUNT(*) n FROM technician_assignments WHERE booking_id=? AND status IN ('Assigned','In Progress')");$remaining->bind_param('i',$row['booking_id']);$remaining->execute();if ((int)$remaining->get_result()->fetch_assoc()['n']===0) {$b=$db->prepare("UPDATE bookings SET status='Completed' WHERE booking_id=?");$b->bind_param('i',$row['booking_id']);$b->execute();}}
            if (in_array($actor['role'],['admin','dispatcher'],true)) apiAudit($db,$actor,'assignment.'.strtolower(str_replace(' ','_',$status)),'assignment',$id);
            respond(200,['assignment_id'=>$id,'status'=>$status]);
        case 'technician-reports':
            if ($method === 'GET') {
                requireRole(['admin']);
                $result=$db->query('SELECT r.report_id,r.assignment_id,r.category,r.description,r.status,r.submitted_at,r.reviewed_at,a.employee_id,a.booking_id FROM technician_reports r JOIN technician_assignments a ON a.assignment_id=r.assignment_id ORDER BY r.submitted_at DESC');
                if (!$result) respond(500,['error'=>'Unable to retrieve technician reports']);respond(200,['reports'=>$result->fetch_all(MYSQLI_ASSOC)]);
            }
            requireMethod('PATCH');$actor=requireRole(['admin']);requiredFields($data,['report_id','status']);$id=(int)$data['report_id'];$status=(string)$data['status'];
            if (!in_array($status,['Reviewing','Resolved'],true)) respond(422,['error'=>'Invalid report status']);
            $stmt=$db->prepare('UPDATE technician_reports SET status=?,reviewer_employee_id=NULL,reviewed_at=CURRENT_TIMESTAMP WHERE report_id=?');$stmt->bind_param('si',$status,$id);
            if (!$stmt->execute() || !$stmt->affected_rows) respond(404,['error'=>'Report not found or unchanged']);apiAudit($db,$actor,'technician_report.review','technician_report',$id);respond(200,['report_id'=>$id,'status'=>$status]);
        default:
            respond(404,['error'=>'Unknown action']);
    }
}
