/* global Fingerprint */
(function () {
    const NativeBridge = {
        activeController: null,
        bridge() {
            return window.AndroidFingerprintBridge || window.AndroidFingerprint || window.BEASFingerprintBridge || null;
        },
        status() {
            return this.statusInfo().message;
        },
        statusInfo() {
            const bridge = this.bridge();
            if (!bridge) {
                return {
                    code: 'UNAVAILABLE',
                    message: 'Android fingerprint bridge is not available.',
                };
            }
            let raw = 'READY: Android fingerprint bridge is ready.';
            if (typeof bridge.getFingerprintBridgeStatus === 'function') {
                raw = String(bridge.getFingerprintBridgeStatus() || 'UNKNOWN: Android fingerprint bridge is not ready.');
            }
            const match = raw.match(/^([A-Z_]+)\s*:\s*(.*)$/);
            return {
                code: match ? match[1] : 'UNKNOWN',
                message: match ? match[2] : raw,
                raw,
            };
        },
        available() {
            const bridge = this.bridge();
            return !!bridge;
        },
        ready() {
            const bridge = this.bridge();
            if (!bridge || typeof bridge.startFingerprintCapture !== 'function') {
                return false;
            }
            if (typeof bridge.isFingerprintBridgeReady === 'function') {
                try {
                    return bridge.isFingerprintBridgeReady() === true || this.statusInfo().code === 'READY';
                } catch (_) {
                    return false;
                }
            }
            return this.statusInfo().code === 'READY';
        },
        requestPermission() {
            const bridge = this.bridge();
            if (!bridge || typeof bridge.requestUsbPermission !== 'function') {
                return {
                    code: 'UNSUPPORTED',
                    message: 'Android bridge cannot request USB permission from the web page.',
                };
            }
            const raw = String(bridge.requestUsbPermission() || 'UNKNOWN: USB permission request did not return a status.');
            const match = raw.match(/^([A-Z_]+)\s*:\s*(.*)$/);
            return {
                code: match ? match[1] : 'UNKNOWN',
                message: match ? match[2] : raw,
                raw,
            };
        },
        setController(controller) {
            this.activeController = controller;
        },
        clearController(controller) {
            if (this.activeController === controller) {
                this.activeController = null;
            }
        },
        onSample(payloadJson) {
            const controller = this.activeController;
            if (controller) {
                controller._handleNativeSample(payloadJson);
            }
        },
        onError(message) {
            const controller = this.activeController;
            if (controller) {
                controller._handleNativeError(message);
            }
        },
        onStatus(state, message) {
            const controller = this.activeController;
            if (controller) {
                controller._handleNativeStatus(state, message);
            }
        },
    };

    class ReaderController {
        constructor(options = {}) {
            this.port = Number(options.port || 52181);
            this.debug = options.debug === true;
            this.nativeMode = NativeBridge.available();
            this._resetWebSdkSession();
            this.api = this.nativeMode ? null : new Fingerprint.WebApi({
                debug: this.debug,
                port: this.port,
                reconnectAlways: false,
            });
            this.readers = [];
            this.ready = this.nativeMode;
            this.capturing = false;
            this.lastQuality = 0;
            this.lastQualityCode = 0;
            this.pendingCapture = null;
            this.statusHandler = null;
            this.readersHandler = null;
            this.sampleHandler = null;
            this.errorHandler = null;
            this._bindEvents();
        }

        _resetWebSdkSession() {
            try {
                sessionStorage.removeItem('websdk');
                sessionStorage.removeItem('websdk.sessionId');
            } catch (_) {
                // Ignore storage failures and fall back to the SDK defaults.
            }
        }

        _bindEvents() {
            if (this.nativeMode) {
                this.ready = true;
                this._emitStatus('ready', 'Android fingerprint bridge ready');
                return;
            }

            this.api.onCommunicationFailed = event => {
                this.ready = false;
                this.capturing = false;
                this._diagnoseConnectionFailure(event?.error || new Error('Communication failure.'))
                    .then(error => {
                        this._rejectPending(error);
                        this._emitError(error);
                    })
                    .catch(() => {
                        const fallback = event?.error || new Error('Communication failure.');
                        this._rejectPending(fallback);
                        this._emitError(fallback);
                    });
            };

            this.api.onDeviceConnected = () => {
                this.refreshReaders().catch(() => {});
            };

            this.api.onDeviceDisconnected = () => {
                this.ready = false;
                this.capturing = false;
                this.refreshReaders().catch(() => {});
            };

            this.api.onAcquisitionStarted = event => {
                this.capturing = true;
                this.ready = true;
                this._emitStatus('ready', 'Fingerprint acquisition started' + (event?.deviceUid ? ' on ' + event.deviceUid : ''));
            };

            this.api.onAcquisitionStopped = () => {
                this.capturing = false;
                this._emitStatus('idle', 'Fingerprint acquisition stopped');
            };

            this.api.onQualityReported = event => {
                const rawQuality = Number(event?.quality ?? 0);
                this.lastQualityCode = rawQuality;
                this.lastQuality = this._qualityCodeToPercent(rawQuality);
                this._emitStatus('scanning', 'Fingerprint quality: ' + this.lastQuality + '%');
            };

            this.api.onSamplesAcquired = event => {
                this._handleSamples(event);
            };

            this.api.onErrorOccurred = event => {
                const error = event?.error instanceof Error
                    ? event.error
                    : new Error(event?.error?.message || event?.error || 'Fingerprint error');
                this.ready = false;
                this.capturing = false;
                this._rejectPending(error);
                this._emitError(error);
            };
        }

        setStatusHandler(handler) {
            this.statusHandler = handler;
        }

        setReadersHandler(handler) {
            this.readersHandler = handler;
        }

        setSampleHandler(handler) {
            this.sampleHandler = handler;
        }

        setErrorHandler(handler) {
            this.errorHandler = handler;
        }

        async refreshReaders() {
            if (this.nativeMode) {
                const status = NativeBridge.statusInfo();
                if (status.code === 'READY' && NativeBridge.ready()) {
                    this.readers = [{ deviceUid: 'android-otg', label: 'Android OTG fingerprint bridge' }];
                    this.ready = true;
                    this._emitReaders(this.readers);
                    this._emitStatus('ready', 'Android U.are.U 4500 ready');
                    return this.readers;
                }

                if (status.code === 'PERMISSION_REQUIRED') {
                    NativeBridge.setController(this);
                    this.ready = false;
                    this._emitReaders([]);
                    const permission = NativeBridge.requestPermission();
                    const message = permission.code === 'REQUESTED'
                        ? 'Android USB permission required for U.are.U 4500. Approve the permission dialog, then retry.'
                        : permission.message;
                    this._emitStatus('idle', message);
                    throw new Error(message);
                }

                this.ready = false;
                this._emitReaders([]);
                throw new Error(status.message);
            }

            let readers;
            try {
                readers = await this.api.enumerateDevices();
            } catch (error) {
                throw await this._diagnoseConnectionFailure(error);
            }
            this.readers = Array.isArray(readers) ? readers : [];
            this.ready = this.readers.length > 0;
            this._emitReaders(this.readers);
            this._emitStatus(this.ready ? 'ready' : 'idle', this.ready
                ? (this.readers.length + ' fingerprint reader(s) available')
                : 'Connect a fingerprint reader');
            return this.readers;
        }

        async _diagnoseConnectionFailure(error) {
            const baseMessage = error?.message || String(error || 'Fingerprint communication failed.');
            const probe = await this._probeLocalBridge();

            if (/2147024846|request is not supported/i.test(baseMessage)) {
                return new Error(
                    'HID Authentication Service responded, but the request is not supported. ' +
                    'The client is installed, but the local bridge endpoint is rejecting this operation. ' +
                    'Restart the HID service or reinstall the HID client.'
                );
            }

            if (!probe.reachable) {
                return new Error(
                    'Browser cannot reach local bridge on port ' + this.port + '. ' +
                    'HID client may not be installed, or the HID Authentication Service is not running.'
                );
            }

            if (/WebSocket connection failed|Communication failure|connection failed/i.test(baseMessage)) {
                return new Error(
                    'HID client is installed, but the local bridge on port ' + this.port + ' is not responding. ' +
                    'Restart the HID Authentication Service and try again.'
                );
            }

            return new Error(baseMessage);
        }

        async _probeLocalBridge() {
            const url = 'https://127.0.0.1:' + this.port + '/get_connection';
            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timeoutId = controller ? setTimeout(() => controller.abort(), 1500) : null;

            try {
                await fetch(url, {
                    method: 'GET',
                    mode: 'no-cors',
                    cache: 'no-store',
                    signal: controller ? controller.signal : undefined,
                });
                return { reachable: true };
            } catch (error) {
                return { reachable: false, error };
            } finally {
                if (timeoutId) clearTimeout(timeoutId);
            }
        }

        _captureOptions(input) {
            if (input && typeof input === 'object' && !Array.isArray(input)) {
                return {
                    sampleFormat: input.sampleFormat || Fingerprint.SampleFormat.PngImage,
                    finger: input.finger || '',
                };
            }
            return {
                sampleFormat: input || Fingerprint.SampleFormat.PngImage,
                finger: '',
            };
        }

        async start(options = Fingerprint.SampleFormat.PngImage) {
            const captureOptions = this._captureOptions(options);
            if (this.capturing) {
                return true;
            }

            if (this.nativeMode) {
                NativeBridge.setController(this);
                const bridge = NativeBridge.bridge();
                if (!NativeBridge.ready()) {
                    await this.refreshReaders();
                }
                const payload = JSON.stringify(captureOptions);
                const result = bridge.startFingerprintCapture(payload);
                if (typeof result === 'string' && result.startsWith('ERROR:')) {
                    throw new Error(result.slice(6));
                }
                this.capturing = true;
                this.ready = true;
                this._emitStatus('scanning', 'Waiting for fingerprint...');
                return true;
            }

            if (!this.ready) {
                await this.refreshReaders();
            }

            await this.api.startAcquisition(captureOptions.sampleFormat);
            this.capturing = true;
            this.ready = true;
            this._emitStatus('scanning', 'Waiting for fingerprint...');
            return true;
        }

        async acquire(options = Fingerprint.SampleFormat.PngImage, timeoutMs = 15000) {
            const captureOptions = this._captureOptions(options);
            if (this.nativeMode) {
                if (this.pendingCapture) {
                    return this.pendingCapture.promise;
                }

                let resolveCapture;
                let rejectCapture;
                const promise = new Promise((resolve, reject) => {
                    resolveCapture = resolve;
                    rejectCapture = reject;
                });

                const timer = setTimeout(() => {
                    this._rejectPending(new Error('Capture timed out after ' + timeoutMs + ' ms.'));
                }, timeoutMs);

                this.pendingCapture = {
                    promise,
                    resolve: resolveCapture,
                    reject: rejectCapture,
                    timer,
                };

                try {
                    await this.start(captureOptions);
                } catch (error) {
                    this._rejectPending(error);
                    throw error;
                }

                return promise;
            }

            if (this.pendingCapture) {
                return this.pendingCapture.promise;
            }

            let resolveCapture;
            let rejectCapture;
            const promise = new Promise((resolve, reject) => {
                resolveCapture = resolve;
                rejectCapture = reject;
            });

            const timer = setTimeout(() => {
                this._rejectPending(new Error('Capture timed out after ' + timeoutMs + ' ms.'));
            }, timeoutMs);

            this.pendingCapture = {
                promise,
                resolve: resolveCapture,
                reject: rejectCapture,
                timer,
            };

            try {
                await this.api.startAcquisition(captureOptions.sampleFormat);
                this.capturing = true;
            } catch (error) {
                this._rejectPending(error);
                throw error;
            }

            return promise;
        }

        async stop() {
            if (this.nativeMode) {
                try {
                    const bridge = NativeBridge.bridge();
                    if (bridge && typeof bridge.stopFingerprintCapture === 'function') {
                        bridge.stopFingerprintCapture();
                    }
                } catch (_) {
                    // Ignore stop errors; the native bridge may already be idle.
                } finally {
                    this.capturing = false;
                    NativeBridge.clearController(this);
                }
                return;
            }

            try {
                await this.api.stopAcquisition();
            } catch (_) {
                // Ignore stop errors; the device may already be disconnected.
            } finally {
                this.capturing = false;
            }
        }

        _handleSamples(event) {
            const samples = this._parseSamples(event?.samples);
            const normalizedSamples = samples.map(sample => this._normalizeSample(sample));
            const sample = normalizedSamples[0] || '';
            const payload = {
                deviceUid: event?.deviceUid || '',
                sampleFormat: event?.sampleFormat || null,
                samples: normalizedSamples,
                sample: sample,
                quality: this.lastQuality || 0,
                qualityCode: this.lastQualityCode || 0,
            };

            if (this.sampleHandler) {
                try {
                    this.sampleHandler(payload);
                } catch (error) {
                    this._emitError(error);
                }
            }

            if (this.pendingCapture) {
                const pending = this.pendingCapture;
                this.pendingCapture = null;
                clearTimeout(pending.timer);
                pending.resolve(payload);
                this.stop();
            }
        }

        _handleNativeSample(payloadJson) {
            let payload = payloadJson;
            if (typeof payloadJson === 'string') {
                try {
                    payload = JSON.parse(payloadJson);
                } catch (_) {
                    payload = { sample: payloadJson };
                }
            }

            const sample = payload?.sample || payload?.sampleData || '';
            const normalized = this._normalizeSample(sample);
            const normalizedPayload = {
                deviceUid: payload?.deviceUid || 'android-otg',
                sampleFormat: payload?.sampleFormat || Fingerprint.SampleFormat.PngImage,
                samples: [normalized],
                sample: normalized,
                quality: Number(payload?.quality ?? 100),
                qualityCode: Number(payload?.qualityCode ?? 0),
            };

            this.capturing = false;
            this.ready = true;

            if (this.sampleHandler) {
                try {
                    this.sampleHandler(normalizedPayload);
                } catch (error) {
                    this._emitError(error);
                }
            }

            if (this.pendingCapture) {
                const pending = this.pendingCapture;
                this.pendingCapture = null;
                clearTimeout(pending.timer);
                pending.resolve(normalizedPayload);
            }

            this._emitStatus('ready', 'Android fingerprint capture complete');
            NativeBridge.clearController(this);
        }

        _handleNativeError(message) {
            const error = message instanceof Error ? message : new Error(String(message || 'Android fingerprint bridge error'));
            this.capturing = false;
            this.ready = false;
            this._rejectPending(error);
            this._emitError(error);
            NativeBridge.clearController(this);
        }

        _handleNativeStatus(state, message) {
            const normalizedState = typeof state === 'string' && state
                ? state.toLowerCase()
                : 'info';
            if (normalizedState) {
                this.ready = normalizedState === 'ready' || normalizedState === 'scanning';
                this.capturing = normalizedState === 'scanning';
            }
            if (message) {
                this._emitStatus(normalizedState, message);
            }
        }

        _parseSamples(rawSamples) {
            if (Array.isArray(rawSamples)) return rawSamples;
            if (typeof rawSamples !== 'string' || !rawSamples.trim()) return [];
            try {
                const parsed = JSON.parse(rawSamples);
                return Array.isArray(parsed) ? parsed : [parsed];
            } catch (_) {
                return [rawSamples];
            }
        }

        _normalizeSample(sample) {
            if (typeof sample !== 'string' || !sample) return '';
            try {
                return btoa(Fingerprint.b64UrlToUtf8(sample));
            } catch (_) {
                return sample;
            }
        }

        _qualityCodeToPercent(value) {
            const quality = Number(value);
            if (!Number.isFinite(quality)) return 0;
            if (quality > 24) {
                return Math.max(0, Math.min(100, Math.round(quality)));
            }
            return Math.max(0, Math.min(100, Math.round(100 - (quality / 24) * 100)));
        }

        _rejectPending(error) {
            if (!this.pendingCapture) return;
            const pending = this.pendingCapture;
            this.pendingCapture = null;
            clearTimeout(pending.timer);
            pending.reject(error instanceof Error ? error : new Error(String(error || 'Fingerprint error')));
            this.capturing = false;
        }

        _emitStatus(state, message) {
            if (this.statusHandler) this.statusHandler(state, message);
        }

        _emitReaders(readers) {
            if (this.readersHandler) this.readersHandler(readers);
        }

        _emitError(error) {
            if (this.errorHandler) this.errorHandler(error instanceof Error ? error : new Error(String(error || 'Fingerprint error')));
        }
    }

    window.BeasFingerprint = {
        ReaderController,
        NativeBridge,
        onNativeFingerprintSample(payloadJson) {
            NativeBridge.onSample(payloadJson);
        },
        onNativeFingerprintError(message) {
            NativeBridge.onError(message);
        },
        onNativeFingerprintStatus(state, message) {
            NativeBridge.onStatus(state, message);
        },
    };
})();
