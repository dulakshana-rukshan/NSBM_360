<?php
// --- SPORTS PAGE CONFIGURATION ---
$pageTitle = 'Sports News';
$activePage = 'sports';
$pageHeading = 'Sports news';
$pageSubheading = 'Every team. Every challenge. Every moment that brings us together.';

// Lock this page strictly to Sports and its subcategories (Parent ID = 3000)
$baseCategoryParentId = 3000; 

// Include your modular template engine
include 'posts_template.php';
?>