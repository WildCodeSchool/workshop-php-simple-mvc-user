# S'authentifier dans le Simple MVC

## Introduction et mise en place

Tu as pris en main le Simple-MVC depuis quelques temps maintenant. Tu es familiarisé avec les routes, les Controllers et leurs méthodes. Les vues Twig ne te sont plus étrangères et tu manipules le passage des variables issues des requêtes Models.
Poursuivons les explorations.


Clone ce dépôt grâce au lien donné ci-dessus ⬆ <a href="#input-clone"><i class="bi bi-code-slash"></i>&nbsp;Code</a>.
 {: .alert-info }

Effectue ensuite les tâches suivantes :
- ```bash
  cd workshop-php-simple-mvc-user
  ```
- ```bash
  composer install
  ```
- Créer le fichier `db.php` à partir du fichier **db.php.dist** avec les identifiants de connexion pour PDO et la base de données `workshop_simple_mvc_user`
- Lancer la commande `php migration.php`.  
Cela aura pour effet d'appeler le fichier `database.sql` et créer la base de données ainsi qu'une table `user` contenant un premier utilisateur dont voici les identifiants de connexion :
> __pseudo__ : marty  
> __email__ : marty@wilders.com  
> __password__ : Wilder4Ever
- Enfin, démarre le serveur avec la commande 
```bash
php -S localhost:8000 -t public
```

Le projet que tu viens de cloner dispose déjà de quelques routes associées à des méthodes de la classe `App\Controller\UserContoller` qui ne renvoient pour le moment que des vues.  

N'hésite pas à consulter le fichier __src/routes.php__ pour en prendre connaissance avant de démarrer.

## Objectifs et notions

Dans cet atelier nous allons mettre en place un système d'identification des utilisateurs.
Pour cela nous aborderons les notions suivantes :
- Utilisation de la superglobale __$_SESSION__
- Utilisation des variables globales Twig
- Créer et vérifier une clé de hachage avec __password_hash()__ et __password_verify()__

