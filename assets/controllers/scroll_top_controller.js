import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const self = this;
        window.onscroll = function () {
            self.scroll();
        };
    }

    scroll() {
        if (
            20 < document.body.scrollTop ||
            20 < document.documentElement.scrollTop
        ) {
            this.element.style.display = 'block';
        } else {
            this.element.style.display = 'none';
        }
    }

    increaseCounter() {
        const counter = this.element.querySelector('small');
        counter.innerHTML = parseInt(counter.innerHTML) + 1;
        counter.classList.remove('hidden');
    }

    scrollTop() {
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
    }
}
