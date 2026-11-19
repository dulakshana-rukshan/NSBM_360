document.addEventListener("DOMContentLoaded", function () {
  // DESCRIPTION EDITING
  const descriptionDisplay = document.getElementById("clubDescriptionDisplay");
  const descriptionEditor = document.getElementById("clubDescriptionEditor");

  if (descriptionDisplay && descriptionEditor) {
    descriptionDisplay.addEventListener("click", function () {
      descriptionDisplay.hidden = true;
      descriptionEditor.hidden = false;

      const textarea = document.getElementById("clubDescriptionInput");
      if (textarea) textarea.focus();
    });
  }

  // LOGO IMAGE PREVIEW
  // LOGO IMAGE EDITOR

  const logoInput = document.getElementById("clubLogoInput");
  const logoEditBtn = document.getElementById("clubLogoEditBtn");
  const logoPreview = document.getElementById("clubLogoPreview");
  const logoPlaceholder = document.getElementById("clubLogoPlaceholder");

  const logoModal = document.getElementById("logoEditorModal");
  const cropArea = document.getElementById("logoCropArea");
  const cropImage = document.getElementById("logoCropImage");
  const zoomSlider = document.getElementById("logoZoom");
  const applyBtn = document.getElementById("logoApplyBtn");
  const cancelBtn = document.getElementById("logoCancelBtn");
  const changeLogoBtn = document.getElementById("changeLogoBtn");

  if (logoInput && logoPreview && logoModal) {
    changeLogoBtn.addEventListener("click", function () {
      logoInput.click();
    });
    let image = new Image();
    let imageURL = null;
    let baseScale = 1;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let previousX = 0;
    let previousY = 0;

    function updateCropPreview() {
      const scale = baseScale * zoom;
      const width = image.naturalWidth * scale;
      const height = image.naturalHeight * scale;

      cropImage.style.width = width + "px";
      cropImage.style.height = height + "px";
      cropImage.style.left = 120 - width / 2 + offsetX + "px";
      cropImage.style.top = 120 - height / 2 + offsetY + "px";
    }

    function openEditor(src) {
      image = new Image();

      image.onload = function () {
        const size = 240;

        baseScale = Math.max(
          size / image.naturalWidth,
          size / image.naturalHeight,
        );

        zoom = 1;
        offsetX = 0;
        offsetY = 0;

        zoomSlider.value = 1;
        cropImage.src = src;

        logoModal.hidden = false;
        updateCropPreview();
      };

      image.src = src;
    }

    logoInput.addEventListener("change", function () {
      const file = this.files[0];
      if (!file) return;

      if (imageURL) URL.revokeObjectURL(imageURL);

      imageURL = URL.createObjectURL(file);
      openEditor(imageURL);
    });

    // Click existing logo to adjust it.
    logoEditBtn.addEventListener("click", function (event) {
      event.preventDefault();

      const currentLogo = logoPreview.getAttribute("src");

      if (currentLogo && !logoPreview.hidden) {
        openEditor(logoPreview.src);
      } else {
        logoInput.click();
      }
    });
    zoomSlider.addEventListener("input", function () {
      zoom = Number(this.value);
      updateCropPreview();
    });

    cropArea.addEventListener("pointerdown", function (event) {
      dragging = true;
      startX = event.clientX;
      startY = event.clientY;
      previousX = offsetX;
      previousY = offsetY;

      cropArea.setPointerCapture(event.pointerId);
    });

    cropArea.addEventListener("pointermove", function (event) {
      if (!dragging) return;

      offsetX = previousX + event.clientX - startX;
      offsetY = previousY + event.clientY - startY;

      updateCropPreview();
    });

    cropArea.addEventListener("pointerup", function () {
      dragging = false;
    });

    cropArea.addEventListener("pointercancel", function () {
      dragging = false;
    });

    cancelBtn.addEventListener("click", function () {
      logoModal.hidden = true;
    });

    applyBtn.addEventListener("click", function () {
      const canvas = document.createElement("canvas");
      const size = 500;

      canvas.width = size;
      canvas.height = size;

      const ctx = canvas.getContext("2d");
      const ratio = size / 240;

      ctx.clearRect(0, 0, size, size);

      ctx.save();
      ctx.beginPath();
      ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
      ctx.clip();

      const scale = baseScale * zoom * ratio;
      const width = image.naturalWidth * scale;
      const height = image.naturalHeight * scale;

      ctx.drawImage(
        image,
        size / 2 - width / 2 + offsetX * ratio,
        size / 2 - height / 2 + offsetY * ratio,
        width,
        height,
      );

      ctx.restore();

      canvas.toBlob(function (blob) {
        if (!blob) return;

        const file = new File([blob], "club-logo.png", { type: "image/png" });

        const transfer = new DataTransfer();
        transfer.items.add(file);

        logoInput.files = transfer.files;

        logoPreview.src = URL.createObjectURL(blob);
        logoPreview.hidden = false;

        if (logoPlaceholder) {
          logoPlaceholder.hidden = true;
        }

        logoModal.hidden = true;
      }, "image/png");
    });
  }

  // BANNER IMAGE PREVIEW
  const bannerInput = document.getElementById("clubBannerInput");
  const bannerImage = document.getElementById("clubBannerImage");

  if (bannerInput && bannerImage) {
    bannerInput.addEventListener("change", function () {
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

document.addEventListener("DOMContentLoaded", function () {
  const editButton = document.getElementById("editSocialLinksBtn");
  const editPanel = document.getElementById("clubSocialEditPanel");
  const addButton = document.getElementById("addSocialLink");
  const socialList = document.getElementById("clubSocialList");

  // ------------------------------------
  // SHOW / HIDE SOCIAL LINKS EDIT PANEL
  // ------------------------------------

  if (editButton && editPanel) {
    editButton.addEventListener("click", function () {
      editPanel.hidden = !editPanel.hidden;

      editButton.innerHTML = editPanel.hidden
        ? '<i class="bi bi-pencil-square"></i>'
        : '<i class="bi bi-x-lg"></i>';

      editButton.title = editPanel.hidden
        ? "Edit official links"
        : "Close editing";
    });
  }

  // ------------------------------------
  // ADD NEW SOCIAL LINK
  // ------------------------------------

  if (addButton && socialList) {
    addButton.addEventListener("click", function () {
      const row = document.createElement("div");

      row.className = "club-social-row";

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

      const platformInput = row.querySelector('input[name="platform[]"]');

      const icon = row.querySelector(".club-social-icon i");

      const removeButton = row.querySelector(".club-social-remove-new");

      // Update icon when platform name changes.
      platformInput.addEventListener("input", function () {
        const platform = this.value.trim().toLowerCase();

        const icons = {
          facebook: "bi-facebook",
          instagram: "bi-instagram",
          whatsapp: "bi-whatsapp",
          youtube: "bi-youtube",
          tiktok: "bi-tiktok",
          linkedin: "bi-linkedin",
          twitter: "bi-twitter-x",
          x: "bi-twitter-x",
          website: "bi-globe",
          telegram: "bi-telegram",
          discord: "bi-discord",
        };

        icon.className = "bi " + (icons[platform] || "bi-link-45deg");
      });

      // Remove newly added row.
      removeButton.addEventListener("click", function () {
        row.remove();
      });

      socialList.appendChild(row);

      platformInput.focus();
    });
  }
});
