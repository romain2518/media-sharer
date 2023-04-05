const messagingList = {
    init: function () {
        document.querySelector('.search input').addEventListener('input', this.handleListSearch);

        document.querySelectorAll('nav.list-selector button').forEach(button => {
            button.addEventListener('click', this.handleListSelector);
        });

        document.querySelector('button.list-displayer').addEventListener('click', this.handleListDisplayer);

        window.addEventListener('resize', this.handleWindowResize);
        
        console.log('Messaging list OK')
    },
    handleListSelector: function (event) {
        // Empty the search bar
        document.querySelector('.search input').value = '';
        document.querySelector('.search input').dispatchEvent(new Event('input'));
    
    
        // Remove active from last button
        const lastButton = document.querySelector('nav.list-selector button.active');
        lastButton.classList.remove('active');
        
        // Close last list & remove active from last list
        const lastList = document.querySelector('.list-container .' + lastButton.attributes.for.value);
        lastList.style.marginTop = `-${lastList.offsetHeight}px`;
        lastList.classList.remove('active');
    
        
        // Set active to new button & list
        event.currentTarget.classList.add('active');
        const newList = document.querySelector('.list-container .' + event.currentTarget.attributes.for.value);
        newList.classList.add('active');
    
        // Open or close new list based on displayer button status
        if (document.querySelector('button.list-displayer').classList.contains('show')) {
            newList.style.marginTop = '0';
        } else {
            newList.style.marginTop = `-${newList.offsetHeight}px`;    
        }
    },
    handleListDisplayer: function (event) {
        if (event.currentTarget.classList.contains('show')) {
            // Remove show from button
            event.currentTarget.classList.remove('show');
    
            // Close every lists
            document.querySelectorAll('.list-container .list').forEach(element => {
                if (!element.classList.contains('active')) {
                    element.classList.add('active');
                    element.style.marginTop = `-${element.offsetHeight}px`;
                    element.classList.remove('active');
                } else {
                    element.style.marginTop = `-${element.offsetHeight}px`;
                }
            });
        } else {
            // Set show to button
            event.currentTarget.classList.add('show');
    
            // Open every lists
            document.querySelectorAll('.list-container .list').forEach(element => {
                element.style.marginTop = '0';
            });
        }
    },
    handleListSearch: function (event) {
        document.querySelectorAll('.list.active .user-card').forEach(userLiElm => {
            userLiElm.closest('li').classList.remove('hidden');
    
            if ('' !== event.currentTarget.value && !userLiElm.querySelector('h3').textContent.startsWith(event.currentTarget.value))
                userLiElm.closest('li').classList.add('hidden');
        });
    },
    handleWindowResize: function () {
        // Set show to displayer button
        document.querySelector('button.list-displayer').classList.add('show');

        // Open every lists
        document.querySelectorAll('.list-container .list').forEach(element => {
            element.style.marginTop = '0';
        });
    },
};

export default messagingList;
