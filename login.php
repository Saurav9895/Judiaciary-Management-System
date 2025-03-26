<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Judiciary Information System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            background-color: var(--primary-color);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            margin-right: 10px;
        }
        
        .login-container {
            max-width: 420px;
            margin: 2rem auto;
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            flex-grow: 0;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--secondary-color);
        }
        
        h2 {
            text-align: center;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            position: relative;
        }
        
        h2::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--secondary-color);
            margin: 10px auto;
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 38px;
            color: var(--primary-color);
        }
        
        button {
            width: 100%;
            padding: 12px;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        
        button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
        }
        
        .error {
            color: var(--accent-color);
            margin-bottom: 1rem;
            padding: 10px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 4px;
            border-left: 3px solid var(--accent-color);
        }
        
        .success {
            color: var(--success-color);
            margin-bottom: 1rem;
            padding: 10px;
            background-color: rgba(39, 174, 96, 0.1);
            border-radius: 4px;
            border-left: 3px solid var(--success-color);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #7f8c8d;
        }
        
        .footer-text a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .footer-text a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        
        .forgot-password {
            display: block;
            text-align: right;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-balance-scale"></i> JIS
            </a>
        </div>
    </nav>
    
    <div class="login-container">
        <h2>Welcome Back</h2>
        
        <?php
        if (isset($_GET['signup']) && $_GET['signup'] === 'success') {
            echo "<p class='success'><i class='fas fa-check-circle'></i> Account created successfully! Please login.</p>";
        }
        ?>
        
        <form action="process_login.php" method="POST">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <i class="fas fa-user input-icon"></i>
                <input type="text" id="username" name="username" placeholder="Enter your username or email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <!-- <p class="footer-text">
            Don't have an account? <a href="signup.php">Create one</a>
        </p> -->
    </div>
</body>
</html>