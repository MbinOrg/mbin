import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    abortController;
    previewElement;
    timerId;

    connect() {
        super.connect();
        this.element.addEventListener('keydown', this.handleInput.bind(this));

        const container = document.getElementById('content');
        this.previewElement = document.getElementById('markdown-preview');
        if (!this.previewElement) {
            this.previewElement = document.createElement('div');
            this.previewElement.id = 'markdown-preview';
            this.previewElement.className = 'section markdown-preview';
            this.previewElement.style.display = 'none';
            container.insertAdjacentElement('afterend', this.previewElement);
        }
    }

    handleInput () {
        // fetch the preview if we got an input event and then the user stopped typing for at least a second
        if (this.timerId) {
            window.clearTimeout(this.timerId);
        }
        this.timerId = window.setTimeout(() => {
            this.abortController?.abort();
            this.abortController = new AbortController();
            fetch('/ajax/preview_markdown', {
                signal: this.abortController.signal,
                body: JSON.stringify({ markdown: this.element.value }),
                headers: { 'Content-Type': 'application/json' },
                method: 'post',
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.html) {
                        console.log('got html', data.html);
                        this.previewElement.innerHTML = data.html;
                        this.previewElement.style.display = 'block';
                    } else {
                        alert("Sorry, that didn't work");
                    }
                });
        }, 1000);
    }
}
