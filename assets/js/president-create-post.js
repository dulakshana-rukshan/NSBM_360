
document.addEventListener('DOMContentLoaded', function () {

    // ------------------------------------
    // 1. GET FORM ELEMENTS
    // ------------------------------------

    const mainCategorySelect =
        document.getElementById('main_category_id');

    const subcategorySelect =
        document.getElementById('subcategory_id');

    const sportSection =
        document.getElementById('sport-section');

    const sportSelect =
        document.getElementById('sport_id');

    const eventSection =
        document.getElementById('event-section');

    const eventStart =
        document.getElementById('event_start_at');

    const eventEnd =
        document.getElementById('event_end_at');

    const eventVenue =
        document.getElementById('event_venue');

    const registrationDeadline =
        document.getElementById('registration_deadline');

    if (!mainCategorySelect || !subcategorySelect) {
        return;
    }

    const clubType =
        (mainCategorySelect.dataset.clubType || '').toLowerCase();

    const isSportsClub = clubType === 'sports';

    const allSubcategoryOptions = Array.from(
        subcategorySelect.querySelectorAll(
            'option[data-parent-id]'
        )
    );


    // ------------------------------------
    // 2. FILTER SUBCATEGORIES
    // ------------------------------------

    function filterSubcategories() {

        const selectedMainCategory =
            mainCategorySelect.value;

        const previousSubcategory =
            subcategorySelect.value;

        subcategorySelect.innerHTML = '';

        const placeholder = document.createElement('option');

        placeholder.value = '';
        placeholder.textContent = 'Choose a subcategory';

        subcategorySelect.appendChild(placeholder);

        let previousOptionAvailable = false;

        allSubcategoryOptions.forEach(function (option) {

            const subcategoryName =
                (option.dataset.subcategoryName || '').trim().toLowerCase();

            const belongsToSelectedCategory =
                option.dataset.parentId === selectedMainCategory;

            if (subcategoryName === 'clubs') {
                return;
            }

            if (!isSportsClub && subcategoryName === 'sports events') {
                return;
            }

            if (belongsToSelectedCategory) {

                const newOption = option.cloneNode(true);

                if (newOption.value === previousSubcategory) {
                    newOption.selected = true;
                    previousOptionAvailable = true;
                }

                subcategorySelect.appendChild(newOption);
            }

        });

        if (!previousOptionAvailable) {
            subcategorySelect.value = '';
        }

        subcategorySelect.disabled =
            selectedMainCategory === '' ||
            subcategorySelect.options.length <= 1;

        updateConditionalFields();
    }


    // ------------------------------------
    // 3. SHOW OR HIDE SPORTS AND EVENT FIELDS
    // ------------------------------------

    function updateConditionalFields() {

        const mainOption =
            mainCategorySelect.options[
                mainCategorySelect.selectedIndex
            ];

        const subOption =
            subcategorySelect.options[
                subcategorySelect.selectedIndex
            ];

        const mainCategoryName = mainOption
            ? (mainOption.dataset.categoryName || '').toLowerCase()
            : '';

        const subcategoryName = subOption
            ? (subOption.dataset.subcategoryName || '').toLowerCase()
            : '';

        const isSports =
            mainCategoryName === 'sports';

        const isEvents =
            mainCategoryName === 'events';

        const isSportsEvent =
            isSportsClub &&
            isEvents &&
            subcategoryName === 'sports events';


        // SPORTS

        const showSportSection =
            isSports || isSportsEvent;

        if (sportSection && sportSelect) {

            sportSection.hidden = !showSportSection;
            sportSelect.required = showSportSection;

            if (!showSportSection) {
                sportSelect.value = '';
            }
        }


        // EVENTS

        if (eventSection) {

            eventSection.hidden = !isEvents;

            if (eventStart) {
                eventStart.required = isEvents;
            }

            if (eventEnd) {
                eventEnd.required = isEvents;
            }

            if (eventVenue) {
                eventVenue.required = isEvents;
            }

            if (!isEvents) {

                if (registrationDeadline) {
                    registrationDeadline.required = false;
                }
            }
        }
    }


    // ------------------------------------
    // 4. IMAGE UPLOAD ELEMENTS
    // ------------------------------------

    const coverInput =
        document.getElementById('cover_image');

    const additionalInput =
        document.getElementById('additional_images');


    // ------------------------------------
    // 5. COVER IMAGE - ONLY ONE
    // ------------------------------------

    if (coverInput) {

        const coverContainer = document.createElement('div');

        coverContainer.className = 'president-image-upload';

        const coverSlot = document.createElement('div');

        coverSlot.className = 'president-image-slot';

        coverSlot.innerHTML = `
            <span class="upload-plus">+</span>
            <strong>Add Cover Image</strong>
            <small>Select one image</small>
        `;

        const coverPreview = document.createElement('div');

        coverPreview.className = 'president-image-preview';
        coverPreview.hidden = true;

        const coverHelp = document.createElement('small');

        coverHelp.className = 'president-image-help';

        coverHelp.textContent =
            'Optional. JPG, PNG or WEBP. Maximum 5 MB.';

        coverContainer.appendChild(coverSlot);
        coverContainer.appendChild(coverPreview);
        coverContainer.appendChild(coverHelp);

        coverInput.insertAdjacentElement(
            'afterend',
            coverContainer
        );

        let coverObjectURL = null;

        coverSlot.addEventListener('click', function () {
            coverInput.click();
        });

        function renderCoverPreview() {

            coverPreview.innerHTML = '';

            if (coverObjectURL) {
                URL.revokeObjectURL(coverObjectURL);
                coverObjectURL = null;
            }

            const file = coverInput.files[0];

            if (!file) {

                coverPreview.hidden = true;
                coverSlot.hidden = false;

                return;
            }

            coverSlot.hidden = true;
            coverPreview.hidden = false;

            coverObjectURL = URL.createObjectURL(file);

            const image = document.createElement('img');

            image.src = coverObjectURL;
            image.alt = 'Cover image preview';

            const removeButton = document.createElement('button');

            removeButton.type = 'button';
            removeButton.className = 'president-image-remove';
            removeButton.innerHTML = '&times;';

            removeButton.setAttribute(
                'aria-label',
                'Remove cover image'
            );

            removeButton.addEventListener('click', function () {

                coverInput.value = '';

                renderCoverPreview();
            });

            coverPreview.appendChild(image);
            coverPreview.appendChild(removeButton);
        }

        coverInput.addEventListener(
            'change',
            renderCoverPreview
        );
    }


    // ------------------------------------
    // 6. ADDITIONAL IMAGES - MAXIMUM FIVE
    // ------------------------------------

    if (additionalInput) {

        const MAX_IMAGES = 5;

        const MAX_FILE_SIZE = 5 * 1024 * 1024;

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        const additionalContainer = document.createElement('div');

        additionalContainer.className = 'president-image-upload';

        const imageGrid = document.createElement('div');

        imageGrid.className = 'president-image-grid';

        const additionalHelp = document.createElement('small');

        additionalHelp.className = 'president-image-help';

        additionalContainer.appendChild(imageGrid);
        additionalContainer.appendChild(additionalHelp);

        additionalInput.insertAdjacentElement(
            'afterend',
            additionalContainer
        );

        let selectedImages = [];

        let additionalObjectURLs = [];


        // UPDATE FILE INPUT

        function updateAdditionalInput() {

            const dataTransfer = new DataTransfer();

            selectedImages.forEach(function (file) {
                dataTransfer.items.add(file);
            });

            additionalInput.files = dataTransfer.files;
        }


        // RELEASE PREVIOUS PREVIEW URLS

        function clearAdditionalObjectURLs() {

            additionalObjectURLs.forEach(function (url) {
                URL.revokeObjectURL(url);
            });

            additionalObjectURLs = [];
        }


        // RENDER IMAGE PREVIEWS

        function renderAdditionalImages() {

            clearAdditionalObjectURLs();

            imageGrid.innerHTML = '';

            selectedImages.forEach(function (file, index) {

                const preview = document.createElement('div');

                preview.className = 'president-image-preview';

                const image = document.createElement('img');

                const imageURL = URL.createObjectURL(file);

                additionalObjectURLs.push(imageURL);

                image.src = imageURL;

                image.alt = 'Additional image ' + (index + 1);

                const removeButton = document.createElement('button');

                removeButton.type = 'button';
                removeButton.className = 'president-image-remove';
                removeButton.innerHTML = '&times;';

                removeButton.setAttribute(
                    'aria-label',
                    'Remove additional image ' + (index + 1)
                );

                removeButton.addEventListener('click', function () {

                    selectedImages.splice(index, 1);

                    updateAdditionalInput();

                    renderAdditionalImages();
                });

                preview.appendChild(image);
                preview.appendChild(removeButton);

                imageGrid.appendChild(preview);
            });


            // SHOW ADD IMAGE SLOT

            if (selectedImages.length < MAX_IMAGES) {

                const addSlot = document.createElement('button');

                addSlot.type = 'button';
                addSlot.className = 'president-image-slot';

                addSlot.innerHTML = `
                    <span class="upload-plus">+</span>
                    <strong>Add Image</strong>
                    <small>${selectedImages.length} / ${MAX_IMAGES} selected</small>
                `;

                addSlot.addEventListener('click', function () {
                    additionalInput.click();
                });

                imageGrid.appendChild(addSlot);
            }


            // UPDATE HELP TEXT

            if (selectedImages.length === MAX_IMAGES) {

                additionalHelp.textContent =
                    'You have selected the maximum of 5 additional images.';

            } else {

                additionalHelp.textContent =
                    'Optional. ' + selectedImages.length +
                    ' of 5 images selected. JPG, PNG or WEBP.';
            }
        }


        // HANDLE NEW IMAGE SELECTION

        additionalInput.addEventListener('change', function () {

            const newlySelectedFiles =
                Array.from(additionalInput.files);

            let invalidTypeFound = false;
            let oversizedFileFound = false;
            let limitReached = false;

            newlySelectedFiles.forEach(function (file) {

                if (!allowedTypes.includes(file.type)) {
                    invalidTypeFound = true;
                    return;
                }

                if (file.size > MAX_FILE_SIZE) {
                    oversizedFileFound = true;
                    return;
                }

                if (selectedImages.length >= MAX_IMAGES) {
                    limitReached = true;
                    return;
                }

                selectedImages.push(file);
            });


            if (invalidTypeFound) {
                alert('Only JPG, PNG or WEBP images are allowed.');
            }

            if (oversizedFileFound) {
                alert('Each image must be smaller than 5 MB.');
            }

            if (limitReached) {
                alert('You can upload a maximum of 5 additional images.');
            }

            updateAdditionalInput();

            renderAdditionalImages();
        });


        // INITIAL RENDER

        renderAdditionalImages();
    }


    // ------------------------------------
    // 7. EVENT LISTENERS
    // ------------------------------------

    mainCategorySelect.addEventListener(
        'change',
        filterSubcategories
    );

    subcategorySelect.addEventListener(
        'change',
        updateConditionalFields
    );


    // ------------------------------------
    // 8. INITIAL LOAD
    // ------------------------------------

    filterSubcategories();

});
