///<reference types="@digitalpersona/websdk" />

namespace Fingerprint {

    export function b64UrlTo64(a: string): string {
        if (a.length % 4 == 2) {
            a = a + "==";
        } else {
            if (a.length % 4 == 3) {
                a = a + "=";
            }
        }
        a = a.replace(/-/g, "+");
        a = a.replace(/_/g, "/");
        return a;
    }

    export function b64To64Url(a: string): string {
        a = a.replace(/\=/g, "");
        a = a.replace(/\+/g, "-");
        a = a.replace(/\//g, "_");
        return a;
    }

    export function b64UrlToUtf8(str: string): string {
        return window.atob(b64UrlTo64(str));
    }

    export function strToB64Url(str: string): string {
        return b64To64Url(window.btoa(str));
    }

    export enum DeviceUidType {
        Persistent = 0,
        Volatile = 1
    }

    export enum DeviceModality {
        Unknown = 0,
        Swipe = 1,
        Area = 2,
        AreaMultifinger = 3
    }

    export enum DeviceTechnology {
        Unknown = 0,
        Optical = 1,
        Capacitive = 2,
        Thermal = 3,
        Pressure = 4
    }

    export enum SampleFormat {
        Raw = 1,
        Intermediate = 2,
        Compressed = 3,
        PngImage = 5
    }

    export enum QualityCode {
        Good = 0,
        NoImage = 1,
        TooLight = 2,
        TooDark = 3,
        TooNoisy = 4,
        LowContrast = 5,
        NotEnoughFeatures = 6,
        NotCentered = 7,
        NotAFinger = 8,
        TooHigh = 9,
        TooLow = 10,
        TooLeft = 11,
        TooRight = 12,
        TooStrange = 13,
        TooFast = 14,
        TooSkewed = 15,
        TooShort = 16,
        TooSlow = 17,
        ReverseMotion = 18,
        PressureTooHard = 19,
        PressureTooLight = 20,
        WetFinger = 21,
        FakeFinger = 22,
        TooSmall = 23,
        RotatedTooMuch = 24
    }

    export class Event {
        type: string;
        constructor(type: string) {
            this.type = type;
        }
    }

    export class CommunicationEvent extends Event {
        constructor(type: string) {
            super(type);
        }
    }

    export class CommunicationFailed extends CommunicationEvent {
        constructor() {
            super("CommunicationFailed");
        }
    }

    export class AcquisitionEvent extends Event {
        deviceUid: string;
        constructor(type: string, deviceUid: string) {
            super(type);
            this.deviceUid = deviceUid;
        }
    }

    export class DeviceConnected extends AcquisitionEvent {
        constructor(deviceUid: string) {
            super("DeviceConnected", deviceUid);
        }
    }

    export class DeviceDisconnected extends AcquisitionEvent {
        constructor(deviceUid: string) {
            super("DeviceDisconnected", deviceUid);
        }
    }

    export class SamplesAcquired extends AcquisitionEvent {
        sampleFormat: SampleFormat;
        samples: string;
        constructor(deviceUid: string, sampleFormat: SampleFormat, samples: string) {
            super("SamplesAcquired", deviceUid);
            this.sampleFormat = sampleFormat;
            this.samples = samples;
        }
    }

    export class QualityReported extends AcquisitionEvent {
        quality: QualityCode;
        constructor(deviceUid: string, quality: QualityCode) {
            super("QualityReported", deviceUid);
            this.quality = quality;
        }
    }

    export class ErrorOccurred extends AcquisitionEvent {
        error: number;
        constructor(deviceUid: string, error: number) {
            super("ErrorOccurred", deviceUid);
            this.error = error;
        }
    }

    export class AcquisitionStarted extends AcquisitionEvent {
        constructor(deviceUid: string) {
            super("AcquisitionStarted", deviceUid);
        }
    }
    export class AcquisitionStopped extends AcquisitionEvent {
        constructor(deviceUid: string) {
            super("AcquisitionStopped", deviceUid);
        }
    }

    export interface DeviceInfo {
        DeviceID: string;
        eUidType: DeviceUidType;
        eDeviceModality: DeviceModality;
        eDeviceTech: DeviceTechnology;
    }

    export interface Handler<E> {
        (event: E): any;
    }

    export interface MultiCastEventSource {
        on(event: string, handler: Handler<Event>): MultiCastEventSource;
        off(event?: string, handler?: Handler<Event>): MultiCastEventSource;
    }

    export interface CommunicationEventSource {
        onCommunicationFailed?: Handler<CommunicationFailed>;
    }

    export interface AcquisitionEventSource {
        onDeviceConnected?: Handler<DeviceConnected>;
        onDeviceDisconnected?: Handler<DeviceDisconnected>;
        onSamplesAcquired?: Handler<SamplesAcquired>;
        onQualityReported?: Handler<QualityReported>;
        onErrorOccurred?: Handler<ErrorOccurred>;
        onAcquisitionStarted?: Handler<AcquisitionStarted>,
        onAcquisitionStopped?: Handler<AcquisitionStopped>,
    }

    export interface EventSource extends AcquisitionEventSource, CommunicationEventSource, MultiCastEventSource {
        on(event: string, handler: Handler<Event>): EventSource;
        on(event: "DeviceConnected", handler: Handler<DeviceConnected>): EventSource;
        on(event: "DeviceDisconnected", handler: Handler<DeviceDisconnected>): EventSource;
        on(event: "SamplesAcquired", handler: Handler<SamplesAcquired>): EventSource;
        on(event: "QualityReported", handler: Handler<QualityReported>): EventSource;
        on(event: "ErrorOccurred", handler: Handler<ErrorOccurred>): EventSource;
        on(event: "AcquisitionStarted", handler: Handler<AcquisitionStarted>): EventSource;
        on(event: "AcquisitionStopped", handler: Handler<AcquisitionStopped>): EventSource;
        on(event: "CommunicationFailed", handler: Handler<CommunicationFailed>): EventSource;
        off(event?: string, handler?: Handler<Event>): EventSource;
    }

    // @internal
    enum Method {
        EnumerateDevices = 1,
        GetDeviceInfo = 2,
        StartAcquisition = 3,
        StopAcquisition = 4
    }

    // @internal
    enum NotificationType {
        Completed = 0,
        Error = 1,
        Disconnected = 2,
        Connected = 3,
        Quality = 4,
        Stopped = 10,
        Started = 11
    }

    // @internal
    enum MessageType {
        Response = 0,
        Notification = 1
    }

    // @internal
    interface Response {
        Method: Method;
        Result: number;
        Data?: string;
    }

    // @internal
    interface Notification {
        Event: NotificationType;
        Device: string;
        Data?: string;
    }

    // @internal
    interface Message {
        Type: MessageType;
        Data: string;
    }

    // @internal
    interface EnumerateDevicesResponse {
        DeviceCount: number;
        DeviceIDs: string;
    }

    // @internal
    interface Completed {
        SampleFormat: SampleFormat;
        Samples: string;
    }

    // @internal
    interface Error {
        uError: number;
    }

    // @internal
    interface Quality {
        Quality: QualityCode;
    }

    // @internal
    class Command {
        Method: Method;
        Parameters?: string;
        constructor(method: Method, parameters?: string) {
            this.Method = method;
            if (parameters)
                this.Parameters = parameters;
        }
    }

    // @internal
    class Request {
        command: Command;
        resolve: Function;
        reject: Function;
        sent: boolean;
        constructor(command: Command, resolve: Function, reject: Function) {
            this.command = command;
            this.resolve = resolve;
            this.reject = reject;
            this.sent = false;
        }
    }

    export class WebApi implements EventSource {

        private webChannel: WebSdk.WebChannelClient;
        private requests: Request[] = [];
        private handlers: { [key: string]: Handler<Event>[] } = {};

        constructor(options?: WebSdk.WebChannelOptionsData) {
            var _instance = this;
            this.webChannel = new WebSdk.WebChannelClient("fingerprints", options);
            this.webChannel.onConnectionSucceed = () => { _instance.onConnectionSucceed(); };
            this.webChannel.onConnectionFailed = () => { _instance.onConnectionFailed(); };
            this.webChannel.onDataReceivedTxt = (data: string) => { _instance.onDataReceivedTxt(data); };
        }

        enumerateDevices(): Promise<string[]> {
            var _instance = this;
            return new Promise<string[]>(function (resolve, reject) {
                var command = new Command(Method.EnumerateDevices);
                var request = new Request(command, resolve, reject);
                _instance.requests.push(request);
                if (_instance.webChannel.isConnected())
                    _instance.processQueue();
                else
                    _instance.webChannel.connect();
            });
        }

        getDeviceInfo(deviceUid: string): Promise<DeviceInfo> {
            var _instance = this;
            return new Promise<DeviceInfo>(function (resolve, reject) {
                var deviceParams = { DeviceID: deviceUid };
                var command = new Command(Method.GetDeviceInfo, strToB64Url(JSON.stringify(deviceParams)));
                var request = new Request(command, resolve, reject);
                _instance.requests.push(request);
                if (_instance.webChannel.isConnected())
                    _instance.processQueue();
                else
                    _instance.webChannel.connect();
            });
        }

        startAcquisition(sampleFormat: SampleFormat, deviceUid?: string): Promise<void> {
            var _instance = this;
            return new Promise<void>(function (resolve, reject) {
                var acquisitionParams = { DeviceID: deviceUid ? deviceUid : "00000000-0000-0000-0000-000000000000", SampleType: sampleFormat };
                var command = new Command(Method.StartAcquisition, strToB64Url(JSON.stringify(acquisitionParams)));
                var request = new Request(command, resolve, reject);
                _instance.requests.push(request);
                if (_instance.webChannel.isConnected())
                    _instance.processQueue();
                else
                    _instance.webChannel.connect();
            });
        }

        stopAcquisition(deviceUid?: string): Promise<void> {
            var _instance = this;
            return new Promise<void>(function (resolve, reject) {
                var acquisitionParams = { DeviceID: deviceUid ? deviceUid : "00000000-0000-0000-0000-000000000000" };
                var command = new Command(Method.StopAcquisition, strToB64Url(JSON.stringify(acquisitionParams)));
                var request = new Request(command, resolve, reject);
                _instance.requests.push(request);
                if (_instance.webChannel.isConnected())
                    _instance.processQueue();
                else
                    _instance.webChannel.connect();
            });
        }

        private onConnectionSucceed(): void {
            this.processQueue();
        }

        private onConnectionFailed(): void {
            for (var i = 0; i < this.requests.length; i++) {
                this.requests[i].reject(new Error("Communication failure."));
            }
            this.requests = [];
            this.emit(new CommunicationFailed());
        }

        private onDataReceivedTxt(data: string): void {
            var message = <Message>JSON.parse(b64UrlToUtf8(data));
            if (message.Type === MessageType.Response) {
                var response = <Response>JSON.parse(b64UrlToUtf8(message.Data));
                this.processResponse(response);
            }
            else if (message.Type === MessageType.Notification) {
                var notification = <Notification>JSON.parse(b64UrlToUtf8(message.Data));
                this.processNotification(notification);
            }
        }

        private processQueue(): void {
            for (var i = 0; i < this.requests.length; i++) {
                if (this.requests[i].sent)
                    continue;
                this.webChannel.sendDataTxt(strToB64Url(JSON.stringify(this.requests[i].command)));
                this.requests[i].sent = true;
            }
        }

        private processResponse(response: Response): void {
            var request: Request | undefined;
            for (var i = 0; i < this.requests.length; i++) {
                if (!this.requests[i].sent)
                    continue;
                if (this.requests[i].command.Method === response.Method) {
                    request = this.requests[i];
                    this.requests.splice(i, 1);
                    break;
                }
            }
            if (request) {
                if (response.Method === Method.EnumerateDevices) {
                    if (response.Result < 0 || response.Result > 2147483647)
                        request.reject(new Error("EnumerateDevices: " + (response.Result >>> 0).toString(16)));
                    else {
                        var enumerateDevicesResponse = <EnumerateDevicesResponse>JSON.parse(b64UrlToUtf8(response.Data!));
                        request.resolve(JSON.parse(enumerateDevicesResponse.DeviceIDs));
                    }
                }
                else if (response.Method === Method.GetDeviceInfo) {
                    if (response.Result < 0 || response.Result > 2147483647)
                        request.reject(new Error("GetDeviceInfo: " + (response.Result >>> 0).toString(16)));
                    else {
                        var deviceInfo = <DeviceInfo>JSON.parse(b64UrlToUtf8(response.Data!));
                        request.resolve(deviceInfo);
                    }
                }
                else if (response.Method === Method.StartAcquisition) {
                    if (response.Result < 0 || response.Result > 2147483647)
                        request.reject(new Error("StartAcquisition: " + (response.Result >>> 0).toString(16)));
                    else
                        request.resolve();
                }
                else if (response.Method === Method.StopAcquisition) {
                    if (response.Result < 0 || response.Result > 2147483647)
                        request.reject(new Error("StopAcquisition: " + (response.Result >>> 0).toString(16)));
                    else
                        request.resolve();
                }
            }
        }

        private processNotification(notification: Notification): void {
            if (notification.Event === NotificationType.Completed) {
                var completed = <Completed>JSON.parse(b64UrlToUtf8(notification.Data!));
                this.emit(new SamplesAcquired(notification.Device, completed.SampleFormat, completed.Samples));
            }
            else if (notification.Event === NotificationType.Connected) {
                this.emit(new DeviceConnected(notification.Device));
            }
            else if (notification.Event === NotificationType.Disconnected) {
                this.emit(new DeviceDisconnected(notification.Device));
            }
            else if (notification.Event === NotificationType.Error) {
                var error = <Error>JSON.parse(b64UrlToUtf8(notification.Data!));
                this.emit(new ErrorOccurred(notification.Device, error.uError));
            }
            else if (notification.Event === NotificationType.Quality) {
                var quality = <Quality>JSON.parse(b64UrlToUtf8(notification.Data!));
                this.emit(new QualityReported(notification.Device, quality.Quality));
            }
            else if (notification.Event === NotificationType.Started) {
                this.emit(new AcquisitionStarted(notification.Device));
            }
            else if (notification.Event === NotificationType.Stopped) {
                this.emit(new AcquisitionStopped(notification.Device));
            }
        }

        onDeviceConnected?: Handler<DeviceConnected>;
        onDeviceDisconnected?: Handler<DeviceDisconnected>;
        onSamplesAcquired?: Handler<SamplesAcquired>;
        onQualityReported?: Handler<QualityReported>;
        onErrorOccurred?: Handler<ErrorOccurred>;
        onAcquisitionStarted?: Handler<AcquisitionStarted>;
        onAcquisitionStopped?: Handler<AcquisitionStopped>;
        onCommunicationFailed?: Handler<CommunicationFailed>;

        on<E extends Event>(event: string, handler: Handler<E>): WebApi {
            if (!this.handlers[event])
                this.handlers[event] = [];
            this.handlers[event].push(handler as Handler<Event>);
            return this;
        }

        off(event?: string, handler?: Handler<Event>): WebApi {
            if (event) {
                var hh: Handler<Event>[] = this.handlers[event];
                if (hh) {
                    if (handler)
                        this.handlers[event] = hh.filter(h => h !== handler);
                    else
                        delete this.handlers[event];
                }
            }
            else
                this.handlers = {};
            return this;
        }

        protected emit(event: Event): void {
            if (!event) return;

            var eventName: string = event.type;
            var unicast: Handler<Event> = (this as any)["on" + eventName];
            if (unicast)
                this.invoke(unicast, event);

            var multicast: Handler<Event>[] = this.handlers[eventName];
            if (multicast)
                multicast.forEach(h => this.invoke(h, event));
        }

        private invoke(handler: Handler<Event>, event: Event) {
            try {
                handler(event);
            } catch (e) {
                console.error(e);
            }
        }
    }
}
