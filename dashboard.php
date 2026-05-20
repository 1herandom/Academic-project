<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Smart role-based home navigation
// Redirects to the correct dashboard based on the user's active session role.
$role = current_user()['role'];
if ($role === 'Academic Admin') redirect('/admin/index.php');
if ($role === 'Teacher') redirect('/teacher/index.php');
redirect('/student/index.php');
