<?php
// Load Composer's autoloader
require './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Access environment variables
$send_email = getenv('SEND_EMAIL');
$app_password = getenv('APP_PASSWORD');
$url = getenv('WEB_URL');

function sendWelcomeEmail($recipientEmail, $name, $password = null) {
    global $send_email, $app_password,$url;
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $send_email; // Use the correct variable
        $mail->Password = $app_password; // Use the correct variable
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($send_email, 'University of Rwanda'); // Correct variable usage
        $mail->addAddress($recipientEmail);

        // Generate email body
        $emailBody = generateWelcomeEmailBody($name, $password);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to the University of Rwanda';
        $mail->Body = $emailBody;
        $mail->AltBody = "Dear $name,\n\nWelcome to the University of Rwanda. " .
                         ($password ? "Your temporary password is: $password.\n\n" : "") .
                         "We look forward to supporting your success.";

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}


function generateWelcomeEmailBody($name, $password = null) {
    global $url;
    $passwordSection = $password ? "<p>Your temporary password is: <strong>$password</strong></p>" : "";

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
            .container { width: 100%; max-width: 600px; margin: auto; background-color: #f4f4f4; padding: 10px; }
            .header { background-color: #00428c; color: #fff; padding: 10px; text-align: center; }
            .content { background-color: #fff; padding: 10px; border-radius: 5px; }
            .footer { background-color: #00428c; color: #fff; text-align: center; padding: 10px; font-size: 12px; }
            button { background-color: #00428c; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to the University of Rwanda</h1>
            </div>
            <div class='content'>
                <p>Dear $name,</p>

                 <p>Your Student Card MIS system account has been created successfully. To access your student card and other services, please click the link below:</p>
                <p><a href='$url/studentcard/login.php' target='_blank'>Access Your Student Card MIS</a>or Click here to reset your 
                own password <a href='$url/studentcard/reset.php' target='_blank'>Reset your password</a> </p>

                <!-- Include password section if a password is provided -->
                $passwordSection

               
                <p>If you have any questions or need assistance, feel free to contact us.</p>
                <p>Warm regards,</p>
                <p><strong>The University of Rwanda Team</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; University of Rwanda. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
}



function sendResetPasswordEmail($recipientEmail, $name, $resetCode) {
    global $send_email, $app_password,$url;
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $send_email;
        $mail->Password = $app_password;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($send_email, 'University of Rwanda');
        $mail->addAddress($recipientEmail);

        // Generate email body
        $emailBody = generateResetPasswordEmailBody($name, $resetCode,$recipientEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body = $emailBody;
        $mail->AltBody = "Dear $name,\n\nWe received a request to reset your password. " . 
                         "Your password reset code is: $resetCode\n\n" . 
                         "Please use this code to reset your password.";

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function generateResetPasswordEmailBody($name, $resetCode, $recipientEmail) {
    global $url;
    // Escape email for security
    $safeEmail = htmlspecialchars($recipientEmail, ENT_QUOTES, 'UTF-8');
    $link = "$url/studentcard/reset.php?step=2&email=$safeEmail";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
            .container { width: 100%; max-width: 600px; margin: auto; background-color: #f4f4f4; padding: 20px; }
            .header { background-color: #00428c; color: #ffffff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #ffffff; padding: 20px; border-radius: 0 0 5px 5px; }
            .footer { background-color: #00428c; color: #ffffff; text-align: center; padding: 15px; font-size: 12px; margin-top: 10px; }
            a { color: #00428c; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Password Reset Request</h1>
            </div>
            <div class='content'>
                <p>Dear $name,</p>
                <p>We received a request to reset your password. Please use the following code to reset your password:</p>
                <p><strong>$resetCode</strong></p>
                <p><a href='$link' target='_blank'>Click here to reset your password</a></p>
                <p>Warm regards,</p>
                <p><strong>The University of Rwanda Team</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date("Y") . " University of Rwanda. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
}





function sendRejectionEmail($recipientEmail, $regNumber) {
    $sendemail = 'urhuyecards@gmail.com'; 
    $password = ''; 

    // Load PHPMailer
    // require 'PHPMailer/PHPMailerAutoload.php'; // Adjust the path if needed

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $sendemail;
        $mail->Password = $password;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($sendemail, 'Card Rejected');
        $mail->addAddress($recipientEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Student Card Rejection';
        $mail->Body = "<!DOCTYPE html>
        <html>
        <head>
            <style>
                body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                .container { width: 100%; max-width: 600px; margin: auto; background-color: #f4f4f4; padding: 20px; }
                .header { background-color: brown; color: #fff; padding: 10px; text-align: center; }
                .content { background-color: #fff; padding: 20px; border-radius: 5px; }
                .footer { background-color: brown; color: #fff; text-align: center; padding: 10px; font-size: 12px; }
                a { color: #1a73e8; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Student Card Rejection</h1>
                </div>
                <div class='content'>
                    <p>Dear Student,</p>
                    <p>We regret to inform you that your student card request associated with the registration number <strong>$regNumber</strong> has been <strong>rejected</strong>.</p>
                    <p><strong>Reason:</strong> The image provided was inappropriate or did not meet our standards.</p>
                    <p>Please submit a valid image and other wise you will struggle !!</p>
                    <p>Thank you for your understanding.</p>
                </div>
                <div class='footer'>
                    <p>&copy; University of Rwanda. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "Your student card request with registration number $regNumber has been rejected due to an inappropriate image. Please submit a valid image.";

        $mail->send();
        echo "<script>alert('rejected succesfully');</script>";
    } catch (Exception $e) {
        echo "<script>alert('Email could not be sent. Mailer Error: {$mail->ErrorInfo}');</script>";
    }
}
?>
