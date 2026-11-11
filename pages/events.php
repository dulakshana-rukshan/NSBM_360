<?php
// --- EVENTS PAGE CONFIGURATION ---
$pageTitle = 'Campus Events';
$activePage = 'events';
$pageHeading = 'Be part of what\'s next.';
$pageSubheading = 'Your campus calendar for ideas, connections and unforgettable moments.';

// Lock this page strictly to Events and its subcategories (Parent ID = 2000)
$baseCategoryParentId = 2000; 

// Include your modular template engine
include 'posts_template.php';
?>