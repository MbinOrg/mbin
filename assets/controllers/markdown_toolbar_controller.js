// SPDX-FileCopyrightText: 2023-2024 /kbin & Mbin contributors
//
// SPDX-License-Identifier: AGPL-3.0-only

import 'emoji-picker-element';
import { autoUpdate, computePosition, flip, limitShift, shift } from '@floating-ui/dom';
import { Controller } from '@hotwired/stimulus';

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
        const input = document.getElementById(this.element.getAttribute('for'));
        const tooltip = document.querySelector('#tooltip');
        const emojiPicker = document.querySelector('#emoji-picker');

        // Remove any existing event listener
        if (this.emojiClickHandler) {
            emojiPicker.removeEventListener('emoji-click', this.emojiClickHandler);
        }

        if (!this.cleanupTooltip) {
            this.cleanupTooltip = autoUpdate(button, tooltip, () => {
                computePosition(button, tooltip, {
                    placement: 'bottom',
                    middleware: [flip(), shift({ limiter: limitShift() })],
                }).then(({ x, y }) => {
                    Object.assign(tooltip.style, {
                        left: `${x}px`,
                        top: `${y}px`,
                    });
                });
            });
        }

        tooltip.classList.toggle('shown');

        if (tooltip.classList.contains('shown')) {
            this.emojiClickHandler = (event) => {
                const emoji = event.detail.emoji.unicode;
                const start = input.selectionStart;
                const end = input.selectionEnd;

                input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
                const emojiPosition = start + emoji.length;
                input.setSelectionRange(emojiPosition, emojiPosition);
                input.focus();

                tooltip.classList.remove('shown');
                this.cleanupTooltip();
                emojiPicker.removeEventListener('emoji-click', this.emojiClickHandler);
                this.emojiClickHandler = null;
            };

            emojiPicker.addEventListener('emoji-click', this.emojiClickHandler);
        }
    }
}
