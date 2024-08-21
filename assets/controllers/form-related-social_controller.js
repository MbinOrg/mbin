import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['relatedContainer'];

    static values = {
        index: Number,
        name: String,
        link: String,
    };

    connect() {
        const container = this.element;
        container
            .querySelectorAll('.related-link-group')
            .forEach((item) => {
                this.#addButtonDeleteLink(item);
            });
    }

    addRelatedElement() {
        const item = document.createElement('span');
        item.className = 'flex related-link-group';

        const nodeName = this.#htmlToNode(this.nameValue.replace(
            /__name__/g,
            this.indexValue,
        ));
        item.append(nodeName);

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
        const div = document.createElement('div');

        const removeFormButton = document.createElement('button');
        removeFormButton.innerText = 'Delete'; // TODO - Translation
        removeFormButton.className = 'btn btn__secondry';

        div.append(removeFormButton);
        item.append(div);

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
