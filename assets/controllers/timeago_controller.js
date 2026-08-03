import { Controller } from '@hotwired/stimulus';
/* eslint-disable camelcase -- zh_TW is a specific identifier */
// eslint-disable-next-line -- grouping timeago imports here is more readable than properly sorting
import * as timeago from 'timeago.js';
import bg from 'timeago.js/lib/lang/bg.js';
import da from 'timeago.js/lib/lang/da.js';
import de from 'timeago.js/lib/lang/de.js';
import el from 'timeago.js/lib/lang/el.js';
import en from 'timeago.js/lib/lang/en_US.js';
import es from 'timeago.js/lib/lang/es.js';
import fr from 'timeago.js/lib/lang/fr.js';
import gl from 'timeago.js/lib/lang/gl.js';
import it from 'timeago.js/lib/lang/it.js';
import ja from 'timeago.js/lib/lang/ja.js';
import nl from 'timeago.js/lib/lang/nl.js';
import pl from 'timeago.js/lib/lang/pl.js';
import pt_BR from 'timeago.js/lib/lang/pt_BR.js';
import ru from 'timeago.js/lib/lang/ru.js';
import tr from 'timeago.js/lib/lang/tr.js';
import uk from 'timeago.js/lib/lang/uk.js';
import zh_TW from 'timeago.js/lib/lang/zh_TW.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        const elems = document.querySelectorAll('.timeago');

        if (!elems.length) {
            return;
        }

        const lang = document.documentElement.lang;
        const languages = {
            bg: bg.default,
            da: da.default,
            de: de.default,
            el: el.default,
            en: en.default,
            es: es.default,
            fr: fr.default,
            gl: gl.default,
            it: it.default,
            ja: ja.default,
            nl: nl.default,
            pl: pl.default,
            pt_BR: pt_BR.default,
            ru: ru.default,
            tr: tr.default,
            uk: uk.default,
            zh_TW: zh_TW.default,
        };
        timeago.register(lang, languages[lang]);
        timeago.render(elems, lang);
    }
}
