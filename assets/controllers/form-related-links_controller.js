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
        console.log('addRelatedElement method');

        const item = document.createElement('span');
        item.className = 'flex related-link-item';

        const nodeLink = this.#htmlToNode(this.linkValue.replace(
            /__name__/g,
            this.indexValue,
        ));
        item.append(nodeLink);

        this.#addButtonDeleteLink(item);

        this.relatedContainerTarget.appendChild(item);
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
