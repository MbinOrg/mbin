import { Controller } from '@hotwired/stimulus';
import router from '../utils/routing';

const KBIN_SUBSCRIPTIONS_IN_SEPARATE_SIDEBAR = 'kbin_subscriptions_in_separate_sidebar';
const KBIN_SUBSCRIPTIONS_SIDEBARS_SAME_SIDE = 'kbin_subscriptions_sidebars_same_side';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        sidebarPosition: String,
    };

    generateSettingsRoute(key, value) {
        return router().generate('theme_settings', { key, value });
    }

    async reattach() {
        await window.fetch(
            this.generateSettingsRoute(KBIN_SUBSCRIPTIONS_IN_SEPARATE_SIDEBAR, 'false'),
        );
        window.location.reload();
    }

    async popLeft() {
        await window.fetch(
            this.generateSettingsRoute(KBIN_SUBSCRIPTIONS_IN_SEPARATE_SIDEBAR, 'true'),
        );
        await window.fetch(
            this.generateSettingsRoute(
                KBIN_SUBSCRIPTIONS_SIDEBARS_SAME_SIDE,
                ('left' === this.sidebarPositionValue ? 'true' : 'false'),
            ),
        );
        window.location.reload();
    }

    async popRight() {
        await window.fetch(
            this.generateSettingsRoute(KBIN_SUBSCRIPTIONS_IN_SEPARATE_SIDEBAR, 'true'),
        );
        await window.fetch(
            this.generateSettingsRoute(
                KBIN_SUBSCRIPTIONS_SIDEBARS_SAME_SIDE,
                ('left' !== this.sidebarPositionValue ? 'true' : 'false'),
            ),
        );
        window.location.reload();
    }
}
