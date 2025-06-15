<?php
session_start();
require 'db.php';

// Use 'email' or 'username' based on your DB
$login_column = 'email';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $mfa_code = trim($_POST['mfa_code'] ?? '');

    if (!$login_input || !$password) {
        $error = 'Please enter your ' . ($login_column === 'email' ? 'email' : 'username') . ' and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE $login_column = ?");
        $stmt->execute([$login_input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            // Optional: MFA logic placeholder (can expand as needed)
            $mfa_enabled = isset($user['mfa_enabled']) && $user['mfa_enabled'];
            if ($mfa_enabled) {
                if (!$mfa_code) {
                    $error = 'MFA code required.';
                } else {
                    // Replace with real MFA validation as needed
                    if ($mfa_code !== '123456') {
                        $error = 'Invalid MFA code.';
                    }
                }
            }
            if (!$error) {
                unset($user['password']);
                $_SESSION['user'] = $user;
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LMS Login</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(120deg, #1a2238 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .login-outer {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-panel {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(30,64,175,0.13);
            display: flex;
            flex-direction: row;
            align-items: stretch;
            max-width: 820px;
            width: 100%;
            min-height: 420px;
            overflow: hidden;
        }
        .login-side {
            background: #3b82f6;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-width: 310px;
            max-width: 350px;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .login-side img {
            width: 100px;
            margin-bottom: 1.4rem;
            border-radius: 14px;
            background: #fff;
            padding: 8px;
            box-shadow: 0 4px 14px 0 #23294621;
        }
        .login-side h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
            letter-spacing: 1px;
        }
        .login-side p {
            color: #e0e7ef;
            margin-bottom: 0;
            font-size: 1.12rem;
            letter-spacing: 0.03em;
        }
        .login-form-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.7rem;
        }
        .login-form {
            width: 100%;
            max-width: 340px;
        }
        .login-title {
            font-size: 1.44rem;
            font-weight: 600;
            color: #232946;
            margin-bottom: 1.1rem;
            letter-spacing: 0.5px;
            text-align: left;
        }
        .form-label {
            color: #232946;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .form-control {
            border-radius: 7px;
            border: 1px solid #bfc8e6;
            padding: 0.85rem 0.9rem;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.15rem #3b82f655;
        }
        .btn-primary {
            background: #3b82f6;
            border: none;
            border-radius: 7px;
            padding: 0.7rem 0;
            font-size: 1.08rem;
            font-weight: 500;
            width: 100%;
            margin-top: 0.3rem;
            transition: background 0.12s;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: #232946;
        }
        .form-check-label {
            color: #444;
            font-size: 0.97rem;
        }
        .mfa-toggle {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 0.7rem;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .alert-danger {
            margin-bottom: 1.1rem;
            padding: 0.7em 1em;
            font-size: 1.02rem;
        }
        .login-links {
            margin-top: 1.3rem;
            font-size: 0.98rem;
            text-align: center;
        }
        .login-links a {
            color: #3b82f6;
            text-decoration: underline;
            margin: 0 0.5em;
            transition: color 0.12s;
        }
        .login-links a:hover {
            color: #232946;
        }
        .forgot-link {
            font-size: 0.96em;
            color: #3b82f6;
            text-decoration: underline;
            margin: 0 auto;
            display: block;
            text-align: center;
            margin-top: 0.8rem;
            transition: color 0.12s;
        }
        .forgot-link:hover {
            color: #232946;
        }
        .password-toggle-wrapper {
            position: relative;
        }
        .show-password-btn {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            z-index: 2;
            color: #3b82f6;
            font-size: 1.15em;
            cursor: pointer;
            padding: 0 2px;
        }
        @media (max-width: 900px) {
            .login-panel {
                flex-direction: column;
                min-height: unset;
                max-width: 98vw;
            }
            .login-side {
                flex-direction: row;
                min-width: unset;
                max-width: unset;
                width: 100%;
                padding: 1.4rem 1.1rem;
                border-radius: 0 0 20px 20px;
                box-shadow: none;
            }
            .login-form-wrap {
                padding: 2rem 1.2rem 1.8rem 1.2rem;
            }
        }
        @media (max-width: 600px) {
            .login-panel {
                min-width: 98vw;
                padding: 0;
                max-width: 100vw;
            }
            .login-side {
                padding: 0.8rem 0.2rem;
            }
            .login-form-wrap {
                padding: 1.2rem 0.2rem 1.2rem 0.2rem;
            }
        }
    </style>
</head>
<body>
<div class="login-outer">
    <div class="login-panel">
        <div class="login-side">
            <img src="https://cdn-icons-png.flaticon.com/512/3135/3135768.png" alt="LMS Logo">
            <h1>LMS Portal</h1>
            <p>Welcome to your organization's Learning Management System.<br>
            Access your training, simulate phishing, and more.</p>
        </div>
        <div class="login-form-wrap">
            <form class="login-form" method="post" autocomplete="off">
                <div class="login-title">Sign in to your account</div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <label for="login" class="form-label"><?= ucfirst($login_column) ?></label>
                <input
                    type="<?= $login_column === 'email' ? 'email' : 'text' ?>"
                    id="login"
                    name="login"
                    class="form-control"
                    required
                    autofocus
                    placeholder="Enter your <?= $login_column ?>"
                >

                <label for="password" class="form-label">Password</label>
                <div class="password-toggle-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required aria-label="Password">
                    <button type="button" class="show-password-btn" tabindex="-1" onclick="togglePasswordVisibility()" aria-label="Show or hide password">
                        <span id="showPwdIcon"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" fill="none" viewBox="0 0 24 24"><path fill="#3b82f6" d="M12 4.5c-7 0-10 7.13-10 7.5s3 7.5 10 7.5 10-7.13 10-7.5-3-7.5-10-7.5Zm0 12c-4.41 0-7.21-3.6-8.32-5 .99-1.31 3.6-5 8.32-5s7.33 3.69 8.32 5c-1.11 1.4-3.91 5-8.32 5Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/></svg></span>
                    </button>
                </div>

                <div class="mfa-toggle">
                    <input class="form-check-input" type="checkbox" id="mfa-toggle-checkbox" onclick="toggleMfaInput()">
                    <label class="form-check-label" for="mfa-toggle-checkbox">
                        Use MFA (Multi-factor Authentication) <span style="color:#999;font-size:0.93em;">(optional)</span>
                    </label>
                </div>
                <input type="text" id="mfa_code" name="mfa_code" class="form-control" maxlength="6" placeholder="MFA Code" style="display:none;">

                <button type="submit" class="btn btn-primary mt-2">Sign In</button>
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                <div class="login-links mt-3">
                    <a href="privacy_policy.php" target="_blank">Privacy Policy</a>
                    |
                    <a href="terms_conditions.php" target="_blank">Terms &amp; Conditions</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const showPwdIcon = document.getElementById('showPwdIcon');
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            showPwdIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" fill="none" viewBox="0 0 24 24"><path fill="#3b82f6" d="M12 4.5c-7 0-10 7.13-10 7.5s3 7.5 10 7.5c1.86 0 3.59-.3 5.03-.8l1.47 1.47a.75.75 0 0 0 1.06-1.06l-16-16a.75.75 0 1 0-1.06 1.06l2.13 2.13C3.11 7.12 2 9.17 2 12c0 .37 3 7.5 10 7.5 2.08 0 4.03-.29 5.71-.8l1.47 1.47a.75.75 0 1 0 1.06-1.06l-16-16a.75.75 0 0 0-1.06 1.06l2.13 2.13ZM12 17c-4.41 0-7.21-3.6-8.32-5 .99-1.31 3.6-5 8.32-5 1.56 0 3 .34 4.24.98l1.51 1.51c-.52.35-.97.76-1.35 1.2L12 11.5a3 3 0 0 0-3 3c0 .41.08.79.22 1.13l-1.45 1.45C8.64 16.18 10.29 17 12 17Zm3-3a1.5 1.5 0 0 1-2.16-1.36c0-.41.08-.79.22-1.13L17.5 10.1c.22.2.43.42.61.66a3 3 0 0 1-3.11 3.24Z"/></svg>`;
        } else {
            passwordInput.type = "password";
            showPwdIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" fill="none" viewBox="0 0 24 24"><path fill="#3b82f6" d="M12 4.5c-7 0-10 7.13-10 7.5s3 7.5 10 7.5 10-7.13 10-7.5-3-7.5-10-7.5Zm0 12c-4.41 0-7.21-3.6-8.32-5 .99-1.31 3.6-5 8.32-5s7.33 3.69 8.32 5c-1.11 1.4-3.91 5-8.32 5Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/></svg>`;
        }
    }
    function toggleMfaInput() {
        const cb = document.getElementById('mfa-toggle-checkbox');
        const mfaInput = document.getElementById('mfa_code');
        mfaInput.style.display = cb.checked ? 'block' : 'none';
        if (!cb.checked) mfaInput.value = '';
    }
</script>
</body>
</html>