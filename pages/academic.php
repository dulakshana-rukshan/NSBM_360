<?php
// --- LATEST NEWS CONFIGURATION ---
$pageTitle = 'Academic News';
$activePage = 'academic';
$pageHeading = 'Academic News';
$pageSubheading = 'Ideas worth exploring. Opportunities worth taking. Learing beyond the lecture hall.';




// Lock page to News subcategories (Parent ID = 1000)
$baseCategoryParentId = 4000; 

// Include template engine directly from the same folder
include 'posts_template.php';
?>