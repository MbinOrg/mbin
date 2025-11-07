import { fetch, ok } from '../utils/http';
import { Controller } from '@hotwired/stimulus';
import router from '../utils/routing';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    /**
     * Instance of setTimeout to be used for the display of the popup. This is cleared if the user
     * exits the target before the delay is reached
     */
    userPopupTimeout;

    /**
     * Delay to wait until the popup is displayed
     */
    userPopupTimeoutDelay = 1200;

    /**
     * Called on mouseover
     * @param {*} event
     * @returns
     */
    async userPopup(event) {

        if (false === event.target.matches(':hover')) {
            return;
        }

        //create a setTimeout callback to be executed when the user has hovered over the target for a set amount of time
        this.userPopupTimeout = setTimeout(this.triggerUserPopup, this.userPopupTimeoutDelay, event);
    }

    /**
     * Called on mouseout, cancel the UI popup as the user has moved off the element
     */
    async userPopupOut() {
        clearTimeout(this.userPopupTimeout);
    }

    /**
     * Called when the user popup should open
     */
    async triggerUserPopup(event) {

        try {
            let param = event.params.username;

            if ('@' === param.charAt(0)) {
                param = param.substring(1);
            }
            const username = param.includes('@') ? `@${param}` : param;
            const url = router().generate('ajax_fetch_user_popup', { username: username });

            this.loadingValue = true;

            let response = await fetch(url);

            response = await ok(response);
            response = await response.json();

            document.querySelector('.popover').innerHTML = response.html;

            popover.trigger = event.target;
            popover.selectedTrigger = event.target;
            popover.element.dispatchEvent(new Event('openPopover'));
        } catch {
        } finally {
            this.loadingValue = false;
        }
    }

    async navigateUser(event) {
        event.preventDefault();

        window.location = '/u/' + event.params.username;
    }

    async navigateMagazine(event) {
        event.preventDefault();

        window.location = '/m/' + event.params.username;
    }
}
