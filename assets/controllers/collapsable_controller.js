import { Controller } from '@hotwired/stimulus';

const MAX_COLLAPSED_HEIGHT = '25rem';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static targets = ['content', 'button'];

    isExpanded = true;
    button = null;
    buttonIcon = null;

    connect() {
        this.contentTarget.style.maxHeight = MAX_COLLAPSED_HEIGHT;

        if (!this.checkSize()) {
            return;
        }

        this.setupButton();
        this.setExpanded(false);
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
            this.setExpanded(!this.isExpanded);
        });

        this.buttonTarget.appendChild(this.button);
    }

    setExpanded(expanded) {
        if (expanded) {
            this.contentTarget.style.maxHeight = null;
            this.buttonIcon.classList.remove('fa-angles-down');
            this.buttonIcon.classList.add('fa-angles-up');
        } else {
            this.contentTarget.style.maxHeight = MAX_COLLAPSED_HEIGHT;
            this.buttonIcon.classList.remove('fa-angles-up');
            this.buttonIcon.classList.add('fa-angles-down');

            this.contentTarget.scrollIntoView();
        }

        this.isExpanded = expanded;
    }
}
