<?php
// goodbye.php - Shown after account deletion
session_start();
session_destroy(); // Make sure session is destroyed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goodbye | Secure Locker</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        body.dark-theme {
            background: linear-gradient(135deg, #1e1e1e 0%, #2d2d2d 100%);
        }

        .goodbye-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s ease;
        }

        body.dark-theme .goodbye-container {
            background: #2d2d2d;
            border: 1px solid rgba(255,255,255,0.1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: wave 2s infinite;
            color: #4CAF50;
        }

        body.dark-theme .icon {
            color: #4CAF50;
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(10deg); }
            75% { transform: rotate(-10deg); }
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 32px;
        }

        body.dark-theme h1 {
            color: #ffffff;
        }

        p {
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
            line-height: 1.6;
        }

        body.dark-theme p {
            color: #e0e0e0;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: linear-gradient(135deg, #2980b9, #1c6ea4);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.4);
        }

        body.dark-theme .btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }

        body.dark-theme .btn:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            color: #888;
            font-size: 14px;
        }

        body.dark-theme .footer {
            border-top-color: #404040;
            color: #bdc3c7;
        }
    </style>
</head>
<body>
    <div class="goodbye-container">
        <div class="icon">👋</div>
        <h1>Goodbye, Friend!</h1>
        <p>Your account has been successfully deleted.<br>We're sorry to see you go.</p>
        <p style="font-size: 14px; color: #888; margin-bottom: 20px;">
            All your files have been permanently removed from our servers.
        </p>
        
        <a href="login.php" class="btn">
            <span>🔐</span> Return to Login
        </a>
        
        <div class="footer">
            <p>Thank you for using Secure Locker</p>
            <p style="font-size: 12px; margin-top: 5px;">Hope to see you again someday</p>
        </div>
    </div>

    <script>
        // Apply saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
            }
        });
    </script>
</body>
</html>