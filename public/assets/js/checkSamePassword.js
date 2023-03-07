const handleFormSubmit = function (event) {
    [password1Elm, password2Elm] = event.currentTarget.querySelectorAll('input[type=password].needSameCheck');

    if (password1Elm.value === password2Elm.value) return;

    event.preventDefault();

    // Removing existings same password errors
    document.querySelectorAll('.info.error li, .info.error p').forEach(element => {
        if (element.textContent === 'Les mots de passes sont différents.') element.remove();
    });
    
    const pElm = document.createElement('p');
    pElm.textContent = 'Les mots de passes sont différents.';

    if (password1Elm.parentNode.previousElementSibling
        && password1Elm.parentNode.previousElementSibling.classList.contains('error')) {
        password1Elm.parentNode.previousElementSibling.appendChild(pElm);
    } else {
        const errorElm = document.createElement('div');
        errorElm.classList.add('info', 'error');
        errorElm.appendChild(pElm);
        password1Elm.parentNode.before(errorElm);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('form').addEventListener('submit', handleFormSubmit);
})