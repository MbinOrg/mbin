import { fetch, ok } from '../utils/http';
import { Controller } from '@hotwired/stimulus';
import getIntIdFromElement from '../utils/mbin';
import router from '../utils/routing';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['main', 'loader', 'expand', 'collapse', 'comments'];
    static values = {
        loading: Boolean,
    };

    async expandComments(event) {
        event.preventDefault();

        if (true === this.loadingValue) {
            return;
        }

        try {
            this.loadingValue = true;

            const url = router().generate('ajax_fetch_post_comments', { 'id': getIntIdFromElement(this.mainTarget) });

            let response = await fetch(url, { method: 'GET' });

            response = await ok(response);
            response = await response.json();

            this.collapseComments();

            this.commentsTarget.innerHTML = response.html;

            if (this.commentsTarget.children.length && this.commentsTarget.children[0].classList.contains('comments')) {
                const container = this.commentsTarget.children[0];
                const parentDiv = container.parentNode;

                while (container.firstChild) {
                    parentDiv.insertBefore(container.firstChild, container);
                }

                parentDiv.removeChild(container);
            }

            this.expandTarget.style.display = 'none';
            this.collapseTarget.style.display = 'block';
            this.commentsTarget.style.display = 'block';

            this.application
                .getControllerForElementAndIdentifier(document.getElementById('main'), 'lightbox')
                .connect();
            this.application
                .getControllerForElementAndIdentifier(document.getElementById('main'), 'timeago')
                .connect();
        } catch (e) {
            console.error(e);
        } finally {
            this.loadingValue = false;
        }
    }

    collapseComments(event) {
        event?.preventDefault();

        while (this.commentsTarget.firstChild) {
            this.commentsTarget.removeChild(this.commentsTarget.firstChild);
        }

        this.expandTarget.style.display = 'block';
        this.collapseTarget.style.display = 'none';
        this.commentsTarget.style.display = 'none';
    }

    async expandVoters(event) {
        event?.preventDefault();

        try {
            this.loadingValue = true;

            let response = await fetch(event.target.href, { method: 'GET' });

            response = await ok(response);
            response = await response.json();

            event.target.parentNode.innerHTML = response.html;
        } catch (e) {
            console.error(e);
        } finally {
            this.loadingValue = false;
        }
    }

    loadingValueChanged(val) {
        const subjectController = this.application.getControllerForElementAndIdentifier(this.mainTarget, 'subject');
        if (null !== subjectController) {
            subjectController.loadingValue = val;
        }
    }
}
