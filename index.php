<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judiciary Information System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .header {
            background: #2c3e50;
            color: white;
            width: 100%;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 3rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .container {
            max-width: 800px;
            padding: 2rem;
            text-align: center;
        }
        .welcome-text {
            margin-bottom: 3rem;
            color: #2c3e50;
            line-height: 1.6;
        }
        .auth-buttons {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .auth-button {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
        }
        .signin {
            background: #3498db;
            color: white;
        }
        .signup {
            background: #2ecc71;
            color: white;
        }
        .auth-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Judiciary Information System</h1>
    </header>
    <main class="container">
        <div class="welcome-text">
            <h2>Welcome to the Judiciary Information System</h2>
            <p>A comprehensive platform for managing judicial processes efficiently and transparently.</p>
            <p>Access case information, manage court schedules, and handle legal documentation all in one place.</p>
        </div>
        <div class="auth-buttons">
            <a href="login.php" class="auth-button signin">Sign In</a>
            <a href="signup.php" class="auth-button signup">Sign Up</a>
        </div>
    </main>
</body>
</html>