
<footer class="site-footer">

    <div class="footer-container">

        <div class="footer-brand">

            <a href="/NSBM_360/index.php">
                <img src="/NSBM_360/assets/image/NSBM_360_Logo_without_background_1.png"
                    alt="NSBM 360 Logo">
            </a>

            <p>
                Your window into life at NSBM Green University.
                The stories, people and experiences that make
                our campus a world of its own.
            </p>

        </div>

        <div class="footer-links">

            <h3>Explore campus</h3>

            <ul>
                <li><a href="/NSBM_360/index.php">Home</a></li>
                <li><a href="/NSBM_360/pages/events.php">Events</a></li>
                <li><a href="/NSBM_360/pages/clubs.php">Clubs</a></li>
                <li><a href="/NSBM_360/pages/news.php">News</a></li>
                <li><a href="/NSBM_360/pages/sports.php">Sports</a></li>
            </ul>

        </div>

        <div class="footer-contact">

            <h3>Get in touch</h3>

            <p>NSBM Green University</p>
            <p>Mahenwaththa, Pitipana, Homagama, Sri Lanka</p>

            <div class="contact-details">
                <a href="mailto:info@nsbm.ac.lk">info@nsbm.ac.lk</a>
                <a href="tel:+94115445000">+94 11 544 5000</a>
            </div>

        </div>

    </div>

    <div class="footer-bottom">

        <p>
            &copy; <?php echo date("Y"); ?>
            NSBM 360. All rights reserved.
        </p>

        <div class="footer-legal">
            <a href="#">Privacy Policy</a>
            <span>•</span>
            <a href="#">Terms of Use</a>
        </div>

        <p class="footer-community">
            Made for the NSBM community.
        </p>

    </div>

</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>


<?php if (!empty($pageJS)): ?>
    <script src="<?php echo htmlspecialchars($pageJS); ?>"></script>
<?php endif; ?>

</body>
</html>
