import {ApplicationController, useDebounce} from 'stimulus-use'

/* stimulusFetch: 'lazy' */
export default class extends ApplicationController {
    static values = {
        loading: Boolean,
    }

    static debounces = ['mention']

    connect() {
        useDebounce(this, {wait: 800})
        this.handleDropdowns();
        this.handleOptionsBarScroll();
    }

    handleDropdowns() {
        this.element.querySelectorAll('.dropdown > a').forEach((dropdown) => {
            dropdown.addEventListener('click', (event) => {
                event.preventDefault();
            });
        });
    }

    handleOptionsBarScroll() {
        const container = document.getElementById('options');
        if (container) {
            const containerWidth = container.clientWidth;
            const area = container.querySelector('.options__main');

            if (null === area) {
                return;
            }

            const areaWidth = area.scrollWidth;

            if (areaWidth > containerWidth && !area.nextElementSibling) {
                container.insertAdjacentHTML('beforeend', '<menu class="scroll"><li class="scroll-left"><i class="fa-solid fa-circle-left"></i></li><li class="scroll-right"><i class="fa-solid fa-circle-right"></i></li></menu>');

                const scrollLeft = container.querySelector('.scroll-left');
                const scrollRight = container.querySelector('.scroll-right');
                const scrollArea = container.querySelector('.options__main');

                scrollRight.addEventListener('click', () => {
                    scrollArea.scrollLeft += 100;
                });
                scrollLeft.addEventListener('click', () => {
                    scrollArea.scrollLeft -= 100;
                });
            }
        }
    }

    /**
     * Handles interaction with the mobile nav button, opening the sidebar
     * @param {*} e
     */
    handleNavToggleClick(e) {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }

    changeLang(event) {
        window.location.href = '/settings/theme/kbin_lang/' + event.target.value;
    }
}
