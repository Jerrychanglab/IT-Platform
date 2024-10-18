<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $pageType = $_POST['pageType'];

    define('SECURE_ACCESS', true);
    $users = include 'users.php';

    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        $role = $users[$username]['role'];

        if ($pageType === 'admin' && $role !== 'admin') {
            $error = '您沒有權限登入管理頁面！';
        } else {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['pageType'] = $pageType;

            header('Location: /index.php');
            exit;
        }
    } else {
        $error = '無效的用戶名或密碼！';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>IWE系統登入</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        form {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            position: relative;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .error {
            color: #ffffff;
            background-color: #e74c3c;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #007B8F;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }

        button:hover {
            background-color: #005f6b;
        }

        select {
            background-color: #fff;
            font-size: 16px;
            color: #003060;
            appearance: none;
            cursor: pointer;
            font-weight: bold;
        }

        select:focus {
            outline: none;
            border-color: #007B8F;
            box-shadow: 0 0 5px rgba(0, 123, 143, 0.5);
        }
    </style>
</head>

<body>
    <form method="post" action="Login.php">
        <h1>登入</h1>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <label for="username">用戶名:</label>
        <input type="text" id="username" name="username" required>

        <label for="password">密碼:</label>
        <input type="password" id="password" name="password" required>

        <label for="pageType">選擇頁面:</label>
        <select id="pageType" name="pageType" required>
            <option value="user">使用者-頁面</option>
            <option value="admin">管理者-頁面</option>
        </select>

        <button type="submit">登入</button>
    </form>
</body>

</html>
