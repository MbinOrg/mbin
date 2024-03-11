import './bootstrap.js';
import {Application} from '@hotwired/stimulus'
import './styles/app.scss';
import './utils/popover';
import '@github/markdown-toolbar-element'

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js');
    });
}

// start the Stimulus application
Application.start()
