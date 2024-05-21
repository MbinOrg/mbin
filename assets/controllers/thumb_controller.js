import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    /**
     * Called on mouseover
     * @param {*} event
     * @returns
     */
    async adult_image_hover(event) {
        if (false === event.target.matches(':hover')) {
            return;
        }

        event.target.style.filter = 'none';
    }

    /**
     * Called on mouseout
     * @param {*} event
     */
    async adult_image_hover_out(event) {
        event.target.style.filter = 'blur(8px)';
    }
}
