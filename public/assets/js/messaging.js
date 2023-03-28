//! List selector
const handleNavBtnClick = function (event) {
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
}

//! List displayer
const handleUserListDisplayerClick = function (event) {
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

}

//! New message textarea
const resizeMessageTextarea = function (event) {
    event.currentTarget.style.height = 'auto';
    event.currentTarget.style.height = event.currentTarget.scrollHeight + 'px';
}

const resizeMessageList = function () {
    // Resize message liste based on textarea height
    const
        himselfHeight = document.querySelector('.himself').offsetHeight,
        himselfMarginBottom = parseInt(
            window.getComputedStyle(document.querySelector('.himself')).marginBottom,
            10
        ),
        newMessageHeight = document.querySelector('.new-message').offsetHeight;

    document.querySelector('.chat').style.height = `calc(100% - ${himselfHeight + himselfMarginBottom}px - ${newMessageHeight}px)`;
}

const handleTextareaFocus = function (event) {
    event.currentTarget.classList.add('active');
}

const handleTextareaBlur = function (event) {
    if (event.currentTarget.value !== '') return;
    event.currentTarget.classList.remove('active');
}

const handleTextareaPlaceholderClick = function () {
    document.querySelector('.new-message textarea').focus();
}

//! Window resize (Reset list selector, list displayer & new message textarea)
const handleWindowResize = function (event) {
    // Set show to displayer button
    document.querySelector('button.list-displayer').classList.add('show');

    // Open every lists
    document.querySelectorAll('.list-container .list').forEach(element => {
        element.style.marginTop = '0';
    });

    // Resize message list
    document.querySelector('.new-message textarea').dispatchEvent(new Event('input'));

}

document.addEventListener('DOMContentLoaded', function () {
    // List selector
    document.querySelectorAll('nav.list-selector button').forEach(button => {
        button.addEventListener('click', handleNavBtnClick);
    });

    // List displayer
    document.querySelector('button.list-displayer').addEventListener('click', handleUserListDisplayerClick);

    // New message textarea
    document.querySelector('.new-message textarea').addEventListener('input', resizeMessageTextarea);
    document.querySelector('.new-message textarea').addEventListener('input', resizeMessageList);
    document.querySelector('.new-message textarea').addEventListener('focus', handleTextareaFocus);
    document.querySelector('.new-message textarea').addEventListener('blur', handleTextareaBlur);
    
    document.querySelector('.new-message .placeholder').addEventListener('click', handleTextareaPlaceholderClick);

    // Window resize (Reset list selector, list displayer & new message textarea)
    window.addEventListener('resize', handleWindowResize);
});
