import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    ask(event) {
        if (!window.confirm(event.params.message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }
}