> Les bouts de codes fournis dans les différentes sections contiennent des commentaires __*@todo*__ qui t'invitent à les compléter. Il s'agit très souvent d'ajouter des contrôles de valeurs. Voici quelques liens qui te seront utiles :
> - [https://www.php.net/manual/fr/function.filter-var.php](https://www.php.net/manual/fr/function.filter-var.php)
> - [https://www.php.net/manual/fr/function.preg-match](https://www.php.net/manual/fr/function.preg-match)
> - [https://www.html5pattern.com/](https://www.html5pattern.com/)


## 1. UserManager : récupérer l'utilisateur en BDD 

Pour identifier les utilisateurs il va falloir effectuer une requête sur la table `user`.  
Dans une structure **M**VC, c'est le rôle du **M**odel.  
Dans le dossier `src/Model`, crée une classe `UserManager`. N'oublie pas :
- l'héritage de l'`AbstractManager`, 
- le *__namespace__*, 
- la constante `TABLE`, etc. 😏.   

Dans cette classe, crée une méthode `selectOneByEmail` qui prendra en paramètre l'email `string $email` et qui retournera l'enregistrement correspondant de la table `user` grâce à la méthode `fetch()`.  
Ta classe est prête, passons au *__controller__*.

## 2. UserController : soumission du formulaire de connexion

L'étape de connexion se joue en deux temps.

1. Modifie la méthode `login()` pour que, lors de la soumission, l'email saisi dans le formulaire soit envoyé à la méthode `selectOneByEmail` que tu viens de créer. Enregistre son retour dans une variable `$user`.  
⚠️️ N'oublie pas d'effectuer quelques contrôles et d'indiquer à l'utilisateur les erreurs le cas échéant.
2. Vérifie que le mot de passe envoyé par le formulaire corresponde à celui de l'utilisateur.

> __Précisions__  
> Le mot de passe enregistré en BDD n'est pas vraiment le mot de passe de l'utilisateur. Il s'agit plutôt de __l'empreinte du mot de passe__ qui a été générée grâce à la fonction  [__password_hash__](https://www.php.net/manual/fr/function.password-hash.php). Pour vérifier le mot de passe, il faut utiliser la fonction PHP [__password_verify__](https://www.php.net/manual/fr/function.password-verify.php).  

Si ces deux étapes sont un succès, alors on conclut que la connexion est établie.  
Enregistre l'__id__ de l'utilisateur à l'index `['user_id']` du tableau `$_SESSION` puis redirige l'utilisateur vers la page d'accueil.

Voici ce que cela donne.
```php
//src/Controller/UserController.php
public function login()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $credentials = array_map('trim', $_POST);
//      @todo make some controls on email and password fields and if errors, send them to the view
        $userManager = new UserManager();
        $user = $userManager->selectOneByEmail($credentials['email']);
        if ($user && password_verify($credentials['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: /');
            exit();
        }
    }
    return $this->twig->render('User/login.html.twig');
}
```

😳 C'est quoi ce `$_SESSION['user_id']` ?!

## 3. Super globale $_SESSION
`$_SESSION` est une variable globale PHP du type `array`. On persiste ici l'__id__ de notre utilisateur identifié dans la session PHP à l'index `['user_id']`.
> Pour rappel, le lien entre la session PHP enregistrée sur le serveur et le navigateur est rendu possible grâce au cookie __PHPSESSID__.

Il est temps maintenant de rendre notre utilisateur disponible dans notre application.
Rendez-vous dans l'__AbstractController__.

## 4. AbstractController : l'utilisateur disponible dans toute l'application 
C'est ici qu'est instancié l'objet __Twig__ ayant en charge la gestion des templates et accessible par les classes enfants.  
Dans le constructeur, initialise une propriété `$user` qui sera :
- soit un tableau contenant les données de l'utilisateur si il est connecté ;
- soit __false__  

💡 Les classes filles auront besoin d'accéder à cette propriété, choisi une visibilité adaptée et pense aux types. 
{: .alert-info }

Enfin, ajoute `$user` aux variables globales de twig pour l'avoir à disposition aussi dans tes templates.
Voici ce que cela donne.

```php
//src/Controller/AbstractController.php
<?php

namespace App\Controller;

use App\Model\UserManager;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

abstract class AbstractController
{
    protected Environment $twig;
    protected array|false $user;
    /**
     *  Initializes this class.
     */
    public function __construct()
    {
        $loader = new FilesystemLoader(APP_VIEW_PATH);
        $this->twig = new Environment(
            $loader,
            [
                'cache' => false,
                'debug' => (ENV === 'dev'),
            ]
        );
        $this->twig->addExtension(new DebugExtension());
        $userManager = new UserManager();
        $this->user = isset($_SESSION['user_id']) ? $userManager->selectOneById($_SESSION['user_id']) : false;
        $this->twig->addGlobal('user', $this->user);
    }
}
```

Il est temps de tester tout ça !
Accède à la page [http://localhost:8000/login](http://localhost:8000/login) et essaie de te connecter avec les identifiants de l'utilisateur présent dans ta BDD (email : *__marty@wilders.com__* / pass: *__Wilder4Ever__*).


Ça marche ! 🥳

## 5. Déconnexion
Je te laisse mettre les deux lignes manquantes à la méthode `logout` du *__UserController__* pour gérer la déconnexion des utilisateurs.  
Indices :
- détruits l'index `['user_id']` de la superglobale __$_SESSION__ (appuie-toi sur le cours si nécessaire),
- redirige ensuite l'utilisateur vers une autre page (page d'accueil par exemple).

## 6. Restriction des routes

> Si tu jettes un œil au template __Home/index.html.twig__ tu constateras à la ligne 8, l'utilisation de `user` (faisant référence à la variable twig globale `user` et qui permet ici d'afficher du contenu différent selon que l'utilisateur est connecté ou non. Cette vérification peut être utilisée n'importe où dans tes templates. C'est d'ailleurs ce qui se passe dans la barre de navigation pour afficher les différents menus en fonction de l'état de connexion. 👀

De la même façon, tu peux aussi limiter l'accès à certaines pages directement depuis une classe *__Controller__* puisque la propriété `$user` est *__protected__* 😉.  
Faisons cela pour la route `/items`. Si l'utilisateur n'est pas connecté, alors interdis-lui l'accès et affiche un message approprié associé au code de retour [HTTP 401](https://fr.wikipedia.org/wiki/Liste_des_codes_HTTP#4xx_-_Erreur_du_client_HTTP), comme ceci par exemple :

```php
//src/Controller/ItemController.php
class ItemController extends AbstractController
{
    /**
     * List items
     */
    public function index(): string
    {
        if (!$this->user) {
            echo 'Unauthorized access';
            header('HTTP/1.1 401 Unauthorized');
            exit();
        }
        //...
    }
}
```


## 🎁 Bonus : créer un compte

> __Préambule__  
La route __/register__ fait référence à la méthode `register` de notre __UserController__ appelant la vue __User/register.html.twig__. Nous allons avoir besoin là aussi de faire appel au __UserManager__ pour insérer en BDD les informations du formulaire lorsque celui-ci sera soumis.

Ajoute une méthode `insert()` à la classe `App\Model\UserManager` qui prendra en paramètre un tableau `credentials` contenant les champs du formulaire d'inscription. Cette méthode doit donc effectuer une requête SQL __INSERT__ sur la table __user__.  
> ⚠️ __Attention__ ⚠️ l'enregistrement du mot de passe nécessite quelques précautions.
En effet, il ne faut __jamais__ (mais alors __JAMAIS__ !) enregistrer un mot de passe tel quel en base de données (donnée beaucoup trop sensible 😱).  

Comme nous l'avons vu précédemment, PHP possède la fonction [__password_hash__](https://www.php.net/manual/fr/function.password-hash.php) qui retourne la clé de hashage (ou empreinte) d'une chaîne de caractères passée en premier paramètre en utilisant l'algorithme passé en deuxième paramètre (on utilise __PASSWORD_DEFAULT__ comme algorithme pour commencer). La clé générée pourra ensuite être vérifiée avec la fonction [__password_verify__](https://www.php.net/manual/fr/function.password-verify.php).
Voici ce que cela donne.

```php
//src/Model/UserManager.php
public function insert(array $credentials): int
{
    $statement = $this->pdo->prepare("INSERT INTO " . static::TABLE .
        " (`email`, `password`, `pseudo`, `firstname`, `lastname`)
        VALUES (:email, :password, :pseudo, :firstname, :lastname)");
    $statement->bindValue(':email', $credentials['email']);
    $statement->bindValue(':password', password_hash($credentials['password'], PASSWORD_DEFAULT));
    $statement->bindValue(':pseudo', $credentials['pseudo']);
    $statement->bindValue(':firstname', $credentials['firstname']);
    $statement->bindValue(':lastname', $credentials['lastname']);
    $statement->execute();
    return (int)$this->pdo->lastInsertId();
}
```

Tu peux maintenant faire appel à cette méthode depuis ton controller __UserController__ et sa méthode `register` comme ceci.
N'oublie pas de contrôler les valeurs et d'afficher les erreurs si il y en a !
```php
//src/Controller/UserController.php
public function register()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//      @todo make some controls and if errors send them to the view
        $credentials = $_POST;
        $userManager = new UserManager();
        if ($userManager->insert($credentials)) {
            return $this->login();
        }
    }
    return $this->twig->render('User/register.html.twig');
}
```
Si l'insertion en BDD s'est correctement déroulée, alors on connecte l'utilisateur grâce à la méthode `$this->login()`.

Accède à la page [http://localhost:8000/register](http://localhost:8000/register) et essaie de créer un nouveau compte.  
👀 Garde un œil sur ta BDD pour vérifier que tout se passe correctement.

🤝 Good job !
