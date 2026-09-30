<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Invalid request method.";
    exit;
}

// Get input values 
$first_name = trim($_POST["First_Name"] ?? "");
$middle_name = trim($_POST["Middle_Name"] ?? "");
$last_name = trim($_POST["Last_Name"] ?? "");
$birth_date = trim($_POST["Birth_date"] ?? "");
$gender = trim($_POST["Gender"] ?? "");
$email = trim($_POST["Email"] ?? "");
$phone_number = trim($_POST["Phone_Number"] ?? "");
$address = trim($_POST["Address"] ?? "");
$username = trim($_POST["Username"] ?? "");
$password = $_POST["Password"] ?? "";

// Validation

if (
    empty($first_name) ||
    empty($middle_name) ||
    empty($last_name) ||
    empty($birth_date) ||
    empty($gender) ||
    empty($email) ||
    empty($phone_number) ||
    empty($address) ||
    empty($username) ||
    empty($password)
) {
    echo "All fields are required.";
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address.";
    exit;
}

// Validate phone number
if (!preg_match("/^[0-9+ -]{10,20}$/", $phone_number)) {
    echo "Invalid phone number.";
    exit;
}

// Check if username already exists
$checkUsername = $conn->prepare(
    "SELECT User_ID FROM customer_information WHERE Username = ?"
);

$checkUsername->bind_param("s", $username);
$checkUsername->execute();
$result = $checkUsername->get_result();

if ($result->num_rows > 0) {
    echo "Username already exists.";
    exit;
}

$checkUsername->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Save customer
$sql = "INSERT INTO customer_information
(
    First_Name,
    Middle_Name,
    Last_Name,
    Birth_date,
    Gender,
    Email,
    Phone_Number,
    Address,
    Username,
    Password
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssssssssss",
    $first_name,
    $middle_name,
    $last_name,
    $birth_date,
    $gender,
    $email,
    $phone_number,
    $address,
    $username,
    $hashed_password
);

if ($stmt->execute()) {
    echo "Customer registration successful.";
} else {
    echo "Registration failed: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>