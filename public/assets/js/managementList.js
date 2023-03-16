const handleSwitchState = function (event) {
    const action = (event.currentTarget.checked ? '' : 'non-') + event.currentTarget.dataset.action;

    const httpHeaders = new Headers();

    const bodyFormData = new FormData();
    bodyFormData.append('_token', event.currentTarget.dataset.token);

    const init = {
        method: 'POST',
        headers: httpHeaders,
        mode: 'cors',
        body: bodyFormData,
        cache: 'default'
    };

    fetch(`${BASE_URL}/${event.currentTarget.dataset.id}/marquer-comme-${action}`, init)
        .then(function (response) {
            if (response.status === 206) {
                // return response.json().then(function (json) {
                    // ...
                // });
            } else {
                alert('Une erreur est survenue.')
            }
        });
}

const handleManageActions = function (event) {
    const currentTargetElm = event.currentTarget;
    if (
        currentTargetElm.tagName.toLowerCase() === 'select' && currentTargetElm.selectedIndex === 0
        || !confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
        currentTargetElm.selectedIndex = 0;
        return;
    }

    const action = currentTargetElm.dataset.action;
    const articleElm = currentTargetElm.closest('article');

    const httpHeaders = new Headers();

    const bodyFormData = new FormData();
    bodyFormData.append('_token', articleElm.dataset.token);

    if (currentTargetElm.tagName.toLowerCase() === 'select') 
        bodyFormData.append('role', currentTargetElm.value);

    const init = {
        method: 'POST',
        headers: httpHeaders,
        mode: 'cors',
        body: bodyFormData,
        cache: 'default'
    };

    fetch(`${BASE_URL}/${articleElm.dataset.id}/${action}`, init)
        .then(function (response) {
            if (response.status === 206) {
                return response.json().then(function (json) {
                    articleElm.querySelector(':scope>img').src = articleElm.querySelector(':scope>img').src.replace(/\/[^/]*$/, '/' + (json.picturePath ?? '0.svg'));
                    articleElm.querySelector('h3').textContent = json.pseudo;
                    articleElm.querySelector('.role').textContent = 'Rôles : ' + json.roles.map(role => ROLES_TRANSLATOR[role]).join(', ');
                });
            } else if (response.status === 204) {
                articleElm.remove();
            } else {
                currentTargetElm.selectedIndex = 0;
                alert('Une erreur est survenue.')
            }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.states input[type=checkbox]').forEach(inputElm => {
        inputElm.addEventListener('change', handleSwitchState);
    });

    document.querySelectorAll('button.POST_CTA').forEach(buttonElm => {
        buttonElm.addEventListener('click', handleManageActions);
    });

    document.querySelectorAll('select.POST_CTA').forEach(buttonElm => {
        buttonElm.addEventListener('change', handleManageActions);
    })
})