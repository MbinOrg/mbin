import { fetch, ok } from '../utils/http';
import { Controller } from '@hotwired/stimulus';
import { useIntersection } from 'stimulus-use';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['loader', 'pagination'];
    static values = {
        loading: Boolean,
    };

    connect() {
        window.infiniteScrollUrls = [];
        useIntersection(this);
    }

    async appear() {
        if (true === this.loadingValue) {
            return;
        }

        try {
            this.loadingValue = true;

            const cursorPaginationElement = this.paginationTarget.getElementsByClassName('cursor-pagination');
            let paginationElem = null;
            if (cursorPaginationElement.length) {
                console.log(cursorPaginationElement[0]);
                const button = cursorPaginationElement[0].getElementsByTagName('a');
                if (!button.length) {
                    throw new Error('No more pages');
                }
                paginationElem = button[0];
            } else {
                paginationElem = this.paginationTarget.getElementsByClassName('pagination__item--current-page')[0].nextElementSibling;
                if (paginationElem.classList.contains('pagination__item--disabled')) {
                    throw new Error('No more pages');
                }
            }

            if (window.infiniteScrollUrls.includes(paginationElem.href)) {
                return;
            }

            window.infiniteScrollUrls.push(paginationElem.href);

            this.handleEntries(paginationElem.href);
        } catch {
            this.loadingValue = false;
            this.showPagination();
        }
    }

    async handleEntries(url) {
        let response = await fetch(url, { method: 'GET' });

        response = await ok(response);

        try {
            response = await response.json();
        } catch {
            this.showPagination();
            throw new Error('Invalid JSON response');
        }

        const div = document.createElement('div');
        div.innerHTML = response.html;

        const elements = div.querySelectorAll('[data-controller="subject-list"] > *');
        for (let i = 0; i < elements.length; i++) {
            const element = elements[i];
            if ((element.id && null === document.getElementById(element.id)) || element.classList.contains('user-box-inline') || element.classList.contains('magazine') || element.classList.contains('post-container')) {
                this.element.before(element);

                if (elements[i + 1] && elements[i + 1].classList.contains('post-comments')) {
                    this.element.before(elements[i + 1]);
                }
            }
        }

        const scroll = div.querySelector('[data-controller="infinite-scroll"]');
        if (scroll) {
            this.element.after(div.querySelector('[data-controller="infinite-scroll"]'));
        }

        this.element.remove();

        this.application
            .getControllerForElementAndIdentifier(document.getElementById('main'), 'lightbox')
            .connect();
        this.application
            .getControllerForElementAndIdentifier(document.getElementById('main'), 'timeago')
            .connect();
    }

    loadingValueChanged(val) {
        this.loaderTarget.style.display = true === val ? 'block' : 'none';
    }

    showPagination() {
        this.loadingValue = false;
        this.paginationTarget.classList.remove('visually-hidden');
    }
}
