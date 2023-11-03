export default function
    subscribe(topics, cb) {
    const mercureElem = document.getElementById("mercure-url");
    if (mercureElem) {
        const url = new URL(mercureElem.textContent.trim());

        topics.forEach(topic => {
            url.searchParams.append('topic', topic);
        })

        const eventSource = new EventSource(url);
        eventSource.onmessage = e => cb(e);

        return eventSource;
    }
    return null;
}
