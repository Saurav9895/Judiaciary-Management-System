<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judiciary Information System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Navigation Bar */
        .navbar {
            background: #1a2a3a;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 3px solid #4a6fa5;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .navbar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .navbar-brand i {
            font-size: 1.8rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .welcome-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            padding: 3rem;
            max-width: 800px;
            width: 100%;
            text-align: center;
            margin-bottom: 2rem;
        }
        .welcome-text h2 {
            color: #1a2a3a;
            margin-bottom: 1.5rem;
            font-weight: 400;
            font-size: 2rem;
        }
        .welcome-text p {
            margin-bottom: 1rem;
            color: #495057;
            font-size: 1.1rem;
            line-height: 1.8;
        }
        
        /* Auth Button */
        .auth-button {
            padding: 1rem 3rem;
            font-size: 1.1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            background: #4a6fa5;
            color: white;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 10px rgba(74, 111, 165, 0.3);
            margin-top: 1.5rem;
        }
        .auth-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(74, 111, 165, 0.4);
            background: #3a5a8a;
        }
        
        /* Features */
        .system-features {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin: 3rem 0;
            gap: 1.5rem;
            width: 100%;
            max-width: 1000px;
        }
        .feature {
            flex: 1;
            min-width: 250px;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #4a6fa5;
            text-align: left;
        }
        .feature h3 {
            color: #1a2a3a;
            margin-bottom: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .feature h3 i {
            color: #4a6fa5;
        }
        .feature p {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        footer {
            padding: 1.5rem;
            color: #6c757d;
            font-size: 0.9rem;
            text-align: center;
            width: 100%;
            background: #1a2a3a;
            color: rgba(255,255,255,0.7);
        }
        
        @media (max-width: 768px) {
            .welcome-card {
                padding: 2rem 1.5rem;
            }
            .feature {
                min-width: 100%;
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
    
    <div class="main-content">
        <div class="welcome-card">
            <div class="welcome-text">
                <h2>Secure Judiciary Portal</h2>
                <p>Welcome to the Judiciary Information System (JIS)</p>
                <p>Authorized court personnel may sign in to access case management tools, court schedules, and legal documentation systems.</p>
                
                <a href="login.php" class="auth-button">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
            </div>
        </div>
        
        <div class="system-features">
            <div class="feature">
                <h3><i class="fas fa-gavel"></i> Case Management</h3>
                <p>Comprehensive digital tools for tracking and managing all court cases with real-time updates and notifications.</p>
            </div>
            <!-- <div class="feature">
                <h3><i class="fas fa-file-contract"></i> Document System</h3>
                <p>Secure digital repository for all legal documents with version control and audit trails.</p>
            </div> -->
            <div class="feature">
                <h3><i class="fas fa-calendar-alt"></i> Court Scheduling</h3>
                <p>Integrated calendar system for managing court dates, hearings, and judicial appointments.</p>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            &copy; <?php echo date("Y"); ?> Judiciary Information System. Restricted to authorized personnel only.
        </div>
    </footer>
</body>
</html>