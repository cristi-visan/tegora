/* Category filter for galleries (Proiecte, Inspirație).
   Markup contract, all inside a [data-gallery-filter] container:
   - [data-filter-chips] (hidden until this script runs) holding buttons with data-filter="<category>" ("toate" = all)
   - items with data-category="<category>"
   - optional [data-filter-count] with data-one / data-many text, shows the number of visible items
   - optional [data-filter-empty], shown when no item matches
   Without JS everything stays visible and the chips stay hidden. */
(function () {
    'use strict';

    function init(root) {
        var chipBox = root.querySelector('[data-filter-chips]');
        if (!chipBox) { return; }
        var chips = Array.prototype.slice.call(chipBox.querySelectorAll('[data-filter]'));
        var items = Array.prototype.slice.call(root.querySelectorAll('[data-category]'));
        var count = root.querySelector('[data-filter-count]');
        var empty = root.querySelector('[data-filter-empty]');

        function apply(filter) {
            var shown = 0;
            items.forEach(function (item) {
                var match = filter === 'toate' || item.getAttribute('data-category') === filter;
                item.hidden = !match;
                if (match) { shown++; }
            });
            chips.forEach(function (chip) {
                chip.setAttribute('aria-pressed', chip.getAttribute('data-filter') === filter ? 'true' : 'false');
            });
            root.classList.toggle('is-filtered', filter !== 'toate');
            if (count) {
                count.textContent = shown + ' ' + count.getAttribute(shown === 1 ? 'data-one' : 'data-many');
            }
            if (empty) { empty.hidden = shown !== 0; }
        }

        chipBox.hidden = false;
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () { apply(chip.getAttribute('data-filter')); });
        });
        apply('toate');
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-gallery-filter]'), init);
})();
