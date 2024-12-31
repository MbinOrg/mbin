import './bootstrap.js';
import './styles/app.scss';
import './utils/popover.js';
import '@github/markdown-toolbar-element';
import { Application } from '@hotwired/stimulus';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js');
    });
}

// start the Stimulus application
Application.start();
