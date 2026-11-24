# Push notifications across devices

Follow this guide when changing push settings, device subscriptions, or delivery. See the [README for setup instructions](README.md#pwa-push-setup).

## Settings and permissions

| Setting                                       | Scope                            | Behavior                                                                                                                                                                                                         |
| --------------------------------------------- | -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Allow push notifications for this account** | User account                     | Off by default. Turning it off pauses new sends across all connected devices and keeps their subscriptions. Turning it back on resumes sending to those devices. The switch also works on a disconnected device. |
| **Connect this device**                       | Current browser or installed PWA | On when local notification permission is granted and the current push subscription belongs to the account. Turning it off removes this subscription from the account's delivery list.                            |
| System or browser permission                  | Current browser or installed PWA | Managed by the operating system or browser. If permission is blocked, the app explains where to change it. On iPhone and iPad, open the web app from the Home Screen.                                            |

Web Push subscriptions belong to individual browser or app installations. For this project, that means separate controls for the account preference and the device connection. Both switches save immediately and keep the same accessible name in every state. [web.dev on push subscriptions](https://web.dev/articles/push-notifications-subscribing-a-user), [WebKit on Web Push for iOS and iPadOS](https://webkit.org/blog/13878/), [W3C switch pattern](https://www.w3.org/WAI/ARIA/apg/patterns/switch/).

Ask for permission only when the user turns on **Connect this device** and the browser has no recorded decision. Opening the page, sending a test, and returning to the app can read the current status as needed. New installations and previously disconnected devices stay disconnected until the user connects them. [Chrome guidance on permission prompts](https://developer.chrome.com/docs/lighthouse/best-practices/notification-on-start), [WebKit on user gestures](https://webkit.org/blog/13878/).

## Delivery and feedback

- Check the account preference on the server before sending to the account's active subscriptions. If topic preferences are added, apply them alongside the account preference. Write clear, relevant notifications and keep sensitive information off the lock screen. [Apple notification guidelines](https://developer.apple.com/design/human-interface-guidelines/notifications), [Android notification guidelines](https://developer.android.com/design/ui/mobile/guides/home-screen/notifications).
- **Send test push** attempts to deliver a real push to every connected device on the account, even when triggered from a disconnected device. Report when there are zero subscriptions, how many attempts push services accepted, and how many failed. Whether a notification appears also depends on device connectivity and system settings. [RFC 8030 on push delivery](https://www.rfc-editor.org/rfc/rfc8030.html#section-5), [WebKit on Focus](https://webkit.org/blog/13878/).
- Test sends are limited to five requests per account per minute. Count down the server's retry delay when the limit is reached and disable the test button until the countdown ends. Distinguish errors in the test request from delivery results returned by push services.
- Treat push endpoints and keys as confidential. Scope subscription lookups and updates to the signed-in account. Send endpoints in the request body and keep them out of telemetry. Remove expired subscriptions when the push service reports them during a send. [RFC 8030 on subscription expiry](https://www.rfc-editor.org/rfc/rfc8030.html#section-7.3), [RFC 8030 on logging risks](https://www.rfc-editor.org/rfc/rfc8030.html#section-8.5).
- When checking a connected device, refresh the encryption keys for its existing subscription. Create a subscription only when the user connects the device. [Push API requirements for subscription keys](https://www.w3.org/TR/push-api/#push-subscription).

## Verification checklist

1. With two connected devices and one disconnected browser, turn the account switch off and back on. Verify that the switch works in each installation and that sending pauses and resumes.
2. Disconnect one device. Verify that only its subscription leaves the delivery list and that refreshing, reopening, or returning from system settings leaves it disconnected.
3. Deny or revoke permission and reinstall the PWA. Verify that the device switch reflects the current state and the prompt appears only after the user chooses to connect the device.
4. Send a test from the disconnected browser with account push enabled. Verify that every valid subscription for the account receives a delivery attempt. Cover zero subscriptions, partial success, and expired subscriptions.
