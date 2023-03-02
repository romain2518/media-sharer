const handleButtonUpClick = function () {
    window.scrollTo(0, 0);
}

const handleBodyScroll = function () {
    document.querySelector('button.up').classList.add('active');
        
    if (window.scrollY === 0) document.querySelector('button.up').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('button.up').addEventListener('click', handleButtonUpClick);
    window.addEventListener('scroll', handleBodyScroll);
});