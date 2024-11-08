import { ApplicationController, useThrottle } from 'stimulus-use';
import { fetch, ok } from '../utils/http';
import router from '../utils/routing';

/* stimulusFetch: 'lazy' */
export default class extends ApplicationController {
    static throttles = ['fetchLink'];
    static targets = ['title', 'description', 'url', 'loader'];
    static values = {
        loading: Boolean,
    };

    timeoutId = null;

    connect() {
        useThrottle(this, {
            wait: 1000,
        });

        const params = new URLSearchParams(window.location.search);
        const url = params.get('url');
        if (url) {
            this.urlTarget.value = url;
            this.urlTarget.dispatchEvent(new Event('input'));
        }
    }

    fetchLink(event) {
        if (!event.target.value) {
            return;
        }

        if (this.timeoutId) {
            window.clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }

        this.timeoutId = window.setTimeout(() => {
            this.loadingValue = true;
            this.fetchTitleAndDescription(event)
                .then(() => {
                    this.loadingValue = false;
                    this.timeoutId = null;
                })
                .catch(() => {
                    this.loadingValue = false;
                    this.timeoutId = null;
                })
        }, 1000)
    }

    loadingValueChanged(val) {
        this.titleTarget.disabled = val;
        this.descriptionTarget.disabled = val;

        if (val) {
            this.loaderTarget.classList.remove('hide');
        } else {
            this.loaderTarget.classList.add('hide');
        }
    }

    async fetchTitleAndDescription(event) {
        if (this.titleTarget.value && false === confirm('Are you sure you want to fetch the title and description? This will overwrite the current values.')) {
            return;
        }

        const url = router().generate('ajax_fetch_title');
        let response = await fetch(url, {
            method: 'POST',
            body: JSON.stringify({
                'url': event.target.value,
            }),
        });

        response = await ok(response);
        response = await response.json();

        this.titleTarget.value = response.title;
        this.descriptionTarget.value = response.description;

        // required for input length indicator
        this.titleTarget.dispatchEvent(new Event('input'));
        this.descriptionTarget.dispatchEvent(new Event('input'));
    }
}
