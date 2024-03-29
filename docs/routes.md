# Routes

## Pages statiques

| URL | Méthodes HTTP | Contrôleur | Méthode |
|--|--|--|--|
| /mentions-legales | GET | Main | legalMentions |
| /politique-de-confidentialite | GET | Main | privacyPolicy |
| /cgu | GET | Main | terms |
| /etats-des-services | GET | Main | status |

## Espace membre

| URL | Méthodes HTTP | Contrôleur | Méthode | Commentaire |
|--|--|--|--|--|
| /inscription | GET/POST | Registration | register | |
| /verification/email | GET | Registration | verifyUserEmail | |
| /verification/renvoi/{id} | GET | Registration | resendVerifyEmail | |
| /connexion | GET/POST | Security | login | |
| /deconnexion | GET/POST | Security | logout | |
| /modification-du-profil | GET/POST | User | editProfile | |
| /modification-des-identifiants | GET/POST | User | editLogins | |
| /reinitialisation-mdp | GET/POST | ResetPassword | request | |
| /reinitialisation-mdp/check-email | GET/POST | ResetPassword | checkEmail | |
| /reinitialisation-mdp/reset | GET/POST | ResetPassword | reset | |
| /suppression-du-compte | POST | User | delete | |

## Espace administrateur

| URL | Méthodes HTTP | Contrôleur | Méthode | Commentaire |
|--|--|--|--|--|
| /signalement/utilisateur | GET/POST | UserReport | new | |
| /signalement/bug | GET/POST | BugReport | new | |
| /notes-de-maj/{limit}/{offset} | GET | PatchNote | index | |
| /gestion/signalement/utilisateur/{limit}/{offset} | GET | UserReport | index | |
| /gestion/signalement/utilisateur/{id}/mark-as-{processed\|unprocessed\|important\|not-important} | POST | UserReport | markAs | |
| /gestion/signalement/utilisateur/{id}/suppression | POST | UserReport | delete | |
| /gestion/signalement/bug/{limit}/{offset} | GET | BugReport | index | |
| /gestion/signalement/bug/{id}/mark-as-{processed\|unprocessed\|important\|not-important} | POST | BugReport | markAs | |
| /gestion/signalement/bug/{id}/suppression | POST | BugReport | delete | |
| /gestion/note-de-maj/ajout | GET/POST | PatchNote | new | |
| /gestion/note-de-maj/{id}/modification | GET/POST | PatchNote | edit | |
| /gestion/note-de-maj/{id}/suppression | POST | PatchNote | delete | |
| /gestion/utilisateur/{limit}/{offset} | GET | User | index | |
| /gestion/utilisateur/{id}/reinitialisation-photo-de-profil | POST | User | manage | |
| /gestion/utilisateur/{id}/reinitialisation-pseudo | POST | User | manage | |
| /gestion/utilisateur/{id}/modification-role | POST | User | manage | |
| /gestion/utilisateur/{id}/ban | POST | User | manage | |
| /gestion/ban/{order}/{limit}/{offset} | GET/POST | Ban | index | GET : liste, POST : nouveau bannissement |
| /gestion/ban/{id}/suppression | POST | Ban | delete | |

## Messagerie instantanée

| URL | Méthodes HTTP | Contrôleur | Méthode | Commentaire |
|--|--|--|--|--|
| / | - | Main | home | - |
| - | - | Conversation | block | Cette route n'a ni URL ni méthode HTTP car elle sera utilisée par le websocket |
| - | - | Conversation | markConversation | Cette route n'a ni URL ni méthode HTTP car elle sera utilisée par le websocket  |
| - | - | Conversation | show | Cette route n'a ni URL ni méthode HTTP car elle sera utilisée par le websocket  |
| - | - | Conversation | new | Cette route n'a ni URL ni méthode HTTP car elle sera utilisée par le websocket  |
| - | - | Conversation | delete | Cette route n'a ni URL ni méthode HTTP car elle sera utilisée par le websocket  |
