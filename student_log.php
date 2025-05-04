<?php
ob_start();
session_start();
include('../db_config.php');
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users_tbl WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['email'] = $user['email'];
            header("Location: student_db.php");
            exit();
        } else {
            $error_message = "Incorrect password.";
        }
    } else {
        $error_message = "Account does not exist.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">

    <!-- ✅ Include Allura and Playfair Display fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Playfair+Display&display=swap" rel="stylesheet">

    <style>
        body {
            background-image: url('../web resources/images/library.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .student-login-card {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            border-radius: 20px;
            background-color: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            box-shadow: 2px 5px 15px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .student-form-logo {
            width: 200px;
            height: auto;
            margin-bottom: 15px;
        }

        h3 {
            margin-bottom: 20px;
            color: #333;
            font-family: 'Allura', cursive;
            font-size: 36px;
        }

        .form-group label {
            font-weight: bold;
            font-family: 'Playfair Display', serif;
            font-size: 16px;
        }

        .form-group input {
            font-size: 14px;
        }

        .text-error {
            color: red;
            font-size: 14px;
            margin-top: 10px;
        }

        .btn-primary {
            background-color: #766B5D;
            border-color: #766B5D;
        }

        .btn-primary:hover {
            background-color: #5f564c;
            border-color: #5f564c;
        }

        .student-login-card a {
            color: #766B5D;
        }

        .student-login-card a:hover {
            color: #5f564c;
            text-decoration: underline;
        }

       
    
    </style>
</head>

<body>

<div class="student-login-card">
    <img src="../web resources/images/Page Turners b.png" alt="Page Turners Logo" class="student-form-logo">
    <h3>Student Login</h3>

    <form method="POST" action="">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <input type="submit" value="Login" class="btn btn-primary btn-block mt-3">
    </form>

    <p class="mt-3">Don't have an account? <a href="student_register.jsp">Register here</a></p>

    <?php if (!empty($error_message)): ?>
        <p class="text-error"><?= $error_message ?></p>
    <?php endif; ?>
</div>

</body>
</html>
