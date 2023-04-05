import {Controller} from '@hotwired/stimulus';
import {fetch, ok} from "../utils/http";
import {useIntersection} from 'stimulus-use'

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['loader', 'pagination'];
    static values = {
        loading: Boolean
    };

    connect() {
        window.infiniteScrollUrls = [];
        useIntersection(this)
    }

    async appear(event) {
        if (this.loadingValue === true) {
            return;
        }

        try {
            this.loadingValue = true;

            const paginationElem = this.paginationTarget.getElementsByClassName('pagination__item--current-page')[0].nextElementSibling;
            if (paginationElem.classList.contains('pagination__item--disabled')) {
                throw new Error('No more pages');
            }

            if (window.infiniteScrollUrls.includes(paginationElem.href)) {
                return;
            }

            window.infiniteScrollUrls.push(paginationElem.href);

            this.handleEntries(paginationElem.href);
        } catch (e) {
            this.loadingValue = false;
            this.showPagination();
        } finally {
        }
    }

    async handleEntries(url) {
        let response = await fetch(url, {method: 'GET'});

        response = await ok(response);

        try {
            response = await response.json();
        } catch (e) {
            this.showPagination();
            throw new Error('Invalid JSON response');
        }

        const div = document.createElement('div');
        div.innerHTML = response.html;

        const elements = div.getElementsByClassName('subject');

        for (const element of [...elements]) {
            if (null === document.getElementById(element.id)) {
                this.element.before(element);
            }
        }

        this.element.after(div.querySelector(`[data-controller='infinite-scroll']`));

        this.element.remove();

        this.application
            .getControllerForElementAndIdentifier(document.getElementById('main'), 'lightbox')
            .connect();
    }

    loadingValueChanged(val) {
        this.loaderTarget.style.display = val === true ? 'block' : 'none';
    }

    showPagination() {
        this.loadingValue = false;
        this.paginationTarget.classList.remove('visually-hidden');
    }
}