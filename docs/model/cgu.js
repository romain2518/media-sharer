const handleSectionDisplayer = function (event) {
    event.currentTarget.closest('section').classList.toggle('active');
}

document.querySelector('.section-displayer').addEventListener('click', handleSectionDisplayer);
document.querySelector('section:first-child h2').addEventListener('click', handleSectionDisplayer);