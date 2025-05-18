<?php
header('Content-Type: application/json');
session_start();

require_once 'db.php';  // Đường dẫn đúng đến file kết nối DB

// Kiểm tra user đã đăng nhập chưa
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Bạn chưa đăng nhập']);
    exit;
}

$email = $_SESSION['user']['email'] ?? null;
if (!$email) {
    http_response_code(401);
    echo json_encode(['error' => 'Session user không hợp lệ']);
    exit;
}

// Lấy user_id từ email
$stmtUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmtUser->bind_param("s", $email);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
if ($resUser->num_rows !== 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Người dùng không tồn tại']);
    exit;
}
$user = $resUser->fetch_assoc();
$user_id = (int)$user['id'];

// Đọc dữ liệu JSON gửi lên
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dữ liệu không hợp lệ']);
    exit;
}

$title = $data['title'] ?? '';
$content = $data['content'] ?? '';
$tags = isset($data['tags']) ? json_encode($data['tags']) : '[]';
$pinned = isset($data['pinned']) ? (int)$data['pinned'] : 0;

// Chuyển thời gian ISO string thành timestamp millis
$updatedAt = isset($data['updatedAt']) ? strtotime($data['updatedAt']) * 1000 : time() * 1000;
$createdAt = isset($data['createdAt']) ? strtotime($data['createdAt']) * 1000 : time() * 1000;

if (isset($data['id']) && is_numeric($data['id'])) {
    $id = (int)$data['id'];

    // Cập nhật ghi chú thuộc user đang đăng nhập
    $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, tags=?, pinned=?, updated_at=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sssiiii", $title, $content, $tags, $pinned, $updatedAt, $id, $user_id);
    $result = $stmt->execute();

    if ($result && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật ghi chú thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi khi cập nhật ghi chú hoặc ghi chú không thuộc user']);
    }
} else {
    // Thêm ghi chú mới với user_id
    $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, tags, pinned, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiii", $user_id, $title, $content, $tags, $pinned, $createdAt, $updatedAt);
    $result = $stmt->execute();

    if ($result) {
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Thêm ghi chú thành công', 'id' => $newId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi khi thêm ghi chú']);
    }
}
?>
