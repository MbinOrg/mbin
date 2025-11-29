import { Controller } from '@hotwired/stimulus';
import { fetch } from '../utils/http';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.element.addEventListener('keydown', this.handleInput.bind(this));
        this.element.addEventListener('blur', this.delayedClearAutocomplete.bind(this));
    }

    // map: allowed enclosure key -> max repeats
    enclosureKeys = {
        '`': 1, '"': 1, "'": 1,
        '*': 2, '_': 2, '~': 2,
    };

    emojiAutocompleteActive = false;
    mentionAutocompleteActive = false;

    abortController;
    requestActive = false;

    selectedSuggestionIndex = 0;

    handleInput (event) {
        const hasSelection = this.element.selectionStart !== this.element.selectionEnd;
        const key = event.key;

        if (event.ctrlKey && 'Enter' === key) {
            // ctrl + enter to submit form

            this.element.form.submit();
            event.preventDefault();
        } else if (event.ctrlKey && 'b' === key) {
            // ctrl + b to toggle bold

            this.toggleFormattingEnclosure('**');
            event.preventDefault();
        } else if (event.ctrlKey && 'i' === key) {
            // ctrl + i to toggle italic

            this.toggleFormattingEnclosure('_');
            event.preventDefault();
        } else if (hasSelection && key in this.enclosureKeys) {
            // toggle/cycle wrapping on selection texts

            this.toggleFormattingEnclosure(key, this.enclosureKeys[key] ?? 1);
            event.preventDefault();
        } else if (!this.emojiAutocompleteActive && !this.mentionAutocompleteActive && ':' === key) {
            this.emojiAutocompleteActive = true;
        } else if (this.emojiAutocompleteActive && ('Escape' === key || ' ' === key)) {
            this.clearAutocomplete();
        } else if (!this.emojiAutocompleteActive && !this.mentionAutocompleteActive && '@' === key) {
            this.mentionAutocompleteActive = true;
        } else if (this.mentionAutocompleteActive && ('Escape' === key || ' ' === key)) {
            this.clearAutocomplete();
        } else if (this.mentionAutocompleteActive || this.emojiAutocompleteActive) {
            if ('ArrowDown' === key || 'ArrowUp' === key) {
                if ('ArrowDown' === key) {
                    this.selectedSuggestionIndex = Math.min(this.getSuggestionElements().length-1, this.selectedSuggestionIndex + 1);
                } else if ('ArrowUp' === key) {
                    this.selectedSuggestionIndex = Math.max(0, this.selectedSuggestionIndex - 1);
                }
                this.markSelectedSuggestion();
                event.preventDefault();
            } else if ('Enter' === key) {
                this.replaceAutocompleteSearchString(this.getSelectedSuggestionReplacement());
                event.preventDefault();
            } else {
                this.fetchAutocompleteResults(this.getAutocompleteSearchString(key));
            }
        }
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

    delayedClearAutocomplete() {
        window.setTimeout(() => this.clearAutocomplete(), 100);
    }

    clearAutocomplete() {
        this.selectedSuggestionIndex = 0;
        this.emojiAutocompleteActive = false;
        this.mentionAutocompleteActive = false;
        this.abortController.abort();
        this.requestActive = false;
        document.getElementById('user-suggestions')?.remove();
        document.getElementById('emoji-suggestions')?.remove();
    }

    getAutocompleteSearchString(key) {
        const [wordStart, wordEnd] = this.getAutocompleteSearchStringStartAndEnd();
        let val = this.element.value.substring(wordStart, wordEnd+1);

        if (1 === key.length) {
            val += key;
        }

        return val;
    }

    getAutocompleteSearchStringStartAndEnd() {
        const value = this.element.value;
        const selection = this.element.selectionStart-1;
        let cursor = selection;
        const breakCharacters = ' \n\t*#?!';
        while (0 < cursor) {
            cursor--;
            if (breakCharacters.includes(value[cursor])) {
                cursor++;
                break;
            }
        }
        const wordStart = cursor;
        cursor = selection;

        while (cursor < value.length) {
            cursor++;
            if (breakCharacters.includes(value[cursor])) {
                cursor--;
                break;
            }
        }
        const wordEnd = cursor;

        return [wordStart, wordEnd];
    }

    fetchAutocompleteResults(searchText) {
        if (this.requestActive) {
            this.abortController.abort();
        }

        if (this.mentionAutocompleteActive) {
            this.abortController = new AbortController();
            this.requestActive = true;
            fetch(`/ajax/fetch_users_suggestions/${searchText}`, { signal: this.abortController.signal })
                .then((response) => response.json())
                .then((data) => {
                    this.fillSuggestions(data.html);
                    this.requestActive = false;
                })
                .catch(() => {});
        } else if (this.emojiAutocompleteActive) {
            this.abortController = new AbortController();
            this.requestActive = true;
            fetch(`/ajax/fetch_emoji_suggestions?query=${searchText}`, { signal: this.abortController.signal })
                .then((response) => response.json())
                .then((data) => {
                    this.fillSuggestions(data.html);
                    this.requestActive = false;
                })
                .catch(() => {});
        }
    }

    replaceAutocompleteSearchString(replaceText) {
        const [wordStart, wordEnd] = this.getAutocompleteSearchStringStartAndEnd();
        this.element.selectionStart = wordStart;
        this.element.selectionEnd = wordEnd+1;
        document.execCommand('insertText', false, replaceText);
        this.clearAutocomplete();
        const resultCursor = wordStart + replaceText.length;
        this.element.selectionStart = resultCursor;
        this.element.selectionEnd = resultCursor;
    }

    fillSuggestions (html) {
        const id = this.mentionAutocompleteActive ? 'user-suggestions' : 'emoji-suggestions';
        let element = document.getElementById(id);
        if (element) {
            element.outerHTML = html;
        } else {
            element = this.element.insertAdjacentElement('afterend', document.createElement('div'));
            element.outerHTML = html;
        }
        for (const suggestion of this.getSuggestionElements()) {
            suggestion.onclick = (event) => {
                const value = event.target.getAttribute('data-replace') ?? event.target.outerText;
                this.element.focus();
                this.replaceAutocompleteSearchString(value);
            };
        }
        this.markSelectedSuggestion();
    }

    markSelectedSuggestion() {
        let i = 0;
        for (const suggestion of this.getSuggestionElements()) {
            if (i === this.selectedSuggestionIndex) {
                suggestion.classList.add('selected');
            } else {
                suggestion.classList.remove('selected');
            }
            i++;
        }
    }

    getSelectedSuggestionReplacement() {
        let i = 0;
        for (const suggestion of this.getSuggestionElements()) {
            if (i === this.selectedSuggestionIndex) {
                suggestion.classList.add('selected');
                return suggestion.getAttribute('data-replace') ?? suggestion.outerText;
            }
            i++;
        }
        return null;
    }

    getSuggestionElements() {
        const suggestions = document.getElementById(this.mentionAutocompleteActive ? 'user-suggestions' : 'emoji-suggestions');
        return suggestions.querySelectorAll('.suggestion');
    }
}
