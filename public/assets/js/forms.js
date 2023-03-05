const handleInputImageChange = function (event) {
    const file = event.currentTarget.files[0];
    if (!file) return;

    event.currentTarget.nextElementSibling.src = URL.createObjectURL(file);
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.image-input input[type=file]').forEach(input => {
        input.addEventListener('change', handleInputImageChange);
    });    
})