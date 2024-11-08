importScripts(
    'https://storage.googleapis.com/workbox-cdn/releases/6.5.4/workbox-sw.js'
);

console.log("ServiceWorker is running")
self.addEventListener("push", (e) => {
    /** @var {PushMessageData} data */
    const data = e.data
    const json = data.json()
    console.log("received push notification", json)
    const promiseChain = self.registration.showNotification(json.title, { body: json.message, data: json, icon: json.avatarUrl ?? json.iconUrl, badge: json.badgeUrl })

    e.waitUntil(promiseChain);
})

self.addEventListener("notificationclick", (/** @var {NotificationEvent} event */ event) => {
    let n = event.notification
    console.log("clicked on notification", event)
    if (!event.action || event.action === "") {
        const url = n.data.actionUrl
        if (url) {
            const promiseChain = self.clients.matchAll({type: "window"})
                .then((clientList) => {
                    if (clientList.length > 0) {
                        const client = clientList.at(0)
                        console.log("got a windowclient", client)
                        return client.navigate(url)
                            .then(client => {
                                console.log("navigated to url", url)
                                if (client && client.focus) {
                                    console.log("focusing to client")
                                    return client.focus();
                                }
                            })
                    }
                    if (self.clients.openWindow) {
                        console.log("opening new window")
                        return self.clients.openWindow(url);
                    }
                })

            event.waitUntil(promiseChain)
        }
    }
    n.close()
})

self.addEventListener('install', (event) => {
    console.log('Inside the install handler:', event);
    event.waitUntil(self.skipWaiting())
});

self.addEventListener('activate', (event) => {
    console.log('Inside the activate handler:', event);
});


// This is your Service Worker, you can put any of your custom Service Worker
// code in this file, above the `precacheAndRoute` line.

workbox.precaching.precacheAndRoute(self.__WB_MANIFEST || []);
