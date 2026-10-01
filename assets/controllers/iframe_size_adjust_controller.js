import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    connect() {
        /** @var HTMLIFrameElement frame */
        const frame = this.element;
        if ('complete' === frame.contentDocument?.readyState) {
            this.resize();
        } else {
            frame.addEventListener('load', () => this.resize());
        }
    }

    resize() {
        /** @var HTMLIFrameElement frame */
        const frame = this.element;
        const frameBody = frame.contentWindow.document.body;

        frameBody.style.width = 'fit-content';

        frame.style.height = `${frameBody.scrollHeight + 20}px`;
        frame.style.width = `${frameBody.scrollWidth + 20}px`;
    }
}
