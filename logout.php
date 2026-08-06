<?php
require __DIR__ . '/includes/auth.php';

// Deleting THIS session's row (not every row for the user) is what makes
// logout actually observable from outside this browser tab, without
// signing the account out on its other concurrently-logged-in systems too
// - isSessionValid() (includes/auth.php) is what checks user_sessions now.
if (isLoggedIn()) {
    $pdo->prepare('DELETE FROM user_sessions WHERE user_id = :id AND session_token = :token')
        ->execute(['id' => $_SESSION['user_id'], 'token' => (string) ($_SESSION['session_token'] ?? '')]);
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
