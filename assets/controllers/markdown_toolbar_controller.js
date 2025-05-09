// SPDX-FileCopyrightText: 2023-2024 /kbin & Mbin contributors
//
// SPDX-License-Identifier: AGPL-3.0-only

import { Controller } from '@hotwired/stimulus';
import { createPopper } from '@popperjs/core';
import 'emoji-picker-element';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    addSpoiler(event) {
        event.preventDefault();

        const input = document.getElementById(this.element.getAttribute('for'));
        let spoilerBody = 'spoiler body';
        let contentAfterCursor;

        const start = input.selectionStart;
        const end = input.selectionEnd;

        const contentBeforeCursor = input.value.substring(0, start);
        if (start === end) {
            contentAfterCursor = input.value.substring(start);
        } else {
            contentAfterCursor = input.value.substring(end);
            spoilerBody = input.value.substring(start, end);
        }

        const spoiler = `
::: spoiler spoiler-title
${spoilerBody}
:::`;

        input.value = contentBeforeCursor + spoiler + contentAfterCursor;
        input.dispatchEvent(new Event('input'));

        const spoilerTitlePosition = contentBeforeCursor.length + '::: spoiler '.length + 1;
        input.setSelectionRange(spoilerTitlePosition, spoilerTitlePosition);
        input.focus();
    }

    toggleEmojiPicker(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const tooltip = document.querySelector('.tooltip');
        const emojiPicker = document.getElementById('emoji-picker');
        const input = document.getElementById(this.element.getAttribute('for'));

        // Create Popper instance if it doesn't exist
        if (!this.popperInstance) {
            this.popperInstance = createPopper(button, tooltip, {
                placement: 'bottom-end',
            });
        }

        tooltip.classList.toggle('shown');

        if (tooltip.classList.contains('shown')) {
            const emojiClickHandler = (event) => {
                const emoji = event.detail.emoji.unicode;
                const start = input.selectionStart;
                const end = input.selectionEnd;

                input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
                input.focus();
                input.setSelectionRange(start + emoji.length, start + emoji.length);

                tooltip.classList.remove('shown');
                emojiPicker.removeEventListener('emoji-click', emojiClickHandler);
            };

            emojiPicker.addEventListener('emoji-click', emojiClickHandler);
        }
    }
}
