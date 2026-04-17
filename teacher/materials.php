<?php
// Include header layout (also initializes session, UI structure)
require_once __DIR__ . '/../includes/header.php';

// Ensure only teachers can access this page
require_role('Teacher');

/*
|--------------------------------------------------------------------------
| Feature 4 | Suprim: Material access redirect for lecture notes / lab sheets / reading material
|--------------------------------------------------------------------------
*/

// Redirect teacher to the main assignments & materials page
// This acts as a controlled entry point for accessing uploaded materials
redirect('/teacher/assignments.php');