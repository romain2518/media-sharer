import messagingCallTimer from "./messagingCallTimer.js";
import webSocket from "./webSocket.js";

const messagingCall = {
    init: function () {
        this.getConn();

        document.querySelector('#start-call-button').addEventListener('click', this.handleStartCall);
        document.querySelector('#call-accept').addEventListener('click', this.handleAcceptCall);
        document.querySelector('#call-end').addEventListener('click', this.handleDenyCall);

        document.querySelector('#call-screen-share-on').addEventListener('click', this.handleStartCapture);
        document.querySelector('#call-screen-share-off').addEventListener('click', this.handleStopCapture);

        console.log('Voice messaging OK')
    },
    config: {
        iceServers: [
            {
                urls: ['stun:stun1.l.google.com:19302']
            }
        ],
        sdpSemantics: 'unified-plan'
    },
    pc: null,
    getConn: async function () {
        if (null === this.pc) {
            this.pc = new RTCPeerConnection(this.config);
        }
    },
    localStream: null,
    captureStream: null,
    userIdBeingCalled: null,
    userIdCalling: null,
    mediaConst: {
        audio: true,
        video: true,
        screen: true,
    },
    otherClientMediaConst: {
        offerToReceiveAudio: true,
        offerToReceiveVideo: true,
    },
    displayMediaOptions: {
        video: {
            displaySurface: "window",
        },
        audio: {
            echoCancellation: true,
            noiseSuppression: true,
            sampleRate: 44100,
            suppressLocalAudioPlayback: true,
        },
        surfaceSwitching: "include",
        selfBrowserSurface: "exclude",
        systemAudio: "exclude",
    },
    localVideoElm: document.querySelector('.call .users .user:first-child .camera'),
    remoteVideoElm: document.querySelector('.call .users .user:last-child .camera'),
    localScreenShareElm: document.querySelector('.call .screen-share .screen-wrapper:first-child .screen'),
    remoteScreenShareElm: document.querySelector('.call .screen-share .screen-wrapper:last-child .screen'),
    remoteAudioElm: null,
    callIntervalId: null,
    getCam: async function () {
        let mediaStream;

        try {
            this.getConn();
            mediaStream = await navigator.mediaDevices.getUserMedia(this.mediaConst);
            
            this.localVideoElm.srcObject = mediaStream;
            this.localVideoElm.play();
            this.localStream = mediaStream;
            this.localStream.getTracks().forEach(track => this.pc.addTrack(track, this.localStream));
        } catch (error) {
            console.log(error);
        }
    },
    handleStartCapture: async function () {
        let mediaStream;
    
        try {
            mediaStream = await navigator.mediaDevices.getDisplayMedia(messagingCall.displayMediaOptions);
    
            messagingCall.localScreenShareElm.srcObject = mediaStream;
            messagingCall.localScreenShareElm.play();
            messagingCall.localScreenShareElm.classList.add('active');
            document.querySelector('#call-screen-share-on').classList.add('active');
    
            messagingCall.captureStream = new MediaStream();
            mediaStream.getVideoTracks().forEach(track => messagingCall.captureStream.addTrack(track));
    
            messagingCall.pc.addTrack(messagingCall.captureStream.getTracks()[0], messagingCall.captureStream);
            webSocket.send('add-new-track', messagingCall.captureStream.getTracks()[0], messagingCall.userIdBeingCalled ?? messagingCall.userIdCalling);
        } catch (error) {
            console.log(error);
        }
    },
    handleStopCapture: function () {
        if (null === messagingCall.pc || 'connected' !== messagingCall.pc.iceConnectionState || null === messagingCall.captureStream) return;

        messagingCall.localScreenShareElm.srcObject = null;
        messagingCall.localScreenShareElm.classList.remove('active');
        document.querySelector('#call-screen-share-on').classList.remove('active');

        const screenTrack = messagingCall.captureStream.getVideoTracks()[0];
        const sender = messagingCall.pc.getSenders().find(s => s.track === screenTrack);
        if (sender) {
            messagingCall.pc.removeTrack(sender);
            messagingCall.captureStream.getTracks().forEach(track => track.stop());
        }
    },
    handleStartCall: async function () {
        if (null !== messagingCall.userIdBeingCalled || null !== messagingCall.userIdCalling) return;

        await messagingCall.getCam();

        const targetedUserId = document.querySelector('section:last-child').dataset.loadedUserId;
        
        messagingCall.userIdBeingCalled = targetedUserId;
        webSocket.send('is-client-ready', '', targetedUserId);

        document.querySelector('.call').className = 'call active calling';

        // Call is automatically canceled after 15s without response
        setTimeout(() => {
            if (null !== messagingCall.pc && 'connected' !== messagingCall.pc.iceConnectionState) {
                messagingCall.userIdBeingCalled = null;
                webSocket.send('call-canceled', '', targetedUserId);

                document.querySelector('.call').className = 'call active call-canceled-by-me';
                messagingCall.closeCall();
            }
        }, 10000);
    },
    handleAcceptCall: async function () {
        if (null === messagingCall.userIdCalling) return;

        await messagingCall.getCam();

        if (!document.querySelector('.call').classList.contains('incoming-call')) return;
        webSocket.send('client-is-ready', '', messagingCall.userIdCalling);
    },
    handleDenyCall: function () {
        if (null === messagingCall.userIdBeingCalled && null === messagingCall.userIdCalling) return;

        // If user calling denied
        if (document.querySelector('.call').classList.contains('calling')) {
            webSocket.send('call-canceled', '', messagingCall.userIdBeingCalled);

            document.querySelector('.call').className = 'call active call-canceled-by-me';
        }
        
        // If user being called denied
        if (document.querySelector('.call').classList.contains('incoming-call')) {
            webSocket.send('call-denied', '', messagingCall.userIdCalling);

            document.querySelector('.call').className = 'call active call-denied-by-me';
        }

        messagingCall.closeCall();
    },
    handleHangUpCall: function () {
        if (null === messagingCall.userIdBeingCalled && null === messagingCall.userIdCalling) return;

        webSocket.send('call-hung-up', '', messagingCall.userIdBeingCalled ?? messagingCall.userIdCalling);

        document.querySelector('.call').className = 'call active call-ended';
        messagingCall.closeCall();
    },
    isClientReady: function (user) {
        // If on call, calling or being called, incoming call is canceled
        if ((null !== this.pc && 'connected' === this.pc.iceConnectionState) || null !== this.userIdCalling || null !== this.userIdBeingCalled) {
            webSocket.send('client-already-on-call', '', user.id);
            return;
        }

        // Display incoming call
        this.userIdCalling = user.id;
        document.querySelector('.call').className = 'call active incoming-call';

        const callTargetedUserElm = document.querySelector('.call .users .user:last-child');
        callTargetedUserElm.querySelector('img').src = 'assets/images/userPictures/' + user.picturePath ?? '0.svg';
        callTargetedUserElm.querySelector('p').textContent = user.pseudo;
    },
    clientAlreadyOnCall: function (user) {
        if (user.id != this.userIdBeingCalled) return;

        document.querySelector('.call').className = 'call active call-denied-by-him';
        this.closeCall();
    },
    callCanceled: function (user) {
        if (user.id != this.userIdCalling) return;

        document.querySelector('.call').className = 'call active call-canceled-by-him';
        this.closeCall();
    },
    callDenied: function (user) {
        if (user.id != this.userIdBeingCalled && user.id != this.userIdCalling) return;

        document.querySelector('.call').className = 'call active call-denied-by-him';
        this.closeCall();
    },
    callHungUp: function (user) {
        if (user.id != this.userIdBeingCalled && user.id != this.userIdCalling) return;

        document.querySelector('.call').className = 'call active call-ended';
        this.closeCall();
    },
    createOffer: async function (user) {
        if (user.id != this.userIdBeingCalled) return;
        
        await this.getConn();

        await this.sendIceCandidate(user);
        await this.pc.createOffer(this.otherClientMediaConst);
        await this.pc.setLocalDescription(this.pc.localDescription);
        webSocket.send('client-offer', this.pc.localDescription, user.id);
    },
    createAnswer: async function (user, data) {
        if (user.id != this.userIdCalling) return;

        await this.getConn();

        await this.sendIceCandidate(user);
        await this.pc.setRemoteDescription(data);
        await this.pc.createAnswer();
        await this.pc.setLocalDescription(this.pc.localDescription);
        webSocket.send('client-answer', this.pc.localDescription, user.id);

        this.callIntervalId = setInterval(() => {
            if (null !== this.pc && 'failed' !== this.pc.iceConnectionState && 'disconnected' !== this.pc.iceConnectionState) return;

            document.querySelector('.call').className = 'call active call-ended';
            this.closeCall();

            clearInterval(this.callIntervalId);
            this.callIntervalId = null;
        }, 1000);
    },
    processAnswer: async function (user, data) {
        if (user.id != this.userIdBeingCalled) return;
        if (null === this.pc.localDescription) return;

        await this.getConn();

        await this.pc.setRemoteDescription(data);

        this.callIntervalId = setInterval(() => {
            if (null !== this.pc && 'failed' !== this.pc.iceConnectionState && 'disconnected' !== this.pc.iceConnectionState) return;

            document.querySelector('.call').className = 'call active call-ended';
            this.closeCall();

            clearInterval(this.callIntervalId);
            this.callIntervalId = null;
        }, 1000);
    },
    addClientCandidate: async function (user, data) {
        await this.getConn();

        if (this.pc.localDescription) {
            if (user.id != this.userIdBeingCalled && user.id != this.userIdCalling) return;
    
            await this.pc.addIceCandidate(new RTCIceCandidate(data));
            document.querySelector('.call').className = 'call active on-call';

            document.querySelector('#call-end').removeEventListener('click', this.handleDenyCall);
            document.querySelector('#call-end').addEventListener('click', this.handleHangUpCall);

            messagingCallTimer.start();
        }
    },
    addNewTrack: async function (user, data) {
        if (null === this.pc || 'connected' !== this.pc.iceConnectionState || user.id != this.userIdBeingCalled && user.id != this.userIdCalling) return;

        console.log(data);
        
        await this.pc.addTrack(new MediaStreamTrack(data), this.captureStream);
    },
    sendIceCandidate: async function (user) {
        this.pc.onicecandidate = (e) => {
            if (null !== e.candidate) {
                webSocket.send('client-candidate', e.candidate, user.id)
            }
        }

        this.pc.ontrack = async (e) => {
            console.log(e);
            if (e.track.kind === 'video') {
                this.remoteVideoElm.srcObject = e.streams[0];
                const playPromise = this.remoteVideoElm.play();
                if (undefined !== playPromise) // Prevent DOMException : play request was interrupted by a new load request
                    playPromise.then(_ => {}).catch(error => {});
            } else if (e.track.kind === 'audio') {
                const remoteAudio = new Audio();
                this.remoteAudioElm = remoteAudio;
                this.remoteAudioElm.srcObject = e.streams[0];
                const playPromise = this.remoteAudioElm.play();
                if (undefined !== playPromise) // Prevent DOMException : play request was interrupted by a new load request
                    playPromise.then(_ => {}).catch(error => {});
            }
        }
    },
    closeCall: function (delay = 2000) {
        if (null !== messagingCall.pc) {
            messagingCall.pc.close();
            messagingCall.pc = null;
        }

        if (null !== messagingCall.localStream) {
            messagingCall.localStream.getTracks().forEach(track => track.stop());
            messagingCall.localStream = null;
        }

        if (null !== messagingCall.callIntervalId) {
            clearInterval(messagingCall.callIntervalId);
            messagingCall.callIntervalId = null;
        }

        messagingCallTimer.stop(delay);
        
        document.querySelector('#call-end').removeEventListener('click', messagingCall.handleHangUpCall);
        document.querySelector('#call-end').addEventListener('click', messagingCall.handleDenyCall);

        setTimeout(() => {
            messagingCall.userIdCalling = null;
            messagingCall.userIdBeingCalled = null;

            document.querySelector('.call').className = 'call';
        }, delay);
    },
};

export default messagingCall;
