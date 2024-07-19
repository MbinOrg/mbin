import { Controller } from '@hotwired/stimulus';
import Subscribe from '../utils/event-source';
import {fetch, ThrowResponseIfNotOk} from "../utils/http";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        endpoint: String,
        user: String,
        magazine: String,
        entryId: String,
        postId: String,
    };

    connect() {
        if (this.endpointValue) {
            this.connectEs(this.endpointValue, this.getTopics());

            window.addEventListener('pagehide', this.closeEs);
        }
        if (this.userValue) {
            this.fetchAndSetNewNotificationAndMessageCount()
        }
    }

    disconnect() {
        this.closeEs();
    }

    connectEs(endpoint, topics) {
        this.closeEs();

        const cb = (e) => {
            const data = JSON.parse(e.data);

            this.dispatch(data.op, { detail: data });

            this.dispatch('Notification', { detail: data });

            // if (data.op.includes('Create')) {
            //     self.dispatch('CreatedNotification', {detail: data});
            // }

            // if (data.op === 'EntryCreatedNotification' || data.op === 'PostCreatedNotification') {
            //     self.dispatch('MainSubjectCreatedNotification', {detail: data});
            // }
            //
        };

        const eventSource = Subscribe(endpoint, topics, cb);
        if (eventSource) {
            window.es = eventSource;
            // firefox bug: https://bugzilla.mozilla.org/show_bug.cgi?id=1803431
            if (navigator.userAgent.toLowerCase().includes('firefox')) {
                const resubscribe = () => {
                    window.es.close();
                    setTimeout(() => {
                        const eventSource = Subscribe(endpoint, topics, cb);
                        if (eventSource) {
                            window.es = eventSource;
                            window.es.onerror = resubscribe;
                        }
                    }, 10000);
                };
                window.es.onerror = resubscribe;
            }
        }
    }

    closeEs() {
        if (window.es instanceof EventSource) {
            window.es.close();
        }
    }

    getTopics() {
        let pub = true;
        const topics = [
            'count',
        ];

        if (this.userValue) {
            topics.push(`/api/users/${this.userValue}`);
            pub = true;
        }

        if (this.magazineValue) {
            topics.push(`/api/magazines/${this.magazineValue}`);
            pub = false;
        }

        if (this.entryIdValue) {
            topics.push(`/api/entries/${this.entryIdValue}`);
            pub = false;
        }

        if (this.postIdValue) {
            topics.push(`/api/posts/${this.postIdValue}`);
            pub = false;
        }

        if (pub) {
            topics.push('pub');
        }

        return topics;
    }

    fetchAndSetNewNotificationAndMessageCount() {
        fetch("/ajax/fetch_user_notifications_count")
            .then(ThrowResponseIfNotOk)
            .then((data) => {
                if (typeof data.notifications === "number") {
                    this.setNotificationCount(data.notifications)
                }
                if (typeof data.messages === "number") {
                    this.setMessageCount(data.messages)
                }
                window.setTimeout(() => this.fetchAndSetNewNotificationAndMessageCount(), 10 * 1000)
            })
    }

    /**
     * @param {number} count
     */
    setNotificationCount(count) {
        let notificationHeader = self.window.document.getElementById("header-notification-count")
        notificationHeader.style.display = count ? "" : "none"
        this.setCountInSubBadgeElement(notificationHeader, count)
        let notificationDropdown = self.window.document.getElementById("dropdown-notifications-count")
        this.setCountInSubBadgeElement(notificationDropdown, count)
    }

    /**
     * @param {number} count
     */
    setMessageCount(count) {
        let messagesHeader = self.window.document.getElementById("header-messages-count")
        messagesHeader.style.display = count ? "" : "none"
        this.setCountInSubBadgeElement(messagesHeader, count)
        let messageDropdown = self.window.document.getElementById("dropdown-messages-count")
        this.setCountInSubBadgeElement(messageDropdown, count)
    }

    /**
     * @param {Element} element
     * @param {number} count
     */
    setCountInSubBadgeElement(element, count) {
        let badgeElements = element.getElementsByClassName("badge")
        for (let i = 0; i<badgeElements.length; i++) {
            let el = badgeElements.item(i)
            el.textContent = count.toString(10)
            el.style.display = count ? "" : "none"
        }
    }
}
