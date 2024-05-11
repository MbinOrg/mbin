export default function subscribe(endpoint, topics, cb) {
    if (!endpoint) {
        return null;
    }

    const url = new URL(endpoint);

    topics.forEach((topic) => {
        url.searchParams.append('topic', topic);
    });

    const eventSource = new EventSource(url);
    eventSource.onmessage = (e) => cb(e);

    return eventSource;
}
