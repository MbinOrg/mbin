import { Controller } from '@hotwired/stimulus';
import debounce from '../utils/debounce';

// use some buffer-space so that the expand-button won't be included if just a couple of lines would be hidden
const MAX_COLLAPSED_HEIGHT_REM = 25;
const MAX_FULL_HEIGHT_REM = 28;

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static targets = ['content', 'button'];

    maxCollapsedHeightPx = 0;
    maxFullHeightPx = 0;

    isActive = false;
    isExpanded = true;
    button = null;
    buttonIcon = null;

    connect() {
        const remConvert = parseFloat(getComputedStyle(document.documentElement).fontSize);
        this.maxCollapsedHeightPx = MAX_COLLAPSED_HEIGHT_REM * remConvert;
        this.maxFullHeightPx = MAX_FULL_HEIGHT_REM * remConvert;

        this.setup();

        const observerDebounced = debounce(200, () => {
            this.setup();
        });
        const observer = new ResizeObserver(observerDebounced);
        observer.observe(this.contentTarget);
    }

    setup() {
        const activate = this.checkSize();
        if (activate === this.isActive) {
            return;
        }

        if (activate) {
            this.setupButton();
            this.setExpanded(false, true);
        } else {
            this.contentTarget.style.maxHeight = null;
            this.button.remove();
        }

        this.isActive = activate;
    }

    checkSize() {
        const elem = this.contentTarget;
        return elem.scrollHeight - 30 > this.maxFullHeightPx || elem.scrollWidth > elem.clientWidth;
    }

    setupButton() {
        this.buttonIcon = document.createElement('i');
        this.buttonIcon.classList.add('fa-solid', 'fa-angles-down');

        this.button = document.createElement('div');
        this.button.classList.add('more');
        this.button.appendChild(this.buttonIcon);

        this.button.addEventListener('click', () => {
            this.setExpanded(!this.isExpanded, false);
        });

        this.buttonTarget.appendChild(this.button);
    }

    setExpanded(expanded, skipEffects) {
        if (expanded) {
            this.contentTarget.style.maxHeight = null;
            this.buttonIcon.classList.remove('fa-angles-down');
            this.buttonIcon.classList.add('fa-angles-up');
        } else {
            this.contentTarget.style.maxHeight = `${MAX_COLLAPSED_HEIGHT_REM}rem`;
            this.buttonIcon.classList.remove('fa-angles-up');
            this.buttonIcon.classList.add('fa-angles-down');

            if (!skipEffects) {
                this.contentTarget.scrollIntoView();
            }
        }

        this.isExpanded = expanded;
    }
}
