import {Controller} from '@hotwired/stimulus';
import Subscribe from '../utils/event-source';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        user: String,
        magazine: String,
        entryId: String,
        postId: String,
    };

    connect() {
        this.es(this.getTopics());

        window.onbeforeunload = function (event) {
            if (window.es !== undefined) {
                window.es.close();
            }
        };
    }

    es(topics) {
        if (window.es !== undefined) {
            window.es.close();
        }

        let self = this;
        let cb = function (e) {
            let data = JSON.parse(e.data);

            self.dispatch(data.op, {detail: data});

            self.dispatch('Notification', {detail: data});

            // if (data.op.includes('Create')) {
            //     self.dispatch('CreatedNotification', {detail: data});
            // }

            // if (data.op === 'EntryCreatedNotification' || data.op === 'PostCreatedNotification') {
            //     self.dispatch('MainSubjectCreatedNotification', {detail: data});
            // }
            //
        }

        const eventSource = Subscribe(topics, cb);
        if (eventSource) {
            window.es = eventSource;
            // firefox bug: https://bugzilla.mozilla.org/show_bug.cgi?id=1803431
            if (navigator.userAgent.toLowerCase().indexOf('firefox') > -1) {
                let resubscribe = (e) => {
                    window.es.close();
                    setTimeout(() => {
                        const eventSource = Subscribe(topics, cb);
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

    getTopics() {
        let pub = true;
        const topics = [
            'count'
        ]

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
}
