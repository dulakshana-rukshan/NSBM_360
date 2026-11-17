
document.addEventListener('DOMContentLoaded', function () {

    // DESCRIPTION EDITING
    const descriptionDisplay = document.getElementById('clubDescriptionDisplay');
    const descriptionEditor = document.getElementById('clubDescriptionEditor');

    if (descriptionDisplay && descriptionEditor) {
        descriptionDisplay.addEventListener('click', function () {
            descriptionDisplay.hidden = true;
            descriptionEditor.hidden = false;

            const textarea = document.getElementById('clubDescriptionInput');
            if (textarea) textarea.focus();
        });
    }

    // LOGO IMAGE PREVIEW
    const logoInput = document.getElementById('clubLogoInput');
    const logoPreview = document.getElementById('clubLogoPreview');
    const logoPlaceholder = document.getElementById('clubLogoPlaceholder');

    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function () {
            const file = this.files[0];

            if (!file) return;

            const imageURL = URL.createObjectURL(file);

            logoPreview.src = imageURL;
            logoPreview.hidden = false;

            if (logoPlaceholder) {
                logoPlaceholder.hidden = true;
            }
        });
    }

    // BANNER IMAGE PREVIEW
    const bannerInput = document.getElementById('clubBannerInput');
    const bannerImage = document.getElementById('clubBannerImage');

    if (bannerInput && bannerImage) {
        bannerInput.addEventListener('change', function () {
            const file = this.files[0];

            if (!file) return;

            bannerImage.src = URL.createObjectURL(file);
            bannerImage.hidden = false;
        });
    }

});

// ========================================
// CLUB SOCIAL LINKS - EDIT PANEL
// ========================================

document.addEventListener('DOMContentLoaded', function () {

    const editButton = document.getElementById('editSocialLinksBtn');
    const editPanel = document.getElementById('clubSocialEditPanel');
    const addButton = document.getElementById('addSocialLink');
    const socialList = document.getElementById('clubSocialList');


    // ------------------------------------
    // SHOW / HIDE SOCIAL LINKS EDIT PANEL
    // ------------------------------------

    if (editButton && editPanel) {

        editButton.addEventListener('click', function () {

            editPanel.hidden = !editPanel.hidden;

            editButton.innerHTML = editPanel.hidden
                ? '<i class="bi bi-pencil-square"></i>'
                : '<i class="bi bi-x-lg"></i>';

            editButton.title = editPanel.hidden
                ? 'Edit official links'
                : 'Close editing';

        });

    }


    // ------------------------------------
    // ADD NEW SOCIAL LINK
    // ------------------------------------

    if (addButton && socialList) {

        addButton.addEventListener('click', function () {

            const row = document.createElement('div');

            row.className = 'club-social-row';

            row.innerHTML = `
                <input type="hidden" name="link_id[]" value="">

                <div class="club-social-icon">
                    <i class="bi bi-link-45deg"></i>
                </div>

                <div class="club-social-inputs">

                    <input
                        type="text"
                        name="platform[]"
                        maxlength="50"
                        placeholder="Platform name (e.g. Facebook)"
                        required
                    >

                    <input
                        type="url"
                        name="url[]"
                        maxlength="500"
                        placeholder="https://..."
                        required
                    >

                </div>

                <span
                    class="club-social-remove-new"
                    title="Remove this row"
                    role="button"
                    tabindex="0"
                >
                    <i class="bi bi-x-lg"></i>
                </span>
            `;

            const platformInput = row.querySelector(
                'input[name="platform[]"]'
            );

            const icon = row.querySelector('.club-social-icon i');

            const removeButton = row.querySelector(
                '.club-social-remove-new'
            );


            // Update icon when platform name changes.
            platformInput.addEventListener('input', function () {

                const platform = this.value.trim().toLowerCase();

                const icons = {
                    facebook: 'bi-facebook',
                    instagram: 'bi-instagram',
                    whatsapp: 'bi-whatsapp',
                    youtube: 'bi-youtube',
                    tiktok: 'bi-tiktok',
                    linkedin: 'bi-linkedin',
                    twitter: 'bi-twitter-x',
                    x: 'bi-twitter-x',
                    website: 'bi-globe',
                    telegram: 'bi-telegram',
                    discord: 'bi-discord'
                };

                icon.className = 'bi ' + (
                    icons[platform] || 'bi-link-45deg'
                );

            });


            // Remove newly added row.
            removeButton.addEventListener('click', function () {
                row.remove();
            });

            socialList.appendChild(row);

            platformInput.focus();

        });

    }

});
