const messagingChatBox = {
    init: function () {
        document.querySelector('.new-message .placeholder').addEventListener('click', this.handleTextareaPlaceholderClick);

        document.querySelector('.new-message textarea').addEventListener('input', this.handleTextareaInput);
        document.querySelector('.new-message textarea').addEventListener('focus', this.handleTextareaFocus);
        document.querySelector('.new-message textarea').addEventListener('blur', this.handleTextareaBlur);
        
        window.addEventListener('resize', this.handleWindowResize);

        console.log('Messaging chat box OK')
    },
    handleTextareaPlaceholderClick: function () {
        document.querySelector('.new-message textarea').focus();
    },
    handleTextareaInput: function (event) {
        // Textarea must be resized before list
        messagingChatBox.resizeMessageTextarea(event);
        messagingChatBox.resizeMessageList();
    },
    handleTextareaFocus: function (event) {
        event.currentTarget.classList.add('active');
    },
    handleTextareaBlur: function (event) {
        if (event.currentTarget.value !== '') return;
        event.currentTarget.classList.remove('active');
    },
    handleWindowResize: function () {
        // Resize message list
        document.querySelector('.new-message textarea').dispatchEvent(new Event('input'));
    },
    resizeMessageTextarea: function (event) {
        event.currentTarget.style.height = 'auto';
        event.currentTarget.style.height = event.currentTarget.scrollHeight + 'px';
    },
    resizeMessageList: function () {
        // Resize message liste based on textarea height
        const
            himselfHeight = document.querySelector('.himself').offsetHeight,
            himselfMarginBottom = parseInt(
                window.getComputedStyle(document.querySelector('.himself')).marginBottom,
                10
            ),
            newMessageHeight = document.querySelector('.new-message').offsetHeight;
    
        document.querySelector('.chat').style.height = `calc(100% - ${himselfHeight + himselfMarginBottom}px - ${newMessageHeight}px)`;
    },
};

export default messagingChatBox;
