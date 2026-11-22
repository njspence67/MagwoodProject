<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Magwood API</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; color: #21392d; }
        a { color: #246040; } select, input, textarea, button { font: inherit; padding: .6rem; box-sizing: border-box; }
        label { display: block; margin-top: 1rem; } input, textarea, select { width: 100%; }
        textarea { min-height: 13rem; font-family: monospace; } button { margin-top: 1rem; cursor: pointer; }
        pre { white-space: pre-wrap; overflow-wrap: anywhere; padding: 1rem; background: #f1f5f2; }
        summary { cursor: pointer; } .notice { border-left: 4px solid #a46a15; padding-left: 1rem; }
    </style>
</head>
<body>
<h1>Magwood API</h1>
<p class="notice">Development demo with illustrative records. Requests below can change demo data. Authentication and production database integration are not yet implemented.</p>
<p><a href="/openapi.json">Download the OpenAPI contract</a>. Dates use YYYY-MM-DD; tags are labels, not animal IDs.</p>
<form id="request-form">
    <label for="endpoint">Endpoint</label><select id="endpoint"></select>
    <label for="path">Request path (replace IDs and add query parameters here)</label>
    <input id="path" value="/api/v1/animals" required>
    <label for="payload">JSON body for POST requests</label>
    <textarea id="payload" spellcheck="false">{"type":"note","animal_id":"animal-020","occurred_on":"2026-11-20","notes":"Illustrative observation"}</textarea>
    <button type="submit">Send request</button>
</form>
<h2>Response</h2><pre id="result" aria-live="polite">Choose an endpoint to try it.</pre>
<h2>Request and response schemas</h2><div id="schemas"></div>
<script>
    const endpoint = document.querySelector('#endpoint');
    const path = document.querySelector('#path');
    const result = document.querySelector('#result');
    const payload = document.querySelector('#payload');
    fetch('/openapi.json').then(response => response.json()).then(schema => {
        for (const [route, operations] of Object.entries(schema.paths)) {
            for (const method of Object.keys(operations).filter(method => ['get', 'post'].includes(method))) {
                const option = document.createElement('option');
                option.value = JSON.stringify({method: method.toUpperCase(), route});
                option.textContent = `${method.toUpperCase()} ${route}`;
                endpoint.append(option);
            }
        }
        for (const [name, definition] of Object.entries(schema.components.schemas)) {
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            const pre = document.createElement('pre');
            summary.textContent = name;
            pre.textContent = JSON.stringify(definition, null, 2);
            details.append(summary, pre);
            document.querySelector('#schemas').append(details);
        }
        endpoint.value = [...endpoint.options].find(option => JSON.parse(option.value).route === '/api/v1/animals').value;
        endpoint.dispatchEvent(new Event('change'));
    }).catch(() => { result.textContent = 'Could not load the API contract.'; });
    endpoint.addEventListener('change', () => {
        const selection = JSON.parse(endpoint.value);
        path.value = selection.route;
        payload.disabled = selection.method !== 'POST';
        if (selection.route === '/api/v1/animals' && selection.method === 'POST') {
            payload.value = JSON.stringify({kind: 'calf', description: 'Illustrative calf'}, null, 2);
        } else if (selection.method === 'POST') {
            payload.value = JSON.stringify({type: 'note', animal_id: 'animal-020', occurred_on: '2026-11-20', notes: 'Illustrative observation'}, null, 2);
        }
    });
    document.querySelector('#request-form').addEventListener('submit', async event => {
        event.preventDefault();
        try {
            const {method} = JSON.parse(endpoint.value);
            const url = new URL(path.value, location.origin);
            if (url.origin !== location.origin || !url.pathname.startsWith('/')) throw new Error('Use a path on this API server.');
            const options = {method, headers: {Accept: 'application/json'}};
            if (method === 'POST') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(JSON.parse(payload.value));
            }
            const response = await fetch(url, options);
            result.textContent = `${response.status} ${response.statusText}\n${JSON.stringify(await response.json(), null, 2)}`;
        } catch (error) { result.textContent = error.message; }
    });
</script>
</body>
</html>
