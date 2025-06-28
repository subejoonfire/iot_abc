<?php
header('Content-Type: application/json');
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "iot_abc";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$action = $_GET['action'] ?? '';

// Simpan data sensor
if ($action == 'save_sensor') {
    $data = json_decode(file_get_contents('php://input'), true);

    $hujan = $data['hujan'] ?? '';
    $cahaya = $data['cahaya'] ?? '';

    $stmt = $conn->prepare("INSERT INTO sensor_data (hujan_status, cahaya_status) VALUES (?, ?)");
    $stmt->bind_param("ss", $hujan, $cahaya);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Save failed"]);
    }
    $stmt->close();
}

// Ambil status lampu
elseif ($action == 'get_lamp') {
    $result = $conn->query("SELECT manual_mode, lamp_status FROM lamp_control ORDER BY id DESC LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            "manual_mode" => (bool)$row['manual_mode'],
            "lamp_status" => (bool)$row['lamp_status']
        ]);
    } else {
        echo json_encode(["error" => "Data not found"]);
    }
}

// Update status lampu (dipanggil dari website)
elseif ($action == 'update_lamp') {
    $manual = $_POST['manual'] ?? 0;
    $status = $_POST['status'] ?? 0;

    $stmt = $conn->prepare("UPDATE lamp_control SET manual_mode = ?, lamp_status = ?");
    $stmt->bind_param("ii", $manual, $status);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Update failed"]);
    }
    $stmt->close();
}

// Ambil data sensor untuk website
elseif ($action == 'get_sensor') {
    $result = $conn->query("SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 20");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
}

$conn->close();
