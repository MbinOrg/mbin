import { fetch, ok } from '../utils/http';
import { Controller } from '@hotwired/stimulus';
import router from '../utils/routing';
import { useThrottle } from 'stimulus-use';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        loading: Boolean,
    };

    static targets = ['container'];
    static throttles = ['show'];

    connect() {
        useThrottle(this, { wait: 1000 });

        // workaround: give itself a container if it couldn't find one
        // I am not happy with this
        if (!this.hasContainerTarget && this.element.matches('span.preview')) {
            const container = this.createContainerTarget('preview-target');
            this.element.insertAdjacentElement('beforeend', container);
            console.warn('unable to find container target, creating one for itself at', this.element.lastChild);
        }
    }

    createContainerTarget(extraClasses) {
        const classes = [].concat(extraClasses ?? []);

        const div = document.createElement('div');
        div.classList.add(...classes, 'hidden');
        div.dataset.previewTarget = 'container';

        return div;
    }

    async retry(event) {
        event.preventDefault();

        this.containerTarget.replaceChildren();
        this.containerTarget.classList.add('hidden');

        await this.show(event);
    }

    async show(event) {
        event.preventDefault();

        if (this.containerTarget.hasChildNodes()) {
            this.containerTarget.classList.toggle('hidden');
            return;
        }

        try {
            this.loadingValue = true;

            let response = await fetch(router().generate('ajax_fetch_embed', { url: event.params.url }), { method: 'GET' });

            response = await ok(response);
            response = await response.json();

            this.containerTarget.innerHTML = response.html;
            this.containerTarget.classList.remove('hidden');
            if (event.params.ratio) {
                this.containerTarget
                    .querySelector('.preview')
                    .classList.add('ratio');
            }
            this.loadScripts(response.html);
        } catch (e) {
            console.error('preview failed: ', e);
            const failedHtml =
                `<div class="preview">
                    <a class="retry-failed" href="#"
                        data-action="preview#retry"
                        data-preview-url-param="${event.params.url}"
                        data-preview-ratio-param="${event.params.ratio}">
                            Failed to load. Click here to retry.
                    </a>
                </div>`;
            this.containerTarget.innerHTML = failedHtml;
            this.containerTarget.classList.remove('hidden');
        } finally {
            this.loadingValue = false;
        }
    }

    loadScripts(response) {
        const tmp = document.createElement('div');
        tmp.innerHTML = response;
        const el = tmp.getElementsByTagName('script');

        if (el.length) {
            const script = document.createElement('script');
            script.setAttribute('src', el[0].getAttribute('src'));
            script.setAttribute('async', 'false');

            // let exists = [...document.head.querySelectorAll('script')]
            //     .filter(value => value.getAttribute('src') >= script.getAttribute('src'));
            //
            // if (exists.length) {
            //     return;
            // }

            const head = document.head;
            head.insertBefore(script, head.firstElementChild);
        }
    }

    loadingValueChanged(val) {
        const subject = this.element.closest('.subject');
        if (null !== subject) {
            const subjectController = this.application.getControllerForElementAndIdentifier(subject, 'subject');
            subjectController.loadingValue = val;
        }
    }
}
