import messagingChatBox from "./messaging/messagingChatBox.js";
import messagingList from "./messaging/messagingList.js";
import messagingCall from "./messaging/messagingCall.js";
import webSocket from "./messaging/webSocket.js";

const homeApp = {
    init: function () {
        messagingList.init();
        messagingChatBox.init();
        messagingCall.init();
        webSocket.init();

        console.log('Home OK')
    }
}

document.addEventListener('DOMContentLoaded', homeApp.init);