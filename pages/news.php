<?php
// --- LATEST NEWS CONFIGURATION ---
$pageTitle = 'Latest News';
$activePage = 'news';
$pageHeading = 'Latest news';
$pageSubheading = 'The stories, achievements and ideas shaping life at NSBM.';




// Lock page to News subcategories (Parent ID = 1000)
$baseCategoryParentId = 1000; 

// Include template engine directly from the same folder
include 'posts_template.php';
?>