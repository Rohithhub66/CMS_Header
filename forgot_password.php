<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?");
        $stmt->execute([$token, $expiry, $user['id']]);

        $resetLink = "http://localhost/lms/reset_password.php?token=$token";

        echo "<p>Email sent! Reset link: <a href='$resetLink'>$resetLink</a></p>";
        // For real usage, send the link using mail() or PHPMailer
    } else {
        echo "No user found with that email.";
    }
}
?>
<h2>Forgot Password</h2>
<form method="post">
    Enter your email: <input type="email" name="email" required><br>
    <input type="submit" value="Send Reset Link">
</form>
