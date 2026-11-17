
document.addEventListener('DOMContentLoaded', function () {

    // ==========================================
    // 1. CATEGORY AND SUBCATEGORY FILTERING
    // ==========================================

    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');

    const sportsSection = document.getElementById('sportsSection');
    const sportSelect = document.getElementById('sport_id');

    const eventSection = document.getElementById('eventSection');

    function filterSubcategories() {

        if (!categorySelect || !subcategorySelect) return;

        const selectedCategory = categorySelect.value;
        const previousSubcategory = subcategorySelect.value;

        let selectedOptionStillValid = false;

        Array.from(subcategorySelect.options).forEach(function (option) {

            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const belongsToCategory =
                option.dataset.category === selectedCategory;

            const isClubs =
                (option.dataset.name || '').trim().toLowerCase() === 'clubs';

            const isSportsEvents =
                (option.dataset.name || '').trim().toLowerCase() === 'sports events';

            const isSportsClub =
                (categorySelect.dataset.clubType || '').trim().toLowerCase() === 'sports';

            const allowed =
                belongsToCategory &&
                !isClubs &&
                (isSportsClub || !isSportsEvents);

            option.hidden = !allowed;
            option.disabled = !allowed;

            if (option.value === previousSubcategory && allowed) {
                selectedOptionStillValid = true;
            }
        });

        if (!selectedOptionStillValid) {
            subcategorySelect.value = '';
        }

        subcategorySelect.dispatchEvent(new Event('change'));
    }


    // ==========================================
    // 2. SHOW / HIDE SPORTS AND EVENT SECTIONS
    // ==========================================

    function updateConditionalSections() {

        if (!categorySelect) return;

        const selectedOption =
            categorySelect.options[categorySelect.selectedIndex];

        const categoryName = selectedOption
            ? selectedOption.text.trim().toLowerCase()
            : '';

        const isSportsCategory = categoryName === 'sports';
        const isEventCategory = categoryName === 'events';

        if (sportsSection) {
            sportsSection.style.display =
                isSportsCategory ? 'block' : 'none';
        }

        if (sportSelect) {
            sportSelect.required = isSportsCategory;

            if (!isSportsCategory) {
                sportSelect.value = '';
            }
        }

        if (eventSection) {
            eventSection.style.display =
                isEventCategory ? 'block' : 'none';
        }

        const eventInputs = eventSection
            ? eventSection.querySelectorAll(
                'input[name="event_start_at"], input[name="event_end_at"], input[name="venue"]'
            )
            : [];

        eventInputs.forEach(function (input) {
            input.required = isEventCategory;
        });
    }


    if (categorySelect) {

        categorySelect.addEventListener('change', function () {
            filterSubcategories();
            updateConditionalSections();
        });

        filterSubcategories();
        updateConditionalSections();
    }


    // ==========================================
    // 3. IMAGE UPLOAD SETTINGS
    // ==========================================

    const MAX_IMAGES = 5;
    const MAX_FILE_SIZE = 5 * 1024 * 1024;

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];


    // ==========================================
    // 4. COVER IMAGE PREVIEW
    // ==========================================

    const coverInput = document.getElementById('cover_image');

    if (coverInput) {

        const coverUploadBox = document.createElement('div');

        coverUploadBox.className = 'president-image-upload';

        coverUploadBox.innerHTML = `
            <div class="president-image-grid">
                <div class="president-image-slot" id="editCoverSlot">
                    <span class="upload-plus">+</span>
                    <span>Choose Cover Image</span>
                </div>
            </div>
            <p class="president-image-help">
                Select one cover image. JPG, PNG or WEBP, maximum 5 MB.
            </p>
        `;

        coverInput.insertAdjacentElement('afterend', coverUploadBox);

        const coverSlot = coverUploadBox.querySelector('#editCoverSlot');

        const currentCover = document.querySelector('.current-cover-image');

        if (currentCover) {
            currentCover.style.display = 'none';

            const currentCoverPreview = document.createElement('div');

            currentCoverPreview.className = 'president-image-preview';

            currentCoverPreview.innerHTML = `
                <img src="${currentCover.src}" alt="Current cover image">
                <span class="image-status">Current Cover</span>
            `;

            coverUploadBox.insertBefore(
                currentCoverPreview,
                coverUploadBox.querySelector('.president-image-grid')
            );
        }


        coverSlot.addEventListener('click', function () {
            coverInput.click();
        });


        coverInput.addEventListener('change', function () {

            const file = coverInput.files[0];

            const oldPreview =
                coverUploadBox.querySelector('.new-cover-preview');

            if (oldPreview) {
                oldPreview.remove();
            }

            if (!file) return;

            if (!allowedTypes.includes(file.type)) {
                alert('Please select a JPG, PNG or WEBP image.');
                coverInput.value = '';
                return;
            }

            if (file.size > MAX_FILE_SIZE) {
                alert('Cover image must be smaller than 5 MB.');
                coverInput.value = '';
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {

                const preview = document.createElement('div');

                preview.className =
                    'president-image-preview new-cover-preview';

                preview.innerHTML = `
                    <img src="${event.target.result}" alt="New cover image preview">
                    <button type="button"
                            class="president-image-remove"
                            aria-label="Remove selected cover image">
                        ×
                    </button>
                    <span class="image-status">New Cover</span>
                `;

                coverUploadBox.insertBefore(
                    preview,
                    coverUploadBox.querySelector('.president-image-grid')
                );

                preview.querySelector('.president-image-remove')
                    .addEventListener('click', function () {

                        coverInput.value = '';
                        preview.remove();
                    });
            };

            reader.readAsDataURL(file);
        });
    }


    // ==========================================
    // 5. EXISTING ADDITIONAL IMAGES
    // ==========================================

    const additionalInput =
        document.getElementById('additional_images');

    if (additionalInput) {

        const existingGallery =
            document.querySelector('.post-image-gallery');

        const existingImageItems = existingGallery
            ? Array.from(existingGallery.querySelectorAll('.edit-gallery-item'))
            : [];

        const uploadBox = document.createElement('div');

        uploadBox.className = 'president-image-upload';

        uploadBox.innerHTML = `
            <div class="president-image-grid" id="editAdditionalGrid"></div>
            <p class="president-image-help" id="editImageHelp"></p>
        `;

        additionalInput.insertAdjacentElement('afterend', uploadBox);

        const imageGrid = uploadBox.querySelector('#editAdditionalGrid');
        const imageHelp = uploadBox.querySelector('#editImageHelp');

        let selectedFiles = [];


        // Count existing images that are NOT marked for removal.
        function getRemainingExistingCount() {

            return existingImageItems.filter(function (item) {

                const checkbox = item.querySelector(
                    'input[name="remove_image_ids[]"]'
                );

                return !checkbox || !checkbox.checked;

            }).length;
        }


        // Calculate available slots.
        function getAvailableSlots() {

            return Math.max(
                0,
                MAX_IMAGES - getRemainingExistingCount()
            );
        }


        // Update image counter.
        function updateImageHelp() {

            const existingCount = getRemainingExistingCount();
            const availableSlots = getAvailableSlots();

            imageHelp.textContent =
                `${existingCount} existing image(s) kept. ` +
                `${selectedFiles.length} new image(s) selected. ` +
                `${availableSlots - selectedFiles.length} slot(s) available. ` +
                `Maximum ${MAX_IMAGES} additional images total.`;
        }


        // Rebuild the file input from selectedFiles.
        function syncFileInput() {

            const dataTransfer = new DataTransfer();

            selectedFiles.forEach(function (file) {
                dataTransfer.items.add(file);
            });

            additionalInput.files = dataTransfer.files;
        }


        // Render selected new image previews and empty slots.
        function renderAdditionalImages() {

            imageGrid.innerHTML = '';

            selectedFiles.forEach(function (file, index) {

                const reader = new FileReader();

                reader.onload = function (event) {

                    const preview = document.createElement('div');

                    preview.className = 'president-image-preview';

                    preview.innerHTML = `
                        <img src="${event.target.result}" alt="Selected additional image">
                        <button type="button"
                                class="president-image-remove"
                                aria-label="Remove selected image">
                            ×
                        </button>
                    `;

                    preview.querySelector('.president-image-remove')
                        .addEventListener('click', function () {

                            selectedFiles.splice(index, 1);

                            syncFileInput();
                            renderAdditionalImages();
                        });

                    imageGrid.appendChild(preview);
                };

                reader.readAsDataURL(file);
            });


            const availableSlots = getAvailableSlots();

            const emptySlots = Math.max(
                0,
                availableSlots - selectedFiles.length
            );

            for (let i = 0; i < emptySlots; i++) {

                const slot = document.createElement('div');

                slot.className = 'president-image-slot';

                slot.innerHTML = `
                    <span class="upload-plus">+</span>
                    <span>Add Image</span>
                `;

                slot.addEventListener('click', function () {
                    additionalInput.click();
                });

                imageGrid.appendChild(slot);
            }

            updateImageHelp();
        }


        // Existing image remove checkbox changes.
        existingImageItems.forEach(function (item) {

            const checkbox = item.querySelector(
                'input[name="remove_image_ids[]"]'
            );

            if (!checkbox) return;

            checkbox.addEventListener('change', function () {

                item.classList.toggle(
                    'image-marked-for-removal',
                    checkbox.checked
                );

                // If fewer slots are available, remove excess new selections.
                const availableSlots = getAvailableSlots();

                if (selectedFiles.length > availableSlots) {

                    selectedFiles = selectedFiles.slice(
                        0,
                        availableSlots
                    );

                    syncFileInput();
                }

                renderAdditionalImages();
            });
        });


        // Select new additional images.
        additionalInput.addEventListener('change', function () {

            const incomingFiles = Array.from(additionalInput.files);

            // Reset first so the input can be reused.
            additionalInput.value = '';

            const availableSlots = getAvailableSlots();

            const remainingSlots =
                availableSlots - selectedFiles.length;

            if (remainingSlots <= 0) {
                alert('You already have the maximum of 5 additional images. Remove an existing image first.');
                return;
            }

            let validFiles = [];

            for (const file of incomingFiles) {

                if (!allowedTypes.includes(file.type)) {
                    alert(`${file.name} is not a supported image. Use JPG, PNG or WEBP.`);
                    continue;
                }

                if (file.size > MAX_FILE_SIZE) {
                    alert(`${file.name} is larger than 5 MB.`);
                    continue;
                }

                validFiles.push(file);
            }


            if (validFiles.length > remainingSlots) {

                alert(
                    `You can add only ${remainingSlots} more image(s). ` +
                    `The maximum is ${MAX_IMAGES} additional images total.`
                );

                validFiles = validFiles.slice(0, remainingSlots);
            }


            selectedFiles = selectedFiles.concat(validFiles);

            syncFileInput();
            renderAdditionalImages();
        });


        // Initial rendering.
        renderAdditionalImages();
    }


    // ==========================================
    // 6. FORM SUBMISSION CHECK
    // ==========================================

    const postForm = document.querySelector('.post-form');

    if (postForm) {

        postForm.addEventListener('submit', function (event) {

            const existingCount = getExistingImageCount();
            const newCount = additionalInput
                ? additionalInput.files.length
                : 0;

            if (existingCount + newCount > MAX_IMAGES) {

                event.preventDefault();

                alert(
                    'You can keep or upload a maximum of 5 additional images total. ' +
                    'Please remove some images before saving.'
                );
            }
        });
    }


    function getExistingImageCount() {

        const gallery = document.querySelector('.post-image-gallery');

        if (!gallery) return 0;

        return Array.from(
            gallery.querySelectorAll('.edit-gallery-item')
        ).filter(function (item) {

            const checkbox = item.querySelector(
                'input[name="remove_image_ids[]"]'
            );

            return !checkbox || !checkbox.checked;

        }).length;
    }

});
