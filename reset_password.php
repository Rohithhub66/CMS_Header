<?php
require 'db.php';
session_start();

$token = $_GET['token'] ?? null;

// Token-based password reset
if ($token && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Invalid or expired reset link.");
    }
}

// POST request: update password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['token'])) {
        $token = $_POST['token'];
        $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL, must_reset = 0 WHERE id = ?");
            $stmt->execute([$new_pass, $user['id']]);
            echo "Password updated. <a href='index.php'>Login</a>";
            exit;
        } else {
            echo "Invalid or expired token.";
        }
    } else if (isset($_SESSION['user'])) {
        // Reset after login (first time)
        $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $user_id = $_SESSION['user']['id'];

        $stmt = $pdo->prepare("UPDATE users SET password = ?, must_reset = 0 WHERE id = ?");
        $stmt->execute([$new_pass, $user_id]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['user'] = $stmt->fetch();

        header("Location: dashboard.php");
        exit;
    }
}
?>
<h2>Reset Password</h2>
<form method="post">
    <?php if ($token): ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <?php endif; ?>
    New Password: <input type="password" name="password" required><br>
    <input type="submit" value="Reset Password">
</form>