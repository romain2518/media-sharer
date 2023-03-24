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

//! Window resize (Reset list selector, list displayer & new message textarea)
const handleWindowResize = function (event) {
    // Set show to displayer button
    document.querySelector('button.list-displayer').classList.add('show');

    // Open every lists
    document.querySelectorAll('.list-container .list').forEach(element => {
        element.style.marginTop = '0';
    });

    // Resize message list
    document.querySelector('.new-message .textarea').dispatchEvent(new Event('input'));

}

document.addEventListener('DOMContentLoaded', function () {
    // List selector
    document.querySelectorAll('nav.list-selector button').forEach(button => {
        button.addEventListener('click', handleNavBtnClick);
    });

    // List displayer
    document.querySelector('button.list-displayer').addEventListener('click', handleUserListDisplayerClick);

    // New message textarea
    document.querySelector('.new-message .textarea').addEventListener('beforeinput', handleTextareaBeforeInput);
    document.querySelector('.new-message .textarea').addEventListener('input', resizeMessageList);
    document.querySelector('.new-message .textarea').addEventListener('focus', handleTextareaFocus);
    document.querySelector('.new-message .textarea').addEventListener('blur', handleTextareaBlur);
    
    document.querySelector('.new-message .placeholder').addEventListener('click', handleTextareaPlaceholderClick);

    // Window resize (Reset list selector, list displayer & new message textarea)
    window.addEventListener('resize', handleWindowResize);
});
