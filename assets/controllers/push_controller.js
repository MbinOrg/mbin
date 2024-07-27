import { Controller } from '@hotwired/stimulus';
import {fetch, ThrowResponseIfNotOk} from "../utils/http";

export default class extends Controller {

    applicationServerPublicKey;

    connect() {
        this.applicationServerPublicKey = this.element.dataset.applicationServerPublicKey
        console.log("got application server public key", this.applicationServerPublicKey)
        window.navigator.serviceWorker.getRegistration()
            .then((registration) => {
                console.log("got service worker registration", registration)
                return registration?.pushManager.getSubscription()
            })
            .then(pushSubscription => {
                this.updateButtonVisibility(pushSubscription)
            })
            .catch((error) => {
                console.log("there was an error in the connect method", error)
                this.element.style.display = "none"
            })

        if (!('serviceWorker' in navigator)) {
            // Service Worker isn't supported on this browser, disable or hide UI.
            this.element.style.display = "none"
        }

        if (!('PushManager' in window)) {
            // Push isn't supported on this browser, disable or hide UI.
            this.element.style.display = "none"
        }
    }

    updateButtonVisibility(pushSubscription) {
        let registerBtn = document.getElementById("push-subscription-register-btn")
        let unregisterBtn = document.getElementById("push-subscription-unregister-btn")
        let testBtn = document.getElementById("push-subscription-test-btn")
        if (pushSubscription) {
            registerBtn.style.display = "none"
            testBtn.style.display = ""
            unregisterBtn.style.display = ""
        } else {
            registerBtn.style.display = ""
            testBtn.style.display = "none"
            unregisterBtn.style.display = "none"
        }
    }

    async retry(event) {

    }

    async show(event) {

    }

    askPermission() {
        return new Promise(function(resolve, reject) {
            const permissionResult = Notification.requestPermission(function(result) {
                resolve(result);
            });

            if (permissionResult) {
                permissionResult.then(resolve, reject);
            }
        })
            .then(function(permissionResult) {
                if (permissionResult !== 'granted') {
                    throw new Error('We weren\'t granted permission.');
                }
            });
    }

    registerPush() {
        this.askPermission()
            .then(() => window.navigator.serviceWorker.getRegistration())
            .then(registration => {
                console.log("got service worker registration:", registration)
                const subscribeOptions = {
                    userVisibleOnly: true,
                    applicationServerKey: this.applicationServerPublicKey,
                }

                return registration.pushManager.subscribe(subscribeOptions)
            })
            .then(pushSubscription => {
                console.log("Received PushSubscription: ", JSON.stringify(pushSubscription))
                this.updateButtonVisibility(pushSubscription)
                const jsonSub = pushSubscription.toJSON()
                console.log("registering push to server")
                let payload = {
                    endpoint: pushSubscription.endpoint,
                    deviceKey: this.getDeviceKey(),
                    contentPublicKey: jsonSub.keys["p256dh"],
                    serverKey: jsonSub.keys["auth"],
                }
                return fetch('/ajax/register_push', {
                    method: "post",
                    body: JSON.stringify(payload),
                    headers: {"Content-Type": "application/json"}
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw response
                }
                return response.json()
            })
            .then(data => {

            })
            .catch(error => {
                console.error(error)
                this.unregisterPush()
            })
    }

    unregisterPush() {
        window.navigator.serviceWorker.getRegistration()
            .then((registration) => registration?.pushManager.getSubscription())
            .then(pushSubscription => pushSubscription.unsubscribe())
            .then((successful) => {
                if (successful) {
                    console.log("removed push subscription")
                    this.updateButtonVisibility(null)
                    let payload = {
                        deviceKey: this.getDeviceKey(),
                    }
                    fetch('/ajax/unregister_push', {
                        method: "post",
                        body: JSON.stringify(payload),
                        headers: {"Content-Type": "application/json"}
                    })
                        .then(ThrowResponseIfNotOk)
                        .then(data => {
                        })
                        .catch(error => console.error(error))
                }
            })
    }

    testPush() {
        fetch('/ajax/test_push', { method: "post", body: JSON.stringify({deviceKey: this.getDeviceKey()}), headers: { "Content-Type": "application/json" } })
            .then(response => {
                if (!response.ok) {
                    throw response
                }
                return response.json()
            })
            .then(data => { })
            .catch(error => console.error(error))
    }

    storageKeyPushSubscriptionDevice = "push_subscription_device_key"

    getDeviceKey() {
        if (localStorage.getItem(this.storageKeyPushSubscriptionDevice)) {
            return localStorage.getItem(this.storageKeyPushSubscriptionDevice)
        }
        const subscriptionKey = crypto.randomUUID()
        localStorage.setItem(this.storageKeyPushSubscriptionDevice, subscriptionKey)
        return subscriptionKey
    }
}
