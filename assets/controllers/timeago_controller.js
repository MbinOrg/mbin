import { Controller } from '@hotwired/stimulus';
/* eslint-disable camelcase -- zh_TW is a specific identifier */
// eslint-disable-next-line -- grouping timeago imports here is more readable than properly sorting
import * as timeago from 'timeago.js';
import bg from 'timeago.js/lib/lang/bg';
import de from 'timeago.js/lib/lang/de';
import el from 'timeago.js/lib/lang/el';
import en from 'timeago.js/lib/lang/en_US';
import es from 'timeago.js/lib/lang/es';
import fr from 'timeago.js/lib/lang/fr';
import it from 'timeago.js/lib/lang/it';
import ja from 'timeago.js/lib/lang/ja';
import nl from 'timeago.js/lib/lang/nl';
import pl from 'timeago.js/lib/lang/pl';
import pt_BR from 'timeago.js/lib/lang/pt_BR';
import ru from 'timeago.js/lib/lang/ru';
import tr from 'timeago.js/lib/lang/tr';
import uk from 'timeago.js/lib/lang/uk';
import zh_TW from 'timeago.js/lib/lang/zh_TW';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        const elems = document.querySelectorAll('.timeago');

        if (!elems.length) {
            return;
        }

        const lang = document.documentElement.lang;
        const languages = { bg, de, el, en, es, fr, it, ja, nl, pl, pt_BR, ru, tr, uk, zh_TW };

        if (languages[lang]) {
            timeago.register(lang, languages[lang]);
            timeago.render(elems, lang);
        } else {
            timeago.render(elems);
        }
    }
}
