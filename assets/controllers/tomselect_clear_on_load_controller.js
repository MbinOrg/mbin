import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    _orgLoad = null;
    _tomselect = null;

    initialize() {
        this._onPreConnect = this._onPreConnect.bind(this);
        this._onConnect = this._onConnect.bind(this);
        this._onSearchLoad = this._onSearchLoad.bind(this);
    }

    connect() {
        this.element.addEventListener('autocomplete:pre-connect', this._onPreConnect);
        this.element.addEventListener('autocomplete:connect', this._onConnect);
    }

    disconnect() {
        this.element.removeEventListener('autocomplete:connect', this._onConnect);
        this.element.removeEventListener('autocomplete:pre-connect', this._onPreConnect);
    }

    _onPreConnect(event) {
        this._orgLoad = event.detail.options.load;
        event.detail.options.load = this._onSearchLoad;
    }

    _onConnect(event) {
        this._tomselect = event.detail.tomSelect;
        this._orgLoad = this._orgLoad.bind(this._tomselect);
    }

    _onSearchLoad(query, callback) {
        this._tomselect.clear();
        this._tomselect.clearActiveItems();
        this._tomselect.clearActiveOption();
        this._tomselect.clearFilter();
        this._tomselect.clearOptions();
        this._tomselect.clearPagination();
        this._tomselect.clearCache();

        this._orgLoad(query, callback);
    }
}
