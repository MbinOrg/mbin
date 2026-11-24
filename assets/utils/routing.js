import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

import routes from '../../public/js/fos_js_routes.json';

export default function router() {
    Routing.setRoutingData(routes);

    return Routing;
}
