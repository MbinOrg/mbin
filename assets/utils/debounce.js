export default function debounce(delay, handler) {
    let timer = 0;
    return function() {
        clearTimeout(timer);
        timer = setTimeout(handler, delay);
    };
}
