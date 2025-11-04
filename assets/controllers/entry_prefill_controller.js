import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static targets = [ 'url', 'title', 'body', 'nsfw', 'oc', 'tags', 'imageUrl', 'imageAlt' ];

    params;

    connect() {
        this.params = this.parseParams();
        if (null === this.params) {
            return;
        }

        this.applyTextValue('url', this.urlTarget);
        this.applyTextValue('title', this.titleTarget);
        this.applyTextValue('body', this.bodyTarget);
        this.applyTextValue('imageUrl', this.imageUrlTarget);
        this.applyTextValue('imageAlt', this.imageAltTarget);
        this.applyBoolValue('nsfw', this.nsfwTarget);
        this.applyBoolValue('oc', this.ocTarget);
        this.applyTags();
    }

    parseParams() {
        try {
            let hash = location.hash;
            if (0 === hash.length) {
                return null;
            }
            hash = hash.substring(1);
            return new URLSearchParams(hash);
        } catch (e) {
            console.warn('entry_prefill_controller: unable to parse params', e);
            return null;
        }
    }

    applyTextValue(name, target) {
        try {
            const value = this.params.get(`prefill-${name}`);
            if (null !== value) {
                target.value = decodeURIComponent(value);
            }
        } catch (e) {
            console.warn(`entry_prefill_controller: unable to prefill param ${name}`, e);
        }
    }

    applyBoolValue(name, target) {
        try {
            const value = this.params.get(`prefill-${name}`);
            if ('0' === value) {
                target.checked = false;
            } else if ('1' === value) {
                target.checked = true;
            } else if (null !== value) {
                console.warn(`entry_prefill_controller: invalid value for param ${name}`);
            }
        } catch (e) {
            console.warn(`entry_prefill_controller: unable to prefill param ${name}`, e);
        }
    }

    applyTags() {
        const tagInp = this.tagsTarget.querySelector('#entry_tags-ts-control');
        const values = this.params.getAll('prefill-tags');
        for (const value of values) {
            tagInp.value = value;

            const ev = new KeyboardEvent('keydown', { keyCode: 13 });
            tagInp.dispatchEvent(ev);
        }
    }
}
