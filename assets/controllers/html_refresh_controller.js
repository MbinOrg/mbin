import { fetch, ok } from '../utils/http';
import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    /**
     * Calls the address attached to the nearest link node. Replaces the outer html of the nearest `cssclass` parameter
     * with the response from the link
     */
    async linkCallback(event) {
        event.preventDefault();
        const { cssclass: cssClass, refreshlink: refreshLink, refreshselector: refreshSelector } = event.params;

        const a = event.target.closest('a');
        const subjectController = this.application.getControllerForElementAndIdentifier(this.element, 'subject');

        try {
            if (subjectController) {
                subjectController.loadingValue = true;
            }

            let response = await fetch(a.href);

            response = await ok(response);
            response = await response.json();

            event.target.closest(`.${cssClass}`).outerHTML = response.html;

            const refreshElement = this.element.querySelector(refreshSelector);

            if (!!refreshLink && '' !== refreshLink && !!refreshElement) {
                let response = await fetch(refreshLink);

                response = await ok(response);
                response = await response.json();
                refreshElement.outerHTML = response.html;
            }
        } catch (e) {
            console.error(e);
        } finally {
            if (subjectController) {
                subjectController.loadingValue = false;
            }
        }
    }
}
