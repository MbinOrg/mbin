import { Controller } from '@hotwired/stimulus';

const MAX_COLLAPSED_HEIGHT = '16lh';
const MAX_FULL_HEIGHT = '20lh';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static targets = ['content', 'button'];

    isExpanded = true;
    button = null;
    buttonIcon = null;

    connect() {
        // use some buffer-space so that the expand-button won't be included if just a couple of lines would be hidden
        this.contentTarget.style.maxHeight = MAX_FULL_HEIGHT;

        // give the render-engine some time
        setTimeout(() => {
            if (!this.checkSize()) {
                this.contentTarget.style.maxHeight = null;
                return;
            }

            this.setupButton();
            this.setExpanded(false, true);
        }, 5);
    }

    checkSize() {
        const elem = this.contentTarget;
        return elem.scrollHeight - 30 > elem.clientHeight || elem.scrollWidth > elem.clientWidth;
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
            this.contentTarget.style.maxHeight = MAX_COLLAPSED_HEIGHT;
            this.buttonIcon.classList.remove('fa-angles-up');
            this.buttonIcon.classList.add('fa-angles-down');

            if (!skipEffects) {
                this.contentTarget.scrollIntoView();
            }
        }

        this.isExpanded = expanded;
    }
}
