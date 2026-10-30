<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyShop - Welcome</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 24px 0;
            text-align: center;
        }
        .header h1 { margin: 0 0 10px; font-size: 2.5em; }
        .header p { margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .welcome-text {
            text-align: center;
            margin: 70px auto;
            font-size: 1.2em;
        }
        .signup-link {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 18px;
            margin-top: 20px;
        }
        .signup-link:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>MyShop</h1>
            <p>Your one-stop destination for amazing products</p>
        </div>
    </div>
    <main class="container">
        <div class="welcome-text">
            <h2>Welcome to MyShop</h2>
            <p>Join our community today to access exclusive deals and features!</p>
            <a href="register.php" class="signup-link">Create Your Account</a>
        </div>
    </main>
</body>
</html>
