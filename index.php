<?php
session_start();
include 'db.php'; // file kết nối db, chỉnh lại theo bạn

// PHPMailer
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$login_error = $register_error = $forgot_error = '';
$forgot_success = '';

// XỬ LÝ ĐĂNG NHẬP
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email_login'] ?? '';
    $password = $_POST['password_login'] ?? '';

    if (!$email || !$password) {
        $login_error = "Vui lòng nhập đủ thông tin đăng nhập.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'] ?? '',
            ];
            header('Location: home.php');
            exit;
        } else {
            $login_error = "Email hoặc mật khẩu không đúng.";
        }
    }
}

// XỬ LÝ ĐĂNG KÝ
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $email = $_POST['email_register'] ?? '';
    $password = $_POST['password_register'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (!$email || !$password || !$confirm) {
        $register_error = "Vui lòng nhập đủ thông tin đăng ký.";
    } elseif ($password !== $confirm) {
        $register_error = "Mật khẩu xác nhận không khớp.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $register_error = "Email đã được đăng ký.";
        } else {
            $hash_pass = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $email, $hash_pass);
            if ($stmt->execute()) {
                $_SESSION['user'] = [
                    'id' => $conn->insert_id,
                    'email' => $email,
                    'name' => '',
                ];
                header('Location: home.php');
                exit;
            } else {
                $register_error = "Lỗi hệ thống, vui lòng thử lại sau.";
            }
        }
    }
}

