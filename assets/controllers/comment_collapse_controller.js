import { Controller } from '@hotwired/stimulus';
import { getLevel } from '../utils/kbin';

const COMMENT_ELEMENT_TAG = 'BLOCKQUOTE';
const COLLAPSIBLE_CLASS = 'collapsible';
const COLLAPSED_CLASS = 'collapsed';
const HIDDEN_CLASS = 'hidden';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        depth: Number,
        hiddenBy: Number,
    };
    static targets = ['counter'];

    connect() {
        // derive depth value if it doesn't exist
        // or when attached depth is 1 but css depth says otherwise (trying to handle dynamic list)
        const cssLevel = getLevel(this.element);
        if (!this.hasDepthValue
            || (1 === this.depthValue && cssLevel > this.depthValue)) {
            this.depthValue = cssLevel;
        }

        this.element.classList.add(COLLAPSIBLE_CLASS);
        this.element.collapse = this;
    }

    // main function, use this in action
    toggleCollapse(event) {
        event.preventDefault();

        for (
            var nextSibling = this.element.nextElementSibling, collapsed = 0;
            nextSibling && COMMENT_ELEMENT_TAG === nextSibling.tagName;
            nextSibling = nextSibling.nextElementSibling
        ) {
            const siblingDepth = nextSibling.dataset.commentCollapseDepthValue;
            if (!siblingDepth || siblingDepth <= this.depthValue) {
                break;
            }

            this.toggleHideSibling(nextSibling, this.depthValue);
            collapsed += 1;
        }

        this.toggleCollapseSelf();

        if (0 < collapsed) {
            this.updateCounter(collapsed);
        }
    }

    // signals sibling comment element to hide itself
    toggleHideSibling(element, collapserDepth) {
        if (!element.collapse.hasHiddenByValue) {
            element.collapse.hiddenByValue = collapserDepth;
        } else if (collapserDepth === element.collapse.hiddenByValue) {
            element.collapse.hiddenByValue = undefined;
        }
    }

    // put itself into collapsed state
    toggleCollapseSelf() {
        this.element.classList.toggle(COLLAPSED_CLASS);
    }

    updateCounter(count) {
        if (!this.hasCounterTarget) {
            return;
        }

        if (this.element.classList.contains(COLLAPSED_CLASS)) {
            this.counterTarget.innerText = `(${count})`;
        } else {
            this.counterTarget.innerText = '';
        }
    }

    // using value changed callback to enforce proper state appearance

    // existence of hidden-by value means this comment is in hidden state
    // (basically display: none)
    hiddenByValueChanged() {
        if (this.hasHiddenByValue) {
            this.element.classList.add(HIDDEN_CLASS);
        } else {
            this.element.classList.remove(HIDDEN_CLASS);
        }
    }
}
