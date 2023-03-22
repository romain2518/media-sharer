const conn = new WebSocket('ws://localhost:8080?token='+JWTToken);
let errorMessage = null

conn.onopen = function(e) {
    console.log("Connexion établie !");

    document.querySelectorAll('.WS_CTA').forEach(button => {
        button.addEventListener('click', handleWebSocketCTA);
    });
};
conn.onclose = function(e) {
    alert(
        'Erreur, la connexion au serveur a été interrompue, veuillez actualiser la page.\nSi le problème persiste veuillez réessayer plus tard.' 
        + (null === errorMessage ? '' : '\n\nMessage d\'erreur : '+errorMessage)
    );
    console.log("Connexion interrompue !");
};

conn.onmessage = function(e) {
    const message = JSON.parse(e.data);
    console.log(message);
    switch (message.action) {
        case 'error':
            errorMessage = message.data;
            break;
        case 'block':
            {
                const data = JSON.parse(message.data);

                const conversationElm = document.querySelector(`.chat-list article[data-user-id="${data.id}"]`)
                if (null !== conversationElm) conversationElm.closest('li').remove();
                
                const userElm = document.querySelector(`.user-list article[data-user-id="${data.id}"]`)
                if (null === userElm) return;
                const userLiElm = userElm.closest('li');
                if (null !== userLiElm) userLiElm.remove();

                // Change block button to unblock button
                userElm.querySelector('button[data-action="block"] span').textContent = 'Débloquer';
                userElm.querySelector('button[data-action="block"]').dataset.action = 'unblock';

                // Insert element in block list after .empty element
                document.querySelector('.block-list ul li.empty').after(userLiElm);
            }

            break;
        case 'unblock':
            {
                const data = JSON.parse(message.data);

                let userId = data.id;
                let otherUserIndex, currentUserIndex;
                if (typeof data.users !== 'undefined') {
                    [otherUserIndex, currentUserIndex] = data.users[0].id === APP_USER_ID ? [1, 0] : [0, 1];
                    
                    userId = data.users[otherUserIndex].id;
                }

                const blockedUserElm = document.querySelector(`.block-list article[data-user-id="${userId}"]`)
                const blockedUserLiElm = blockedUserElm.closest('li');
                if (null !== blockedUserElm) blockedUserLiElm.remove();
                
                // Change unblock button to block button
                blockedUserElm.querySelector('button[data-action="unblock"] span').textContent = 'Bloquer';
                blockedUserElm.querySelector('button[data-action="unblock"]').dataset.action = 'block';
                
                // Insert element in user list after .empty element
                document.querySelector('.user-list ul li.empty').after(blockedUserLiElm);

                // Clone & insert element in chat list if needed
                if (typeof data.users === 'undefined') return;

                const conversationElm = document.querySelector('#conversation-li-template').content.cloneNode(true);
                if (!data.statuses[currentUserIndex].isRead) 
                    conversationElm.querySelector('article').classList.add('has-notification');
                conversationElm.querySelector('article').dataset.userId = userId;
                conversationElm.querySelector('.user-pic').src = 'assets/images/userPictures/' + data.users[otherUserIndex].picturePath ?? '0.svg';
                
                conversationElm.querySelector('h3').title = data.users[otherUserIndex].pseudo;
                conversationElm.querySelector('h3').textContent = data.users[otherUserIndex].pseudo;
                
                conversationElm.querySelector('h3 + p').title = data.updatedAt !== '' ? data.updatedAt : data.createdAt;
                conversationElm.querySelector('h3 + p').textContent = data.updatedAt !== '' ? data.updatedAt : data.createdAt;

                conversationElm.querySelector('button[data-action="block"]').dataset.targetedUserId = userId;
                
                conversationElm.querySelector('.report-link').href = '/signalement/utilisateur/' + userId;

                // Replacing event listeners
                conversationElm.querySelector('.dropdown>button').addEventListener('click', handleDropdownBtn);
                conversationElm.querySelector('.WS_CTA').addEventListener('click', handleWebSocketCTA);

                document.querySelector('.chat-list ul li.empty').after(conversationElm);
            }

            break;
    }
};

function send (action, data, targetedUserId) {
    conn.send(
        JSON.stringify({
            action: action, 
            data: data, 
            targetedUserId: targetedUserId
        })
    );
}

const handleWebSocketCTA = function (event) {
    send(
        event.currentTarget.dataset.action,
        event.currentTarget.dataset.data ?? '',
        event.currentTarget.dataset.targetedUserId ?? '',
    );
}