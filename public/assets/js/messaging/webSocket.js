import messagingCall from "./messagingCall.js";

const webSocket = {
    init: function () {
        this.notifier = new AWN({
            enabled: false,
            position: 'bottom-left',
        });

        this.conn = new WebSocket(`ws://${BASE_URL}:8080?token=${JWTToken}`);
        this.initConn();

        console.log('Messaging websocket OK')
    },
    initConn: function () {
        this.conn.onopen = () => this.handleWebSocketOpen();
        this.conn.onclose = () => this.handleWebSocketClose();
        this.conn.onmessage = (event) => this.handleWebSocketMessage(event);
    },
    conn: null,
    fatalErrorMessage: null,
    notifier: null,
    send: function (action, data, targetedUserId) {
        if (1 !== webSocket.conn.readyState) {
            webSocket.actions.displayFatalError();
            return;
        }
    
        webSocket.conn.send(
            JSON.stringify({
                action: action, 
                data: data, 
                targetedUserId: targetedUserId
            })
        );
    },
    handleWebSocketCTA: function (event) {
        // Prevent from trying to reload already loaded data, which can take a long time with a bad connection
        //              loading data when clicking on dropdown button
        if (
            event.currentTarget.dataset.action === 'show' && event.currentTarget.dataset.targetedUserId === document.querySelector('section:last-child').dataset.loadedUserId
            || event.currentTarget.dataset.action === 'show' && event.target.closest('article').querySelector('.dropdown').contains(event.target)
            ) return;
    
        webSocket.send(
            event.currentTarget.dataset.action,
            event.currentTarget.dataset.data ?? '',
            event.currentTarget.dataset.targetedUserId ?? '',
        );
    },
    handleSendMessage: function () {
        const messageElm = document.querySelector('.new-message textarea');
        messageElm.value = messageElm.value.trim()
        if ('' === messageElm.value) return;
    
        webSocket.send(
            'new', 
            {message: messageElm.value},
            document.querySelector('section:last-child').dataset.loadedUserId
        );
    
        messageElm.value = '';
    
        // Dispatch input event resizes message list
        document.querySelector('.new-message textarea').dispatchEvent(new Event('input'));
    },
    handleClickWithinChatSection: function (event) {
        // Set read when clicking chat section
        const loadedUserId = document.querySelector('section:last-child').dataset.loadedUserId;
        const conversationElm = document.querySelector(`.chat-list article[data-targeted-user-id="${loadedUserId}"]`)
        
        if (
            document.querySelector('section:last-child').contains(event.target)
            && null !== conversationElm && conversationElm.classList.contains('has-notification')
        ) webSocket.send('setRead', '', loadedUserId);
    },
    handleDeleteMessage: function (event) {
        const messageElm = event.currentTarget.closest('article');
    
        webSocket.send(
            'delete', 
            {id: messageElm.dataset.id},
            document.querySelector('section:last-child').dataset.loadedUserId
        );    
    },
    handleWebSocketOpen: function () {
        console.log("Connexion établie !");
        
        document.querySelectorAll('.WS_CTA').forEach(button => {
            button.addEventListener('click', this.handleWebSocketCTA);
        });
    
        document.querySelector('#send-message').addEventListener('click', this.handleSendMessage);
        document.querySelector('.new-message textarea').addEventListener('keydown', function (keyEvent) {
            if (keyEvent.key !== 'Enter' || keyEvent.key === 'Enter' && keyEvent.shiftKey) return;
            keyEvent.preventDefault();
            webSocket.handleSendMessage();
        })
    
        document.body.addEventListener('click', this.handleClickWithinChatSection);
    },
    handleWebSocketClose: function () {
        this.actions.displayFatalError();
            
        console.log("Connexion interrompue !");
    },
    handleWebSocketMessage: async function (event) {
        const message = JSON.parse(event.data);
        const data = JSON.parse(message.data);
        // console.log(message);
        // console.log(data);
    
        switch (message.action) {
            case 'error':
                this.actions.displayError(data.message);
                break;
            case 'fatalError':
                this.fatalErrorMessage = data.message;
                break;
            case 'block':
                this.actions.block(data);
                break;
            case 'unblock':
                this.actions.unblock(data);
                break;
            case 'setRead':
                this.actions.setRead(data);
                break;
            case 'setNotRead':
                this.actions.setNotRead(data);
                break;
            case 'show':
                this.actions.show(data);
                break;
            case 'new':
                this.actions.newMessage(data);
                break;
            case 'delete':
                this.actions.deleteMessage(data);
                break;
            case 'is-client-ready':
                messagingCall.isClientReady(data);
                break;
            case 'client-already-on-call':
                messagingCall.clientAlreadyOnCall(data);
                break;
            case 'call-canceled':
                messagingCall.callCanceled(data);
                break;
            case 'call-denied':
                messagingCall.callDenied(data);
                break;
            case 'call-hung-up':
                messagingCall.callHungUp(data);
                break;
            case 'client-is-ready':
                await messagingCall.createOffer(data);
                break;
            case 'client-offer':
                await messagingCall.createAnswer(JSON.parse(data.user), data.callData);
                break;
            case 'client-answer':
                await messagingCall.processAnswer(JSON.parse(data.user), data.callData);
                break;
            case 'client-candidate':
                await messagingCall.addClientCandidate(JSON.parse(data.user), data.callData);
                break;
            case 'add-new-track':
                await messagingCall.addNewTrack(JSON.parse(data.user), data.callData);
                break;
        }
    },
    createConversationElm: function (data, otherUserIndex, currentUserIndex) {
        const userId = data.users[otherUserIndex].id;
    
        const conversationElm = document.querySelector('#conversation-li-template').content.cloneNode(true);
    
        if (!data.statuses[currentUserIndex].isRead) 
            conversationElm.querySelector('article').classList.add('has-notification');
        
        conversationElm.querySelector('article').dataset.targetedUserId = userId;
        conversationElm.querySelector('.user-pic').src = 'assets/images/userPictures/' + data.users[otherUserIndex].picturePath ?? '0.svg';
    
        conversationElm.querySelector('h3').title = data.users[otherUserIndex].pseudo;
        conversationElm.querySelector('h3').textContent = data.users[otherUserIndex].pseudo;
    
        conversationElm.querySelector('h3 + p').title = data.updatedAt !== '' ? data.updatedAt : data.createdAt;
        conversationElm.querySelector('h3 + p').textContent = data.updatedAt !== '' ? data.updatedAt : data.createdAt;
    
        conversationElm.querySelector('.dropdown>button').addEventListener('click', app.handleDropdownBtn);
        conversationElm.querySelectorAll('.WS_CTA').forEach(buttonElm => {
            buttonElm.dataset.targetedUserId = userId;
            buttonElm.addEventListener('click', webSocket.handleWebSocketCTA);
        });
    
        conversationElm.querySelector('.report-link').href = '/signalement/utilisateur/' + userId;
    
        document.querySelector('.chat-list ul li.empty').after(conversationElm);
    
        // Returning conversationElm would return a #document-fragment
        return document.querySelector('.chat-list ul li.empty + *');
    },
    createMessageElm: function (message, otherUserId, first = false) {
        const newMessageElm = document.querySelector('#message-li-template').content.cloneNode(true);
    
        if (message.user.id === otherUserId)
            newMessageElm.querySelector('article').classList.add('him');
        
        newMessageElm.querySelector('article').dataset.id = message.id;
        newMessageElm.querySelector('.profile-pic').src = 'assets/images/userPictures/' + message.user.picturePath ?? '0.svg';
        newMessageElm.querySelector('.pseudo').textContent = message.user.pseudo;
        newMessageElm.querySelector('.delete-message').addEventListener('click', webSocket.handleDeleteMessage);
        newMessageElm.querySelector('.date p').textContent = '' !== message.updatedAt ? message.updatedAt + ' (modifié)' : message.createdAt;
    
        newMessageElm.querySelector('.message').textContent = message.message;
        
        if (first) {
            document.querySelector('.chat ul').prepend(newMessageElm);
        } else {
            document.querySelector('.chat ul').appendChild(newMessageElm);
        }
    
        // Returning conversationElm would return a #document-fragment
        return document.querySelector('.chat ul>*:last-child');
    },
    actions: {
        displayFatalError: function () {
            let message = '<h3>Erreur, la connexion au serveur a été interrompue</h3><p>Veuillez actualiser la page.</p>';
            
            if (null !== webSocket.fatalErrorMessage)
                message+=`<p>Message d'erreur : <code>${webSocket.fatalErrorMessage}</code></p>`;
            
            webSocket.notifier.confirm(
                message,
                function () {location.reload();},
                null,
                {
                    labels: {
                        confirm: '',
                        confirmOk: 'Recharger la page',
                        confirmCancel: 'Annuler',
                    }
                }
            );
        
            // Clicking outside close the modal
            document.querySelector('#awn-popup-wrapper').addEventListener('click', function (event) {
                if (event.target === document.querySelector('#awn-popup-wrapper')) document.querySelector('#awn-popup-wrapper').remove();
            })
        },
        displayError: function(message) {
            webSocket.notifier.alert(message);
        },
        block: function (data) {
            // Close chat box if loaded user is the blocked one
            if (data.id == document.querySelector('section:last-child').dataset.loadedUserId) {
                document.querySelector('section:last-child').classList.add('is-empty');
                delete document.querySelector('section:last-child').dataset.loadedUserId;
            }
        
            const conversationElm = document.querySelector(`.chat-list article[data-targeted-user-id="${data.id}"]`);
            if (null !== conversationElm) conversationElm.closest('li').remove();
            
            const userElm = document.querySelector(`.user-list article[data-targeted-user-id="${data.id}"]`);
            if (null === userElm) return;
            const userLiElm = userElm.closest('li');
            if (null !== userLiElm) userLiElm.remove();
        
            // Change block button to unblock button
            userElm.classList.remove('active');
            userElm.querySelector('button[data-action="block"] span').textContent = 'Débloquer';
            userElm.querySelector('button[data-action="block"]').dataset.action = 'unblock';
        
            // Insert element in block list after .empty element
            document.querySelector('.block-list ul li.empty').after(userLiElm);
        },
        unblock: function (data) {
            let userId = data.id;
            let otherUserIndex, currentUserIndex;
            if (typeof data.users !== 'undefined') {
                [otherUserIndex, currentUserIndex] = data.users[0].id === APP_USER_ID ? [1, 0] : [0, 1];
                
                userId = data.users[otherUserIndex].id;
            }
        
            const blockedUserElm = document.querySelector(`.block-list article[data-targeted-user-id="${userId}"]`);
            if (null === blockedUserElm) return;
            const blockedUserLiElm = blockedUserElm.closest('li');
            if (null !== blockedUserElm) blockedUserLiElm.remove();
            
            // Change unblock button to block button
            blockedUserElm.querySelector('button[data-action="unblock"] span').textContent = 'Bloquer';
            blockedUserElm.querySelector('button[data-action="unblock"]').dataset.action = 'block';
            
            // Insert element in user list after .empty element
            document.querySelector('.user-list ul li.empty').after(blockedUserLiElm);
        
            // Clone & insert element in chat list if needed
            if (typeof data.users === 'undefined') return;
        
            webSocket.createConversationElm(data, otherUserIndex, currentUserIndex);
        },
        setRead: function (data) {
            const otherUserIndex = data.users[0].id === APP_USER_ID ? 1 : 0;
            const userId = data.users[otherUserIndex].id;
        
            const conversationElm = document.querySelector(`.chat-list article[data-targeted-user-id="${userId}"]`)
            if (null !== conversationElm) conversationElm.classList.remove('has-notification');
        },
        setNotRead: function (data) {
            const otherUserIndex = data.users[0].id === APP_USER_ID ? 1 : 0;
            const userId = data.users[otherUserIndex].id;
        
            const conversationElm = document.querySelector(`.chat-list article[data-targeted-user-id="${userId}"]`)
            if (null !== conversationElm) conversationElm.classList.add('has-notification');
        },
        show: function (data) {
            const [otherUserIndex, currentUserIndex] = data.users[0].id === APP_USER_ID ? [1, 0] : [0, 1];
            const otherUserId = data.users[otherUserIndex].id;
        
            // Deactive last active elements
            const lastConversationElm = document.querySelector('.chat-list .user-card.active');
            const lastUserElm = document.querySelector('.user-list .user-card.active');
            const lastBlockedUserElm = document.querySelector('.block-list .user-card.active');
        
            if (null !== lastConversationElm) lastConversationElm.classList.remove('active');
            if (null !== lastUserElm) lastUserElm.classList.remove('active');
            if (null !== lastBlockedUserElm) lastBlockedUserElm.classList.remove('active');
            
            // Active new elements
            let conversationElm = document.querySelector(`.chat-list article[data-targeted-user-id="${otherUserId}"]`)
            const userElm = document.querySelector(`.user-list article[data-targeted-user-id="${otherUserId}"]`);
            const blockedUserElm = document.querySelector(`.block-list article[data-targeted-user-id="${otherUserId}"]`);
            
            // If no conversation element exists and targeted user isn't blocked, create a conversation
            if (null === conversationElm && null === blockedUserElm)
                conversationElm = webSocket.createConversationElm(data, otherUserIndex, currentUserIndex).querySelector('article');
            if (null !== conversationElm) conversationElm.classList.add('active');
            if (null !== userElm) userElm.classList.add('active');
        
            // Set conversation to read since it is being read
            if (null !== conversationElm) conversationElm.classList.remove('has-notification');
        
            // Fill chat section with new datas
            document.querySelector('section:last-child').classList.remove('is-empty');
            document.querySelector('section:last-child').dataset.loadedUserId = otherUserId;
        
            const himselfElm = document.querySelector('.himself');
        
            himselfElm.querySelector('.profile-pic').src = 'assets/images/userPictures/' + data.users[otherUserIndex].picturePath ?? '0.svg';
            himselfElm.querySelector('h2').textContent = data.users[otherUserIndex].pseudo;
            himselfElm.querySelector('h2+p').textContent = 'Inscrit ' + data.users[otherUserIndex].createdAt;
        
            himselfElm.querySelectorAll('.WS_CTA').forEach(buttonElm => {
                buttonElm.dataset.targetedUserId = otherUserId;
                buttonElm.addEventListener('click', webSocket.handleWebSocketCTA);
            });
            
            himselfElm.querySelector('.report-link').href = '/signalement/utilisateur/' + otherUserId;

            // Fill call section
            const callTargetedUserElm = document.querySelector('.call .users .user:last-child');

            callTargetedUserElm.querySelector('img').src = 'assets/images/userPictures/' + data.users[otherUserIndex].picturePath ?? '0.svg';
            callTargetedUserElm.querySelector('p').textContent = data.users[otherUserIndex].pseudo;
        
            // Empty the message list
            document.querySelector('.chat ul').innerHTML = '';;
        
            data.messages.forEach(message => {
                webSocket.createMessageElm(message, otherUserId);
            });
        
            // Dispatch input event resizes message list
            document.querySelector('.new-message textarea').dispatchEvent(new Event('input'));
        
            document.querySelector('.new-message textarea').classList.remove('active');
            document.querySelector('.new-message textarea').value = '';
        
            // For mobile devices : scroll to the chat section
            document.querySelector('section:last-child').scrollIntoView({ behavior: "smooth", block: "nearest" });
            document.querySelector('section:last-child').focus();
        },
        newMessage: function (data) {
            // Display errors if needed
            if (typeof data.id === 'undefined') {
                data.forEach(message => {
                    this.displayError(message);
                });
                return;
            }
        
            const [otherUserIndex, currentUserIndex] = data.users[0].id === APP_USER_ID ? [1, 0] : [0, 1];
            const otherUserId = data.users[otherUserIndex].id;
        
            // If conversation element doesn't exist create a conversation
            let conversationElm = document.querySelector(`.chat-list article[data-targeted-user-id="${otherUserId}"]`)
            if (null === conversationElm)
                conversationElm = webSocket.createConversationElm(data, otherUserIndex, currentUserIndex).querySelector('article');
        
            // Update conversation last update date
            conversationElm.querySelector('h3 + p').title = data.updatedAt;
            conversationElm.querySelector('h3 + p').textContent = data.updatedAt;
        
            // If user is receiver add a notification
            if (data.messages[0].user.id === otherUserId) 
                conversationElm.classList.add('has-notification');
        
            // If loaded user is other user add message
            if (otherUserId == document.querySelector('section:last-child').dataset.loadedUserId)
            webSocket.createMessageElm(data.messages[0], otherUserId, true);
        },
        deleteMessage: function (data) {
            const otherUserIndex = data.conversation.users[0].id === APP_USER_ID ? 1 : 0;
            const otherUserId = data.conversation.users[otherUserIndex].id;
        
            // If conversation is loaded remove message
            if (otherUserId != document.querySelector('section:last-child').dataset.loadedUserId) return;
            
            const messageElm = document.querySelector(`.message-card[data-id="${data.id}"]`);
            if (null !== messageElm) messageElm.remove();
        }
    },
};

export default webSocket;
