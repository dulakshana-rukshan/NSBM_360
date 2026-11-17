document.addEventListener('DOMContentLoaded', () => {
    const categorySelect = document.getElementById('category_select');
    const subcategorySelect = document.getElementById('subcategory_select');
    const eventFields = document.getElementById('event_fields');
    const sportSelect = document.getElementById('sport_select');

    if (!categorySelect || !subcategorySelect) return;

    // Cache all original subcategory options on initial load
    const allSubOptions = Array.from(subcategorySelect.querySelectorAll('option')).filter(opt => opt.value !== '');

    function filterSubcategories() {
        const selectedCatId = categorySelect.value;
        const currentSubVal = subcategorySelect.value;

        // Clear current options except the default placeholder
        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

        allSubOptions.forEach(opt => {
            if (opt.getAttribute('data-cat') === selectedCatId) {
                const cloned = opt.cloneNode(true);
                if (cloned.value === currentSubVal) {
                    cloned.selected = true;
                }
                subcategorySelect.appendChild(cloned);
            }
        });

        toggleDynamicFields();
    }

    function toggleDynamicFields() {
        const selectedCatOpt = categorySelect.options[categorySelect.selectedIndex];
        const catName = selectedCatOpt ? selectedCatOpt.text.trim() : '';

        const selectedSubOpt = subcategorySelect.options[subcategorySelect.selectedIndex];
        const subName = selectedSubOpt ? selectedSubOpt.text.trim() : '';

        // Toggle Event fields when Main Category is 'Events'
        if (eventFields) {
            eventFields.style.display = (catName === 'Events') ? 'block' : 'none';
        }

        // Toggle Sport requirement when Category is 'Sports' or Subcategory is 'Sports Events'
        if (sportSelect) {
            sportSelect.required = (catName === 'Sports' || subName === 'Sports Events');
        }
    }

    categorySelect.addEventListener('change', filterSubcategories);
    subcategorySelect.addEventListener('change', toggleDynamicFields);

    // Initialize on page load
    filterSubcategories();
});