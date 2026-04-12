<?php
require_once __DIR__ . '/../includes/header.php';
require_role('Teacher');
redirect('/teacher/assignments.php');
