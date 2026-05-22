/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'trigger', 'triggerFlag', 'triggerLabel', 'dropdown', 'search', 'list', 'empty'];

    static values = {
        placeholder: String,
    };

    connect() {
        this.buildList();
        this.syncFromSelect();
        this.boundDocClick = this.onDocumentClick.bind(this);
        document.addEventListener('click', this.boundDocClick);
    }

    disconnect() {
        document.removeEventListener('click', this.boundDocClick);
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.triggerTarget.disabled) {
            return;
        }
        this.setOpen(this.dropdownTarget.hidden);
    }

    setOpen(open) {
        this.dropdownTarget.hidden = !open;
        this.triggerTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            if (this.hasSearchTarget) {
                this.searchTarget.value = '';
            }
            this.applyFilter('');
            window.requestAnimationFrame(() => {
                if (this.hasSearchTarget) {
                    this.searchTarget.focus();
                }
            });

            return;
        }

        if (this.hasSearchTarget) {
            this.searchTarget.value = '';
        }
        this.applyFilter('');
    }

    onDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this.setOpen(false);
        }
    }

    onSearchKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.setOpen(false);
            this.triggerTarget.focus();
        }
    }

    filter() {
        const query = this.hasSearchTarget ? this.searchTarget.value : '';
        this.applyFilter(query);
    }

    buildList() {
        this.listTarget.replaceChildren();
        Array.from(this.selectTarget.options).forEach((option) => {
            if (option.disabled) {
                return;
            }

            const item = document.createElement('li');
            item.className = 'country-flag-select__option';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.dataset.label = option.textContent.trim();
            item.dataset.labelNorm = this.normalizeLabel(option.textContent);
            if (option.value === '') {
                item.classList.add('country-flag-select__option--placeholder');
            }
            item.appendChild(this.createFlagElement(option));
            const label = document.createElement('span');
            label.className = 'country-flag-select__option-label';
            label.textContent = option.textContent;
            item.appendChild(label);
            item.addEventListener('click', (clickEvent) => {
                clickEvent.preventDefault();
                this.pick(option.value);
            });
            this.listTarget.appendChild(item);
        });
    }

    applyFilter(query) {
        const normQuery = this.normalizeLabel(query);
        let visibleCount = 0;

        this.listTarget.querySelectorAll('.country-flag-select__option').forEach((item) => {
            const isPlaceholder = item.classList.contains('country-flag-select__option--placeholder');
            const matches =
                normQuery === ''
                || (!isPlaceholder && item.dataset.labelNorm.includes(normQuery));

            item.hidden = !matches;
            if (matches) {
                visibleCount += 1;
            }
        });

        if (this.hasEmptyTarget) {
            const showEmpty = normQuery !== '' && visibleCount === 0;
            this.emptyTarget.hidden = !showEmpty;
            this.listTarget.hidden = showEmpty;
        }
    }

    normalizeLabel(text) {
        return (text ?? '')
            .normalize('NFD')
            .replace(/\p{M}/gu, '')
            .toLowerCase()
            .trim();
    }

    createFlagElement(option) {
        const wrap = document.createElement('span');
        wrap.className = 'country-flag-select__flag';
        const src = option.dataset.flagSrc;
        if (src) {
            const img = document.createElement('img');
            img.src = src;
            img.alt = '';
            img.width = 28;
            img.height = 19;
            img.loading = 'lazy';
            img.decoding = 'async';
            wrap.appendChild(img);
        } else if (option.dataset.countryInitial) {
            const initial = document.createElement('span');
            initial.className = 'country-flag-select__flag-initial';
            initial.textContent = option.dataset.countryInitial;
            wrap.appendChild(initial);
        }

        return wrap;
    }

    pick(value) {
        this.selectTarget.value = value;
        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.syncFromSelect();
        this.setOpen(false);
    }

    syncFromSelect() {
        const option = this.selectTarget.selectedOptions[0];
        if (!option || option.value === '') {
            this.triggerLabelTarget.textContent = this.placeholderValue;
            this.triggerFlagTarget.replaceChildren();

            return;
        }

        this.triggerLabelTarget.textContent = option.textContent;
        this.triggerFlagTarget.replaceChildren();
        this.triggerFlagTarget.appendChild(this.createFlagElement(option));
    }
}
