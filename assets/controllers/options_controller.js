import {Controller, ActionEvent} from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['settings', 'actions'];
    static values = {
        activeTab: String
    }

    connect() {
        const activeTabFragment = window.location.hash;

        if (!activeTabFragment) {
          return;
        }

        if (activeTabFragment !== '#settings') {
          return;
        }

        this.actionsTarget.querySelector(`a[href="${activeTabFragment}"]`).classList.add('active');
        this.activeTabValue = activeTabFragment.substring(1);
    }

    /** @param {ActionEvent} e */
    toggleTab(e) {
        const selectedTab = e.params.tab;

        this.actionsTarget.querySelectorAll('.active').forEach(el => el.classList.remove('active'));

        if (selectedTab === this.activeTabValue) {
            this.activeTabValue = 'none';
        } else {
            this.activeTabValue = selectedTab;

            e.currentTarget.classList.add('active');
        }
    }

    activeTabValueChanged(selectedTab) {
        if (selectedTab === 'none') {
            this.settingsTarget.style.display = 'none';

            return;
        }

        this[`${selectedTab}Target`].style.display = 'block';

        // If you were to need to hide another tab:
        
        //const otherTab = selectedTab === 'settings' ? 'federation' : 'settings';
        //
        //this[`${otherTab}Target`].style.display = 'none';
    }

    closeMobileSidebar() {
        document.getElementById('sidebar').classList.remove('open');
    }

    appearanceReloadRequired(event) {
        event.target.classList.add('spin');
        window.location.reload();
    }
}
