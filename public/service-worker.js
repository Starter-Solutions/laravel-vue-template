self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(request).catch(
            () =>
                new Response(
                    `<!doctype html>
<html lang="en">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Connection unavailable</title>
    <style>
        body { max-width: 32rem; margin: 12vh auto; padding: 0 1.5rem; font: 1rem/1.5 system-ui, sans-serif; }
    </style>
    <main>
        <h1>Connection unavailable</h1>
        <p>Check your internet connection and try again.</p>
        <a href="">Try again</a>
    </main>
</html>`,
                    {
                        status: 503,
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                    },
                ),
        ),
    );
});
