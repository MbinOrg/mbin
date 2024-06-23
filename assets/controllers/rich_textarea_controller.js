import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.element.addEventListener('keydown', this.handleInput.bind(this));
    }

    // map: allowed enclosure key -> max repeats
    enclosureKeys = {
        '`': 1, '"': 1, "'": 1,
        '*': 2, '_': 2, '~': 2,
    };

    handleInput (event) {
        const hasSelection = this.element.selectionStart !== this.element.selectionEnd;
        const key = event.key;

        if (event.ctrlKey && 'Enter' === key) {
            // ctrl + enter to submit form

            this.element.form.submit();
        } else if (event.ctrlKey && 'b' === key) {
            // ctrl + b to toggle bold

            this.toggleFormattingEnclosure('**');
        } else if (event.ctrlKey && 'i' === key) {
            // ctrl + i to toggle italic

            this.toggleFormattingEnclosure('_');
        } else if (hasSelection && key in this.enclosureKeys) {
            // toggle/cycle wrapping on selection texts

            this.toggleFormattingEnclosure(key, this.enclosureKeys[key] ?? 1);
        } else {
            return;
        }

        event.preventDefault();
    }

    toggleFormattingEnclosure(encl, maxLength = 1) {
        const start = this.element.selectionStart, end = this.element.selectionEnd;
        const before = this.element.value.substring(0, start),
            inner = this.element.value.substring(start, end),
            after = this.element.value.substring(end);

        // TODO: find a way to do undo-aware text manipulations that isn't deprecated like execCommand?
        // it seems like specs never actually replaced it with anything unless i'm missing it

        // remove enclosure when it's at the max
        const finalEnclosure = encl.repeat(maxLength);
        if (before.endsWith(finalEnclosure) && after.startsWith(finalEnclosure)) {
            const outerStart = start - finalEnclosure.length,
                outerEnd = end + finalEnclosure.length;

            this.element.selectionStart = outerStart;
            this.element.selectionEnd = outerEnd;

            // no need for delete command as insertText should deletes selection by itself
            // ref: https://developer.mozilla.org/en-US/docs/Web/API/Document/execCommand#inserttext
            document.execCommand('insertText', false, inner);

            this.element.selectionStart = start - finalEnclosure.length;
            this.element.selectionEnd = end - finalEnclosure.length;
        } else {
            // add a new enclosure

            document.execCommand('insertText', false, encl + inner + encl);

            this.element.selectionStart = start + encl.length;
            this.element.selectionEnd = end + encl.length;
        }
    }
}
