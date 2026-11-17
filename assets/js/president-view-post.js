
document.addEventListener('DOMContentLoaded', function () {

    // ------------------------------------
    // 1. DELETE POST CONFIRMATION
    // ------------------------------------

    const deleteForm = document.querySelector(
        'form[action="delete-post.php"]'
    );

    if (deleteForm) {

        deleteForm.addEventListener('submit', function (event) {

            const confirmed = confirm(
                'Are you sure you want to permanently delete this post and its related information? This action cannot be undone.'
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    }


    // ------------------------------------
    // 2. COVER IMAGE ERROR HANDLING
    // ------------------------------------

    const coverImage = document.querySelector(
        '.post-cover-image'
    );

    if (coverImage) {

        coverImage.addEventListener('error', function () {

            this.parentElement.style.display = 'none';

        });

    }


    // ------------------------------------
    // 3. ADDITIONAL IMAGE ERROR HANDLING
    // ------------------------------------

    const galleryImages = document.querySelectorAll(
        '.post-gallery-item img'
    );

    galleryImages.forEach(function (image) {

        image.addEventListener('error', function () {

            this.parentElement.style.display = 'none';

        });

    });

});