// XỬ LÝ QUÊN MẬT KHẨU
if (isset($_POST['action']) && $_POST['action'] === 'forgot') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $forgot_error = "⚠️ Vui lòng nhập email.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();

            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expires, $email);
            $stmt->execute();

            $resetLink = "http://localhost/note_app/reset_password.php?token=$token";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'nobanh0660@gmail.com';
                $mail->Password = 'adgu uqwj fhqq bhgn'; // App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('nohaimai610@hotmail.com', 'Note App');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Đặt lại mật khẩu';
                $mail->Body = "Nhấn vào liên kết sau để đặt lại mật khẩu: <a href='$resetLink'>$resetLink</a>";

                $mail->send();
                $forgot_success = "✅ Email đặt lại mật khẩu đã được gửi. Vui lòng kiểm tra hộp thư.";
            } catch (Exception $e) {
                $forgot_error = "❌ Không thể gửi email: {$mail->ErrorInfo}";
            }
        } else {
            $forgot_error = "⚠️ Email không tồn tại trong hệ thống.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Đăng nhập / Đăng ký / Quên mật khẩu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="style.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      background: linear-gradient(135deg, #fff1f0, #fce4ec);
      font-family: 'Inter', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      animation: bgPulse 12s ease-in-out infinite alternate;
      transition: background 1.5s ease-in-out;
    }

    @keyframes bgPulse {
      0% { background: linear-gradient(135deg, #fff1f0, #fce4ec); }
      100% { background: linear-gradient(135deg, #fce4ec, #ffe2e9); }
    }

    .container {
      background: white;
      padding: 30px 35px;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(233, 30, 99, 0.15);
      width: 360px;
      max-width: 95vw;
      position: relative;
      overflow: hidden;
    }

    h2 {
      margin-bottom: 20px;
      font-weight: 700;
      text-align: center;
      color: #c2185b;
      text-transform: uppercase;
      letter-spacing: 1.2px;
    }

    .tab-buttons {
      display: flex;
      margin-bottom: 22px;
      border-bottom: 3px solid #f0a3b8;
      user-select: none;
    }

    .tab-buttons button {
      flex: 1;
      padding: 12px 0;
      border: none;
      background: transparent;
      font-weight: 700;
      font-size: 16px;
      color: #bb467c;
      cursor: pointer;
      border-bottom: 4px solid transparent;
      transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
      position: relative;
      overflow: hidden;
    }

    .tab-buttons button::after {
      content: "";
      position: absolute;
      left: 50%;
      bottom: 0;
      width: 0%;
      height: 4px;
      background-color: #e91e63;
      transition: width 0.3s ease, left 0.3s ease;
      transform: translateX(-50%);
      border-radius: 2px;
    }

    .tab-buttons button:hover::after {
      width: 80%;
      left: 50%;
    }

    .tab-buttons button.active {
      color: #e91e63;
      border-bottom-color: #e91e63;
    }

    .tab-buttons button.active::after {
      width: 100%;
      left: 50%;
    }

    form {
      display: none;
      opacity: 0;
      transform: translateY(15px);
      transition: opacity 0.5s ease, transform 0.5s ease;
      will-change: opacity, transform;
    }

    form.active {
      display: block;
      opacity: 1;
      transform: translateY(0);
    }

    label {
      display: block;
      margin-bottom: 6px;
      color: #7a375a;
      font-weight: 700;
      font-size: 14px;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px 14px;
      margin-bottom: 18px;
      border: 2px solid #f8bbd0;
      border-radius: 8px;
      font-size: 15px;
      outline: none;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      background: #fff0f4;
      color: #4a2331;
      box-shadow: inset 0 0 8px rgba(233, 30, 99, 0.1);
    }

    input[type="email"]:focus,
    input[type="password"]:focus {
      border-color: #e91e63;
      box-shadow: 0 0 8px #e91e63cc;
    }

    button.submit-btn {
      width: 100%;
      background: linear-gradient(90deg, #e91e63, #bb467c);
      border: none;
      color: white;
      font-weight: 700;
      padding: 14px;
      font-size: 17px;
      border-radius: 8px;
      cursor: pointer;
      box-shadow: 0 6px 12px rgba(233, 30, 99, 0.5);
      transition: background-position 0.4s ease, box-shadow 0.3s ease;
      background-size: 200% 100%;
      background-position: left center;
    }

    button.submit-btn:hover {
      background-position: right center;
      box-shadow: 0 8px 18px rgba(233, 30, 99, 0.7);
    }

    .error-message,
    .success-message {
      margin-bottom: 18px;
      font-weight: 700;
      font-size: 14px;
      text-align: center;
      padding: 8px 10px;
      border-radius: 6px;
      user-select: none;
      animation: fadeInMessage 0.8s ease forwards;
    }

    .error-message {
      background-color: #fce4e4;
      color: #d32f2f;
      box-shadow: 0 2px 6px #d32f2f50;
    }

    .success-message {
      background-color: #d9f0d9;
      color: #388e3c;
      box-shadow: 0 2px 6px #388e3c50;
    }

    @keyframes fadeInMessage {
      from {opacity: 0; transform: translateY(-8px);}
      to {opacity: 1; transform: translateY(0);}
    }

    .link-forgot {
      display: block;
      margin-top: -10px;
      margin-bottom: 15px;
      text-align: right;
      font-size: 13px;
      color: #e91e63;
      cursor: pointer;
      user-select: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .link-forgot:hover {
      color: #bb467c;
      text-decoration: underline;
    }

  </style>
</head>
<body>
  <div class="container">
    <div class="tab-buttons">
      <button id="tab-login" class="active" type="button">Đăng nhập</button>
      <button id="tab-register" type="button">Đăng ký</button>
      <button id="tab-forgot" type="button">Quên mật khẩu</button>
    </div>

    <!-- Form Đăng nhập -->
    <form method="POST" id="form-login" class="active" novalidate>
      <h2>Đăng nhập</h2>
      <?php if ($login_error): ?>
        <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>
      <input type="hidden" name="action" value="login" />
      <label for="email_login">Email</label>
      <input type="email" id="email_login" name="email_login" required autocomplete="username" />
      <label for="password_login">Mật khẩu</label>
      <input type="password" id="password_login" name="password_login" required autocomplete="current-password" />
      <span class="link-forgot" onclick="showTab('forgot')">Quên mật khẩu?</span>
      <button type="submit" class="submit-btn">Đăng nhập</button>
    </form>

    <!-- Form Đăng ký -->
    <form method="POST" id="form-register" novalidate>
      <h2>Đăng ký</h2>
      <?php if ($register_error): ?>
        <div class="error-message"><?= htmlspecialchars($register_error) ?></div>
      <?php endif; ?>
      <input type="hidden" name="action" value="register" />
      <label for="email_register">Email</label>
      <input type="email" id="email_register" name="email_register" required autocomplete="email" />
      <label for="password_register">Mật khẩu</label>
      <input type="password" id="password_register" name="password_register" required autocomplete="new-password" />
      <label for="password_confirm">Xác nhận mật khẩu</label>
      <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" />
      <button type="submit" class="submit-btn">Đăng ký</button>
    </form>

    <!-- Form Quên mật khẩu -->
    <form method="POST" id="form-forgot" novalidate>
      <h2>Quên mật khẩu</h2>
      <?php if ($forgot_success): ?>
        <div class="success-message"><?= htmlspecialchars($forgot_success) ?></div>
      <?php elseif ($forgot_error): ?>
        <div class="error-message"><?= htmlspecialchars($forgot_error) ?></div>
      <?php endif; ?>
      <input type="hidden" name="action" value="forgot" />
      <label for="email_forgot">Email</label>
      <input type="email" id="email_forgot" name="email" required autocomplete="email" />
      <button type="submit" class="submit-btn">Gửi yêu cầu</button>
    </form>
  </div>

<script>
  const btnLogin = document.getElementById('tab-login');
  const btnRegister = document.getElementById('tab-register');
  const btnForgot = document.getElementById('tab-forgot');

  const formLogin = document.getElementById('form-login');
  const formRegister = document.getElementById('form-register');
  const formForgot = document.getElementById('form-forgot');

  function showTab(tab) {
    // Xóa active tất cả tab và form
    [btnLogin, btnRegister, btnForgot].forEach(btn => btn.classList.remove('active'));
    [formLogin, formRegister, formForgot].forEach(form => {
      form.classList.remove('active');
    });

    if (tab === 'login') {
      btnLogin.classList.add('active');
      formLogin.classList.add('active');
    } else if (tab === 'register') {
      btnRegister.classList.add('active');
      formRegister.classList.add('active');
    } else if (tab === 'forgot') {
      btnForgot.classList.add('active');
      formForgot.classList.add('active');
    }
  }

  btnLogin.addEventListener('click', () => showTab('login'));
  btnRegister.addEventListener('click', () => showTab('register'));
  btnForgot.addEventListener('click', () => showTab('forgot'));

  // Mở tab tương ứng khi có lỗi hoặc thành công từ backend
  <?php if ($login_error): ?>
    showTab('login');
  <?php elseif ($register_error): ?>
    showTab('register');
  <?php elseif ($forgot_error || $forgot_success): ?>
    showTab('forgot');
  <?php else: ?>
    showTab('login');
  <?php endif; ?>

</script>
</body>
</html>
