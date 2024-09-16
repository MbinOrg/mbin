import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['relatedContainer'];

    static values = {
        index: Number,
        label: String,
        value: String,
        deleteIcon: String,
    };

    connect() {
        const container = this.element;
        container
            .querySelectorAll('.related-link-row')
            .forEach((item) => {
                this.#addButtonDeleteLink(item);
            });
    }

    addRelatedElement() {
        const rowNode = document.createElement('div');
        rowNode.className = 'related-link-row';

        const nodeLabel = this.#htmlToNode(this.labelValue.replace(
            /__name__/g,
            this.indexValue,
        ));
        rowNode.appendChild(nodeLabel);

        const nodeValue = this.#htmlToNode(this.valueValue.replace(
            /__name__/g,
            this.indexValue,
        ));
        rowNode.appendChild(nodeValue);

        this.#addButtonDeleteLink(rowNode);

        this.relatedContainerTarget.appendChild(rowNode);
        this.indexValue++;
    }

    #addButtonDeleteLink(item) {
        const removeFormButton = document.createElement('button');
        removeFormButton.innerHTML = this.deleteIconValue;
        removeFormButton.className = 'btn btn__secondary';

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
