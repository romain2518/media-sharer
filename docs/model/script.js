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

    document.querySelector('section:last-child').scrollIntoView({ behavior: "smooth", block: "nearest" });
    document.querySelector('section:last-child').focus();
}

const resizeMessageList = function (event) {
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

function getCaretCharacterOffsetWithin(element) {
    let caretOffset = 0;
    const doc = element.ownerDocument || element.document;
    const win = doc.defaultView || doc.parentWindow;
    let sel;
    if (typeof win.getSelection != "undefined") {
        sel = win.getSelection();
        if (sel.rangeCount > 0) {
            const range = win.getSelection().getRangeAt(0);
            const preCaretRange = range.cloneRange();
            preCaretRange.selectNodeContents(element);
            preCaretRange.setEnd(range.endContainer, range.endOffset);
            caretOffset = preCaretRange.toString().length;
        }
    } else if ((sel = doc.selection) && sel.type != "Control") {
        const textRange = sel.createRange();
        const preCaretTextRange = doc.body.createTextRange();
        preCaretTextRange.moveToElementText(element);
        preCaretTextRange.setEndPoint("EndToEnd", textRange);
        caretOffset = preCaretTextRange.text.length;
    }
    return caretOffset;
}  

const handleTextareaBeforeInput = function (event) {
    if (!event.inputType.match(/^insert(?!Text)[A-Za-z]+$/)) return; // If event type is an insert but not insertText (paste, drop etc.)
    event.preventDefault();

    // Add text in plain text
    const caretPos = getCaretCharacterOffsetWithin(event.currentTarget);
    const textToAdd = event.dataTransfer.getData('text/plain');

    event.currentTarget.textContent = 
        event.currentTarget.textContent.slice(0, caretPos)
        + textToAdd
        + event.currentTarget.textContent.slice(caretPos);

    // Set back carret
    const range = document.createRange();
    const sel = window.getSelection();
    
    range.setStart(event.currentTarget.childNodes[0], caretPos + textToAdd.length);
    range.collapse(true);
    
    sel.removeAllRanges();
    sel.addRange(range);

    // Trigger input event to resize the message list
    event.currentTarget.dispatchEvent(new Event('input'));
}

const handleTextareaFocus = function (event) {
    event.currentTarget.classList.add('active');
}

const handleTextareaBlur = function (event) {
    if (event.currentTarget.textContent !== '') return;
    event.currentTarget.classList.remove('active');
}

const handleTextareaPlaceholderClick = function () {
    document.querySelector('.new-message .textarea').focus();
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

document.querySelector('.new-message .textarea').addEventListener('beforeinput', handleTextareaBeforeInput);
document.querySelector('.new-message .textarea').addEventListener('input', resizeMessageList);
document.querySelector('.new-message .textarea').dispatchEvent(new Event('input'));
document.querySelector('.new-message .textarea').addEventListener('focus', handleTextareaFocus);
document.querySelector('.new-message .textarea').addEventListener('blur', handleTextareaBlur);

document.querySelector('.new-message .placeholder').addEventListener('click', handleTextareaPlaceholderClick);