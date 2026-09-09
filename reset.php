<?php
include("connection.php");
session_start();

// Load .env from this app folder when present (do not fatal if missing)
if (file_exists(__DIR__ . '/loadEnv.php')) {
    require_once __DIR__ . '/loadEnv.php';
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        loadEnv($envPath);
    }
}
include(__DIR__ . "/email_functions.php");

$error = "";
$success = "";

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

/**
 * Find an active user by personal email or UR email.
 */
function findUserByEmail($connection, $email) {
    $identifier = strtolower(trim($email));
    if ($identifier === '') {
        return null;
    }

    $sql = "SELECT id, names, email, ur_email, active
            FROM users
            WHERE LOWER(email) = ? OR LOWER(ur_email) = ?
            LIMIT 1";
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

if (isset($_POST["reset"])) {
    if ($step === 1) {
        $email = trim($_POST['email'] ?? '');
        $row = findUserByEmail($connection, $email);

        if ($row) {
            if ((string)$row['active'] === '1') {
                $names = $row['names'];
                $resetCode = (string)rand(100000, 999999);
                $userId = (int)$row['id'];

                $sqlUpdate = "UPDATE users SET resetcode = ? WHERE id = ?";
                $stmt = $connection->prepare($sqlUpdate);
                if ($stmt) {
                    $stmt->bind_param('si', $resetCode, $userId);
                    if ($stmt->execute()) {
                        $mailResult = sendResetPasswordEmail($email, $names, $resetCode);
                        if ($mailResult === true) {
                            header("Location: reset.php?step=2&email=" . urlencode($email));
                            exit;
                        }
                        $error = is_string($mailResult)
                            ? $mailResult
                            : "Failed to send reset email. Please try again later.";
                    } else {
                        $error = "Could not save reset code. Ensure the users.resetcode column exists.";
                    }
                    $stmt->close();
                } else {
                    $error = "Could not prepare reset update. Ensure the users.resetcode column exists.";
                }
            } else {
                $error = "This account is deactivated.";
            }
        } else {
            $error = "Email not found.";
        }
    }

    if ($step === 2) {
        $resetCode = trim($_POST['reset_code'] ?? '');
        $row = findUserByEmail($connection, $email);

        if ($row && $resetCode !== '') {
            $userId = (int)$row['id'];
            $sql = "SELECT id FROM users WHERE id = ? AND resetcode = ? AND resetcode IS NOT NULL AND resetcode != '0' LIMIT 1";
            $stmt = $connection->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('is', $userId, $resetCode);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows === 1) {
                    $_SESSION['code'] = $resetCode;
                    $_SESSION['reset_user_id'] = $userId;
                    header("Location: reset.php?step=3&email=" . urlencode($email));
                    exit;
                }
                $stmt->close();
            }
        }
        $error = "Invalid reset code.";
    }

    if ($step === 3) {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $userId = isset($_SESSION['reset_user_id']) ? (int)$_SESSION['reset_user_id'] : 0;
        $sessionCode = $_SESSION['code'] ?? '';

        if ($userId <= 0 || $sessionCode === '') {
            $error = "Reset session expired. Please try again.";
        } elseif ($newPassword === $confirmPassword) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sqlUpdate = "UPDATE users SET password = ?, resetcode = NULL WHERE id = ? AND resetcode = ?";
            $stmt = $connection->prepare($sqlUpdate);
            if ($stmt) {
                $stmt->bind_param('sis', $hashedPassword, $userId, $sessionCode);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    session_destroy();
                    header("Location: login.php?reset=success");
                    exit;
                }
                $error = "Failed to reset password. Please try again.";
                $stmt->close();
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        } else {
            $error = "Passwords do not match.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Reset Password</title>
  <link href="./Dashboard/assets/img/icon1.png" rel="icon">
  <link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="./Dashboard/assets/css/style.css" rel="stylesheet">
  <style>
    .logo1 { width: 70%; height: auto; margin-bottom: 10px; }
  </style>
</head>

<body>
  <main>
    <div class="container">
      <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">
              <div class="card mb-3">
                <div class="card-body">
                  <div class="pt-4 pb-2">
                    <div class="row">
                      <img class="logo1" src="./assets/img/ur.png" alt="">
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                      <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                      <?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                    <form method="post" action="reset.php?step=1">
                      <div class="col-12">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                      </div>
                      <br>
                      <div class="col-12">
                        <button class="btn btn-primary w-100" name="reset" type="submit">Send Reset Code</button>
                      </div>
                    </form>
                    <?php elseif ($step === 2): ?>
                    <form method="post" action="reset.php?step=2&email=<?php echo urlencode($email); ?>">
                      <div class="col-12">
                        <label for="reset_code" class="form-label">Enter Reset Code</label>
                        <input type="text" name="reset_code" class="form-control" required>
                      </div>
                      <br>
                      <div class="col-12">
                        <button class="btn btn-primary w-100" name="reset" type="submit">Verify Code</button>
                      </div>
                    </form>
                    <?php elseif ($step === 3 && isset($_SESSION['code'])): ?>
                    <form method="post" action="reset.php?step=3&email=<?php echo urlencode($email); ?>">
                      <div class="col-12">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                      </div>
                      <div class="col-12">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                      </div>
                      <br>
                      <div class="col-12">
                        <button class="btn btn-primary w-100" name="reset" type="submit">Reset Password</button>
                      </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <p>Invalid step. Please try again.</p>
                    </div>
                    <button class="btn btn-outline-primary"><a href="reset.php">Try again </a></button>
                <?php endif; ?>

                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script src="assets/js/main.js"></script>
</body>
</html>
