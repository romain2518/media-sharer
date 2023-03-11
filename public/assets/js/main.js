const handleButtonUpClick = function () {
    window.scrollTo(0, 0);
}

const handleBodyScroll = function () {
    document.querySelector('button.up').classList.add('active');
        
    if (window.scrollY === 0) document.querySelector('button.up').classList.remove('active');
}

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

document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('button.up').addEventListener('click', handleButtonUpClick);
    window.addEventListener('scroll', handleBodyScroll);
});

window.addEventListener('click', handleWindowClick);

document.querySelectorAll('.dropdown>button').forEach(element => {
    element.addEventListener('click', handleDropdownBtn);
});
