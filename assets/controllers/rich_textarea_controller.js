import {Controller} from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.element.addEventListener('keydown', this.handleInput.bind(this));
    }

    // map: allowed enclosure key -> max repeats
    enclosureKeys = {
        '`': 1, '~': 1, '"': 1, "'": 1,
        '*': 2, '_': 2,
    };

    handleInput (event) {
        let hasSelection = this.element.selectionStart != this.element.selectionEnd;
        let key = event.key;

        // ctrl + enter to submit form
        if (event.ctrlKey && key === "Enter") {
            this.element.form.submit();
        }

        // ctrl + b to toggle bold
        else if (event.ctrlKey && key === "b") {
            this.toggleFormattingEnclosure('**');
        }

        // ctrl + i to toggle italic
        else if (event.ctrlKey && key === "i") {
            this.toggleFormattingEnclosure('_');
        }

        // toggle/cycle wrapping on selection texts
        else if (hasSelection && key in this.enclosureKeys) {
            this.toggleFormattingEnclosure(key, this.enclosureKeys[key] ?? 1);
        }

        else {
            return;
        }

        event.preventDefault();
    }

    toggleFormattingEnclosure(encl, maxLength = 1) {
        const start = this.element.selectionStart, end = this.element.selectionEnd;
        const ranged = start != end;
        const before = this.element.value.substring(0, start),
            inner = this.element.value.substring(start, end),
            after = this.element.value.substring(end);

        // TODO: find a way to do undo-aware text manipulations that isn't deprecated like execCommand?
        // it seems like specs never actually replaced it with anything unless i'm missing it

        // remove enclosure when it's at the max
        const finalEnclosure = encl.repeat(maxLength);
        if (before.endsWith(finalEnclosure) && after.startsWith(finalEnclosure)) {
            this.element.selectionStart = start - finalEnclosure.length;
            this.element.selectionEnd = end + finalEnclosure.length;

            // TODO: find a way to do this that isn't deprecated?
            // it seems like this was never actually replaced by anything
            document.execCommand('delete', false, null);
            document.execCommand('insertText', false, inner);

            this.element.selectionStart = start - finalEnclosure.length;
            this.element.selectionEnd = end - finalEnclosure.length;
        }

        // add a new enclosure
        else {
            if (ranged) {
                document.execCommand('delete', false, null);
            }
            document.execCommand('insertText', false, encl + inner + encl);

            this.element.selectionStart = start + encl.length;
            this.element.selectionEnd = end + encl.length;
        }
    }
}
