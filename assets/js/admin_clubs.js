/**
 * NSBM 360 - Clubs & Leadership Administration JS
 * Handles dynamically adding and removing social/external link rows in the club form.
 */
document.addEventListener('DOMContentLoaded', () => {
    const addLinkBtn = document.getElementById('add-link-btn');
    const linksContainer = document.getElementById('club-links-container');

    // Add a new platform + URL input row when "Add another link" is clicked
    if (addLinkBtn && linksContainer) {
        addLinkBtn.addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'club-link-row';
            row.innerHTML = `
                <input type="text" name="link_platforms[]" placeholder="Platform (e.g., Website, Facebook, LinkedIn)">
                <input type="url" name="link_urls[]" placeholder="URL (https://...)">
                <button type="button" class="btn btn-red btn-remove-link" aria-label="Remove link">×</button>
            `;
            linksContainer.appendChild(row);
        });

        // Delegate click events for removing any existing or newly added link row
        linksContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('btn-remove-link')) {
                event.target.closest('.club-link-row')?.remove();
            }
        });
    }
});