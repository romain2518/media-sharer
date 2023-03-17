const conn = new WebSocket('ws://localhost:8080?token='+JWTToken);
let errorMessage = null

conn.onopen = function(e) {console.log("Connexion établie !");};
conn.onclose = function(e) {
    alert(
        'Erreur, la connexion au serveur a été interrompue, veuillez actualiser la page.\nSi le problème persiste veuillez réessayer plus tard.' 
        + (null === errorMessage ? '' : '\n\nMessage d\'erreur : '+errorMessage)
    );
    console.log("Connexion interrompue !");
};

conn.onmessage = function(e) {
    const message = JSON.parse(e.data);
    console.log(message);
    if (message.action === 'error') {
        errorMessage = message.data;
    }
};

function send (action, data, targetedUserId) {
    conn.send(
        JSON.stringify({
            action: action, 
            data: data, 
            targetedUserId: targetedUserId
        })
    );
}