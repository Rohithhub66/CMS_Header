<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role  = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, must_reset) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$name, $email, $pass, $role]);

    echo "User registered!";
}
?>
<form method="post">
    Name: <input type="text" name="name" required><br>
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    Role: 
    <select name="role">
        <option value="admin">Admin</option>
        <option value="employee">Employee</option>
    </select><br>
    <input type="submit" value="Register">
</form>