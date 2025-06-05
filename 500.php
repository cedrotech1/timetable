<?php
// session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error | UR-HOSTELS</title>
    <link href="icon1.png" rel="icon">
    <link href="icon1.png" rel="apple-touch-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            backdrop-filter: blur(10px);
            transform: translateY(0);
            transition: transform 0.3s ease;
        }
        .error-container:hover {
            transform: translateY(-5px);
        }
        .logo-container {
            margin-bottom: 1.5rem;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-container img {
            max-width: 180px;
            height: auto;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }
        .skeleton-loader {
            width: 180px;
            height: 120px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
            display: none;
        }
        .skeleton-text {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            display: none;
        }
        .skeleton-error-code {
            width: 200px;
            height: 120px;
            margin: 0 auto 1rem;
        }
        .skeleton-message {
            width: 250px;
            height: 30px;
            margin: 0 auto 1rem;
        }
        .skeleton-description {
            width: 400px;
            height: 60px;
            margin: 0 auto 1.5rem;
        }
        .skeleton-suggestion {
            width: 150px;
            height: 20px;
            margin: 0.5rem 0;
        }
        .skeleton-title {
            width: 180px;
            height: 24px;
            margin-bottom: 1rem;
        }
        .loading .skeleton-text,
        .loading .skeleton-loader {
            display: block;
        }
        .loading .error-code,
        .loading .error-message,
        .loading .error-description,
        .loading .suggestions h5,
        .loading .suggestion-item {
            display: none;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 800;
            color: #dc3545;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            line-height: 1;
        }
        .error-message {
            font-size: 1.4rem;
            color: #343a40;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .error-description {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .back-button {
            padding: 0.8rem 2rem;
            font-size: 1rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            margin: 0.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
        }
        .btn-outline-secondary {
            border: 2px solid #6c757d;
        }
        .suggestions {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }
        .suggestions h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .suggestion-item {
            margin: 0.5rem 0;
            color: #495057;
            transition: all 0.3s ease;
        }
        .suggestion-item:hover {
            transform: translateX(5px);
        }
        .suggestion-item i {
            color: #007bff;
            margin-right: 0.5rem;
            width: 20px;
        }
        .suggestion-item a {
            color: #495057;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .suggestion-item a:hover {
            color: #007bff;
        }
        @media (max-height: 700px) {
            .error-code {
                font-size: 6rem;
            }
            .error-message {
                font-size: 1.5rem;
            }
            .logo-container img {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container loading" id="errorContainer">
        <div class="logo-container">
            <div class="skeleton-loader" id="skeletonLoader"></div>
            <img src="https://snipboard.io/ogVdE9.jpg" alt="Company Logo" class="img-fluid" onerror="handleImageError()" onload="handleImageLoad()">
        </div>
        <div class="skeleton-text skeleton-error-code"></div>
        <div class="error-code">500</div>
        
        <div class="skeleton-text skeleton-message"></div>
        <div class="error-message">Internal Server Error</div>
        
        <div class="skeleton-text skeleton-description"></div>
        <p class="error-description">
            Oops! Something went wrong on our end. We're working to fix the problem. Please try again later.
        </p>
       
        <div class="suggestions">
            <div class="skeleton-text skeleton-title"></div>
            <h5>Here are some helpful links:</h5>
            <div class="suggestion-item">
                <div class="skeleton-text skeleton-suggestion"></div>
                <i class="fas fa-home"></i>
                <a href="index.php">Go to Homepage</a>
            </div>
            <div class="suggestion-item">
                <div class="skeleton-text skeleton-suggestion"></div>
                <i class="fas fa-sync-alt"></i>
                <a href="javascript:location.reload()">Try Again</a>
            </div>
            <div class="suggestion-item">
                <div class="skeleton-text skeleton-suggestion"></div>
                <i class="fas fa-envelope"></i>
                <a href="contact.php">Contact Support</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function handleImageError() {
            document.querySelector('.logo-container img').style.display = 'none';
            document.getElementById('skeletonLoader').style.display = 'block';
        }
        
        function handleImageLoad() {
            document.getElementById('skeletonLoader').style.display = 'none';
            document.querySelector('.logo-container img').style.display = 'block';
            // Simulate content loading
            setTimeout(() => {
                document.getElementById('errorContainer').classList.remove('loading');
            }, 1000);
        }
    </script>
</body>
</html> 