import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    changeLocation(event) {
        window.location = event.currentTarget.value;
    }
}
