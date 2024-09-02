import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['relatedContainer'];

    static values = {
        index: Number,
        link: String,
    };

    connect() {
        const container = this.element;
        container
            .querySelectorAll('.related-link-item')
            .forEach((item) => {
                this.#addButtonDeleteLink(item);
            });
    }

    addRelatedElement() {
        const nodeLink = this.#htmlToNode(this.linkValue.replace(
            /__name__/g,
            this.indexValue,
        ));

        this.#addButtonDeleteLink(nodeLink);

        this.relatedContainerTarget.appendChild(nodeLink);
        this.indexValue++;
    }

    #addButtonDeleteLink(item) {
        const removeFormButton = document.createElement('button');
        removeFormButton.innerText = '⌫';
        removeFormButton.className = 'btn';

        item.append(removeFormButton);

        removeFormButton.addEventListener('click', (e) => {
            e.preventDefault();
            item.remove();
        });
    }

    #htmlToNode(html) {
        const template = document.createElement('div');
        template.innerHTML = html;
        return template.firstChild;
    }
}
