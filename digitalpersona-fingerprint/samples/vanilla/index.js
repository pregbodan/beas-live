// In vanilla JS, the `@digitalpersona/card` and `@digitalpersona/websdk` are
// imported using the `<script>` tag, and the `Card` and `WebSdk` objects are
// available as global variables. Typings are available via the `<reference>`
// triple-slash directive (https://www.typescriptlang.org/docs/handbook/triple-slash-directives.html)

/// <reference types="@digitalpersona/websdk" />
/// <reference types="@digitalpersona/fingerprint" />

// Little HTML helper
const $ = (selector, root) => typeof selector === "string" ?
    (root ?? document).querySelector(selector) :
    selector;

// HTML elements
const startButton = $("#start");
const stopButton  = $("#stop");

const api = new Fingerprint.WebApi({
    debug: true,
});
api.onCommunicationFailed = onCommunicationFailed.bind(this);
api.onAcquisitionStarted = onAquisitionStarted.bind(this);
api.onAcquisitionStopped = onAquisitionStopped.bind(this);
api.onSamplesAcquired = onSamplesAcquired.bind(this);
api.onQualityReported = onQualityReported.bind(this);
api.onErrorOccurred = onErrorOccurred.bind(this);
api.onDeviceConnected = onDeviceConnected.bind(this);
api.onDeviceDisconnected = onDeviceDisconnected.bind(this);

// State variables
var capturing = false;

startButton.onclick = startCapture;
stopButton.onclick = stopCapture;
window.onload = startCapture;

// Reader event handlers and status updates

async function onDeviceConnected(event) {
    console.log(`Reader ${event.deviceUid} is connected`);
    await refreshReadersView();
}

async function onDeviceDisconnected(event) {
    console.log(`Reader ${event.deviceUid} is disconnected`);
    await refreshReadersView();
}

async function refreshReadersView() {
    try {
        const readersList = $("#readers");
        const readers = await api.enumerateDevices();
        clearItems(readersList);
        for (const reader of readers) {
            addItem(readersList, { name: reader });
        }
    } catch (error) {
        handleError(error);
    }
}

// Aquisition event handlers and status updates

async function onSamplesAcquired(event) {
    handleError();
    console.log(`Sample is aquired on ${event.Reader}`);
    try {
        const reader = event.deviceUid;
        const samples = JSON.parse(event.samples);
        const images = samples.map(sample => `data:image/png;base64,${btoa(Fingerprint.b64UrlToUtf8(sample))}`);

        addItem("#samples",
            {
                time        : (new Date()).toLocaleTimeString(),
                reader,
                sample: samples[0],
                image: images[0],
            },
            item => requestAnimationFrame(() => item.setAttribute("open", ""))
        );

    }
    catch (error) {
        handleError(error);
    }
}

async function onQualityReported(event) {
    console.log(`Quality is reported on ${event.deviceUid}`);
}

async function onAquisitionStarted(event) {
    console.log(`Aquisition is started on ${event.deviceUid}`);
}

async function onAquisitionStopped(event) {
    console.log(`Aquisition is stopped on ${event.deviceUid}`);
}

// API event handlers and status updates

async function onCommunicationFailed(event) {
    handleError(event.error);
}

async function onErrorOccurred(event) {
    handleError(event.error);
}

// Capture control methods and status updates

async function startCapture() {
    if (capturing) return;
    try {
        clearItems("#samples");
        await api.startAcquisition(Fingerprint.SampleFormat.PngImage);
        setCaptureActive(true);
    } catch (error) {
        handleError(error);
    }
}

async function stopCapture() {
    if (!capturing) return;
    try {
        await api.stopAcquisition();
        setCaptureActive(false);
    } catch (error) {
        handleError(error);
    }
}

function setCaptureActive(active) {
    capturing = active;
    $("#captureControl").toggleAttribute("active", active);
}

// Other status methods

function handleError(error) {
    $("#error").innerHTML = error?.message || error?.type || "";
}

// HTML view helpers

async function showDialog(id, defaultValue = {}) {
    return new Promise((resolve) => {
        const dialog = $(id);
        const form = $("*", dialog);
        form.reset();

        dialog.onclose = () => {
            const data = Object.fromEntries(new FormData(form).entries());
            resolve(dialog.returnValue === "ok" ? data : defaultValue);
        }
        dialog.showModal();
    });
}

// Data conversion functions

function hex(str) {
    return Array.from(str)
    .map(c => c.charCodeAt(0).toString(16).padStart(2, '0'))
    .join('');
}

// Data transfer functions

const isControl = el => ["INPUT", "TEXTAREA", "SELECT"].includes(el.tagName);
const isMedia = el => ["IMG", "AUDIO", "VIDEO"].includes(el.tagName);

// Set data to HTML elements, using the `name` attribute as a JSON path
function setData(element, data) {
    for (let child of element.children) {
        if (child.hasAttribute('name')) {
            const jsonPath = child.getAttribute('name');
            let value = jsonPath.split('.').reduce((o, k) => (o || {})[k], data);
            if (typeof value === "object") value = JSON.stringify(value, null, 2);
            if (isControl(child)) {
                child.value = value;
            } else if (isMedia(child)) {
                child.src = value;
            } else {
                child.innerText = value;
            }
        }
        setData(child, data);
    }
}

// Item list functions

// Add an item to the list, using the `item-template` attribute as a template reference
// and the `name` attribute as a JSON path to set data
function addItem(list, itemData, afterInsert) {
    const container = $(list);
    const itemTemplate = $(container.getAttribute("item-template"));
    const node = itemTemplate.content.cloneNode(true);
    setData(node, itemData);
    container.insertBefore(node, container.firstChild);
    afterInsert ? afterInsert(container.firstElementChild) : void(0);
}

function clearItems(list) {
    const container = $(list);
    container.textContent = "";
}
