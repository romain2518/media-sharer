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

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.states input[type=checkbox]').forEach(inputElm => {
        inputElm.addEventListener('change', handleSwitchState);
    });  
})