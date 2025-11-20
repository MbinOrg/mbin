import { fetch, ok } from '../utils/http';
import getIntIdFromElement, { getDepth, getLevel, getTypeFromNotification } from '../utils/mbin';
import { Controller } from '@hotwired/stimulus';
import router from '../utils/routing';
import { useIntersection } from 'stimulus-use';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static previewInit = false;
    static targets = ['loader', 'more', 'container', 'commentsCounter', 'favCounter', 'upvoteCounter', 'downvoteCounter'];
    static values = {
        loading: Boolean,
    };
    static sendBtnLabel = null;

    connect() {
        this.wireMoreFocusClassAdjustment();

        if (this.element.classList.contains('show-preview')) {
            useIntersection(this);
        }

        this.wireTouchEvent();
    }

    async getForm(event) {
        event.preventDefault();

        if ('' !== this.containerTarget.innerHTML.trim()) {
            if (false === confirm('Do you really want to leave?')) {
                return;
            }
        }

        try {
            this.loadingValue = true;

            let response = await fetch(event.target.href, { method: 'GET' });

            response = await ok(response);
            response = await response.json();

            this.containerTarget.style.display = 'block';
            this.containerTarget.innerHTML = response.form;

            const textarea = this.containerTarget.querySelector('textarea');
            if (textarea) {
                if ('' !== textarea.value) {
                    let firstLineEnd = textarea.value.indexOf('\n');
                    if (-1 === firstLineEnd) {
                        firstLineEnd = textarea.value.length;
                        textarea.value = textarea.value.slice(0, firstLineEnd) + ' ' + textarea.value.slice(firstLineEnd);
                        textarea.selectionStart = firstLineEnd + 1;
                        textarea.selectionEnd = firstLineEnd + 1;
                    } else {
                        textarea.value = textarea.value.slice(0, firstLineEnd) + ' ' + textarea.value.slice(firstLineEnd);
                        textarea.selectionStart = firstLineEnd + 1;
                        textarea.selectionEnd = firstLineEnd + 1;
                    }
                }

                textarea.focus();
            }
        } catch {
            window.location.href = event.target.href;
        } finally {
            this.loadingValue = false;
            popover.togglePopover(false);
        }
    }

    async sendForm(event) {
        event.preventDefault();

        const form = event.target.closest('form');
        const url = form.action;

        try {
            this.loadingValue = true;
            self.sendBtnLabel = event.target.innerHTML;
            event.target.disabled = true;
            event.target.innerHTML = 'Sending...';

            let response = await fetch(url, {
                method: 'POST',
                body: new FormData(form),
            });

            response = await ok(response);
            response = await response.json();

            if (response.form) {
                this.containerTarget.style.display = 'block';
                this.containerTarget.innerHTML = response.form;
            } else if (form.classList.contains('replace')) {
                const div = document.createElement('div');
                div.innerHTML = response.html;
                div.firstElementChild.className = this.element.className;

                this.element.innerHTML = div.firstElementChild.innerHTML;
            } else {
                const div = document.createElement('div');
                div.innerHTML = response.html;

                const level = getLevel(this.element);
                const depth = getDepth(this.element);

                div.firstElementChild.classList.remove('comment-level--1');
                div.firstElementChild.classList.add('comment-level--' + (10 <= level ? 10 : level + 1));
                div.firstElementChild.dataset.commentCollapseDepthValue = depth + 1;

                if (this.element.nextElementSibling && this.element.nextElementSibling.classList.contains('comments')) {
                    this.element.nextElementSibling.appendChild(div.firstElementChild);
                    this.element.classList.add('mb-0');
                } else {
                    this.element.parentNode.insertBefore(div.firstElementChild, this.element.nextSibling);
                }

                this.containerTarget.style.display = 'none';
                this.containerTarget.innerHTML = '';
            }
        } catch (e) {
            console.error(e);
            // this.containerTarget.innerHTML = '';
        } finally {
            this.application
                .getControllerForElementAndIdentifier(document.getElementById('main'), 'lightbox')
                .connect();
            this.application
                .getControllerForElementAndIdentifier(document.getElementById('main'), 'timeago')
                .connect();
            this.loadingValue = false;
            event.target.disabled = false;
            event.target.innerHTML = self.sendBtnLabel;
        }

    }

    async favourite(event) {
        event.preventDefault();

        const form = event.target.closest('form');

        try {
            this.loadingValue = true;

            let response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
            });

            response = await ok(response);
            response = await response.json();

            form.innerHTML = response.html;
        } catch {
            form.submit();
        } finally {
            this.loadingValue = false;
        }
    }

    async vote(event) {
        event.preventDefault();

        const form = event.target.closest('form');

        try {
            this.loadingValue = true;

            let response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
            });

            response = await ok(response);
            response = await response.json();

            event.target.closest('.vote').outerHTML = response.html;
        } catch {
            form.submit();
        } finally {
            this.loadingValue = false;
        }
    }

    loadingValueChanged(val) {
        const submitButton = this.containerTarget.querySelector('form button[type="submit"]');

        if (true === val) {
            if (submitButton) {
                submitButton.disabled = true;
            }
            this.loaderTarget.style.display = 'block';
        } else {
            if (submitButton) {
                submitButton.disabled = false;
            }
            this.loaderTarget.style.display = 'none';
        }
    }

    async showModPanel(event) {
        event.preventDefault();

        let container = this.element.querySelector('.moderate-inline');
        if (null !== container) {
            // moderate panel was already added to this post, toggle
            // hidden on it to show/hide it and exit
            container.classList.toggle('hidden');
            return;
        }

        container = document.createElement('div');
        container.classList.add('moderate-inline');
        this.element.insertAdjacentHTML('beforeend', container.outerHTML);

        try {
            this.loadingValue = true;

            let response = await fetch(event.target.href);

            response = await ok(response);
            response = await response.json();

            this.element.querySelector('.moderate-inline').insertAdjacentHTML('afterbegin', response.html);
        } catch {
            window.location.href = event.target.href;
        } finally {
            this.loadingValue = false;
        }
    }

    notification(data) {
        if (data.detail.parentSubject && this.element.id === data.detail.parentSubject.htmlId) {
            if (data.detail.op.endsWith('CommentDeletedNotification') || data.detail.op.endsWith('CommentCreatedNotification')) {
                this.updateCommentCounter(data);
            }
        }

        if (this.element.id !== data.detail.htmlId) {
            return;
        }

        if (data.detail.op.endsWith('EditedNotification')) {
            this.refresh(data);
            return;
        }

        if (data.detail.op.endsWith('DeletedNotification')) {
            this.element.remove();
            return;
        }

        if (data.detail.op.endsWith('Vote')) {
            this.updateVotes(data);
            return;
        }

        if (data.detail.op.endsWith('Favourite')) {
            this.updateFavourites(data);
            return;
        }
    }

    async refresh(data) {
        try {
            this.loadingValue = true;

            const url = router().generate(`ajax_fetch_${getTypeFromNotification(data)}`, { id: getIntIdFromElement(this.element) });

            let response = await fetch(url);

            response = await ok(response);
            response = await response.json();

            const div = document.createElement('div');
            div.innerHTML = response.html;

            div.firstElementChild.className = this.element.className;
            this.element.outerHTML = div.firstElementChild.outerHTML;
        } catch {
        } finally {
            this.loadingValue = false;
        }
    }

    updateVotes(data) {
        this.upvoteCounterTarget.innerText = `(${data.detail.up})`;

        if (0 < data.detail.up) {
            this.upvoteCounterTarget.classList.remove('hidden');
        } else {
            this.upvoteCounterTarget.classList.add('hidden');
        }

        if (this.hasDownvoteCounterTarget) {
            this.downvoteCounterTarget.innerText = data.detail.down;
        }
    }

    updateFavourites(data) {
        if (this.hasFavCounterTarget) {
            this.favCounterTarget.innerText = data.detail.count;
        }
    }

    updateCommentCounter(data) {
        if (data.detail.op.endsWith('CommentCreatedNotification') && this.hasCommentsCounterTarget) {
            this.commentsCounterTarget.innerText = parseInt(this.commentsCounterTarget.innerText) + 1;
        }

        if (data.detail.op.endsWith('CommentDeletedNotification') && this.hasCommentsCounterTarget) {
            this.commentsCounterTarget.innerText = parseInt(this.commentsCounterTarget.innerText) - 1;
        }
    }

    async removeImage(event) {
        event.preventDefault();

        try {
            this.loadingValue = true;

            let response = await fetch(event.target.parentNode.formAction, { method: 'POST' });

            response = await ok(response);
            await response.json();

            event.target.parentNode.previousElementSibling.remove();
            event.target.parentNode.nextElementSibling.classList.remove('hidden');
            event.target.parentNode.remove();
        } catch {
        } finally {
            this.loadingValue = false;
        }
    }

    appear() {
        if (this.previewInit) {
            return;
        }

        const prev = this.element.querySelectorAll('.show-preview');

        prev.forEach((el) => {
            el.click();
        });

        this.previewInit = true;
    }

    wireMoreFocusClassAdjustment() {
        const self = this;
        if (this.hasMoreTarget) {
            this.moreTarget.addEventListener('focusin', () => {
                self.element.parentNode
                    .querySelectorAll('.z-5')
                    .forEach((el) => {
                        el.classList.remove('z-5');
                    });
                this.element.classList.add('z-5');
            });

            this.moreTarget.addEventListener('mouseenter', () => {
                const parent = self.element.parentNode;
                parent
                    .querySelectorAll('.z-5')
                    .forEach((el) => {
                        el.classList.remove('z-5');
                    });

                // Clear keyboard focus from any element inside the same
                // parent so that :focus-within is removed from the old
                // element without assigning focus to the hovered one.
                const active = document.activeElement;
                if (active && parent.contains(active) && !self.moreTarget.contains(active)) {
                    try {
                        active.blur();
                    } catch (e) {
                        // ignore environments where blur may throw
                    }
                }
            });
        }
    }

    wireTouchEvent() {
        // if in a list and the click is made via touch, open the post
        if (!this.element.classList.contains('isSingle')) {
            this.element.querySelector('.content')?.addEventListener('click', (e) => {
                if (e.defaultPrevented) {
                    return;
                }
                if ('a' === e.target.nodeName?.toLowerCase() || 'a' === e.target.tagName?.toLowerCase()) {
                    // ignore clicks on links
                    return;
                }
                if (
                    'details' === e.target.nodeName?.toLowerCase() || 'details' === e.target.tagName?.toLowerCase()
                    || 'summary' === e.target.nodeName?.toLowerCase() || 'summary' === e.target.tagName?.toLowerCase()
                ) {
                    // ignore clicks on spoilers
                    return;
                }
                if ('touch' === e.pointerType) {
                    const link = this.element.querySelector('header a:not(.user-inline)');
                    if (link) {
                        const href = link.getAttribute('href');
                        if (href) {
                            document.location.href = href;
                        }
                    }
                }
            });
        }
    }
}
