const handleDropdownBtn = function (event) {
    // Close every other open dropdown
    document.querySelectorAll('.dropdown.active button').forEach(element => {
        if (!element.isSameNode(event.currentTarget)) element.parentNode.classList.remove('active');
    });

    event.currentTarget.parentNode.classList.toggle('active');
}

const handleWindowClick = function (event) {
    if (
        event.target.closest('.dropdown') // Is null
        && event.target.closest('.dropdown').classList.contains('active') // Is active
        && !event.target.classList.contains('dropdown') // Is not blank part of div.dropdown
        ) return;
    
    // Close every open dropdown
    document.querySelectorAll('.dropdown.active').forEach(element => {
        element.classList.remove('active');
    });
}

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

const handleWindowResize = function (event) {
    // Set show to displayer button
    document.querySelector('button.list-displayer').classList.add('show');

    // Open every lists
    document.querySelectorAll('.list-container .list').forEach(element => {
        element.style.marginTop = '0';
    });
}

const handleUserCardClick = function (event) {
    const lastCard = document.querySelector('.user-card.active');
    if (lastCard) lastCard.classList.remove('active');

    event.currentTarget.classList.remove('has-notification');
    event.currentTarget.classList.add('active');
}

const handleNewMessageTextareaInput = function (event) {
    // Resize textarea
    event.currentTarget.style.height = `${event.currentTarget.scrollHeight}px`;

    // Resize chat box based on textarea height
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

window.addEventListener('click', handleWindowClick);

document.querySelectorAll('.dropdown>button').forEach(element => {
    element.addEventListener('click', handleDropdownBtn);
});

document.querySelectorAll('nav.list-selector button').forEach(button => {
    button.addEventListener('click', handleNavBtnClick);
});

document.querySelector('button.list-displayer').addEventListener('click', handleUserListDisplayerClick);

window.addEventListener('resize', handleWindowResize);

document.querySelectorAll('.user-card').forEach(card => {
    card.addEventListener('click', handleUserCardClick);
});

document.querySelector('.new-message textarea').addEventListener('input', handleNewMessageTextareaInput);
document.querySelector('.new-message textarea').dispatchEvent(new Event("input"));
document.querySelector('.new-message textarea').addEventListener('focus', handleTextareaFocus);
document.querySelector('.new-message textarea').addEventListener('blur', handleTextareaBlur);

document.querySelector('.new-message .placeholder').addEventListener('click', handleTextareaPlaceholderClick);