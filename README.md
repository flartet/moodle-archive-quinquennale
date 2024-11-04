# Iris Archive

## Contexte du projet
Ce projet d'archive quinquennale s'inscrit dans les obligations légales qui incombent aux universités en terme de conservation des données d'examens.\
Ces règles sont, après consultation de l'archiviste :
- toutes les années en 0 ou 5, il faut conserver toutes les consignes d'examens ainsi qu'une copie anonymisée non-blanche prise au hasard
- toutes les autres années, même chose mais pas besoin de copie.

## Fonctionnement
Le script se veut simple à lancer en ligne de commandes. Plutôt que de réinventer la roue et faire des appels directs en base de données, ce qui est parfois tentant, il se sert des webservices de Moodle pour la récupérations des infos. Il est de ce fait moins rapide qu'un script qui ferait des appels directs mais le retour sera plus consistant.\
L'utilisation des webservices demande cependant des droits bien spécifiques pour un utilisateur.\
### Pour aller plus loin
Le script, à l'aide de l'utilisateur dédié va reproduire l'arborescence de la plateforme dans un répertoire défini.\
Pour l'instant, seuls les modules assign (dépôts de devoirs) et quiz voient leurs examens archivés.\
Que ce soit assign ou quiz, une note seuil, afin d'éviter les copies blanches est définie (grade_minimum ; dans le vocable Moodle grade=note). Le nombre de quiz passés dépassant la note seuil est aussi défini (limit_positive_grades) afin d'accélérer le traitement. Dans la configuration de base, si 10 étudiants ont >10 à un test, la "copie" au hasard est choisie parmi les 10.\
Lorsqu'il n'y a pas de note, ce qui arrive souvent sur les dépôts de devoir, un devoir/quiz est pris au hasard, avec un risque de copie blanche.\
Les modules folder/label sont systématiquement extraits car ils peuvent contenir des informations sur l'examen présent dans le cours.\

### Paramétrage des webservices pour un utilisateur dédié à l'archive
#### Création du rôle
Le fichier webservice.xml permet l'import d'un rôle dans Moodle, rôle qui a tout ce qu'il faut pour que la version courante du script ait accès à ce dont il a besoin.
#### Création d'un utilisateur dédié
Libre cours à l'imagination, il aura le rôle créé précédemment au niveau système
#### Attribution du rôle à l'utilisateur fraîchement créé
Au niveau système afin qu'il ait accès à tout ou bien au niveau de votre catégorie spécifique où se trouvent les examens.
#### Création d'un service externe
À effectuer dans le menu dédié de Moodle : */admin/settings.php?section=externalservices*\
Ajouter un service, par ex "archive" qui aura une liste d'utilisateurs autorisés, qui pourra télécharger des fichiers et sera activé.\
#### Ajout d'utilisateurs au service externe
Dans le menu dédié "Utilisateurs autorisés" ajouter l'utilisateur ayant le rôle webservice.
#### Ajout de fonctions au service externe
Enfin, ce service doit avoir accès à tous les webservices nécessaires, actuellement :
| Fonction                                       | Description                                                                                                 | Capacités requises                                                                                           |
|------------------------------------------------|-------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| core_course_get_categories                     | Return category details                                                                                     | moodle/category:viewhiddencategories                                                                         |
| core_course_get_contents                       | Get course contents                                                                                         | moodle/course:update, moodle/course:viewhiddencourses                                                        |
| core_course_get_course_module                  | Return information about a course module                                                                    | moodle/course:view                                                                                           |
| core_course_get_course_module_by_instance      | Return information about a given module name and instance id                                                | moodle/course:view                                                                                           |
| core_course_get_courses                        | Return course details                                                                                       | moodle/course:view, moodle/course:update, moodle/course:viewhiddencourses                                    |
| core_course_get_courses_by_field               | Get courses matching a specific field (id/s, shortname, idnumber, category)                                 | moodle/course:view                                                                                           |
| core_enrol_get_enrolled_users                  | Get enrolled users by course id.                                                                            | moodle/user:viewdetails, moodle/user:viewhiddendetails, moodle/course:useremail, moodle/user:update, site:accessallgroups |
| core_files_get_files                           | Browse Moodle files                                                                                         | moodle/course:view                                                                                           |
| mod_assign_get_assignments                     | Returns the courses and assignments for the users capability                                                | mod/assign:view                                                                                              |
| mod_assign_get_participant                     | Get a participant for an assignment, with some summary info about their submissions.                        | mod/assign:view, mod/assign:viewgrades                                                                       |
| mod_assign_get_submissions                     | Returns the submissions for assignments                                                                     | mod/assign:view                                                                                              |
| mod_assign_get_submission_status               | Returns information about an assignment submission status for a given user.                                 | mod/assign:view                                                                                              |
| mod_assign_list_participants                   | List the participants for a single assignment, with some summary info about their submissions.              | mod/assign:view, mod/assign:viewgrades                                                                       |
| mod_folder_get_folders_by_courses              | Returns a list of folders in a provided list of courses. If no list is provided, all folders the user can view will be returned. Please note that this WS is not returning the folder contents. | mod/folder:view                                                                                              |
| mod_quiz_get_attempt_data                      | Returns information for the given attempt page for a quiz attempt in progress.                              | mod/quiz:attempt                                                                                             |
| mod_quiz_get_attempt_review                    | Returns review information for the given finished attempt, can be used by users or teachers.                | mod/quiz:reviewmyattempts                                                                                    |
| mod_quiz_get_attempt_summary                   | Returns a summary of a quiz attempt before it is submitted.                                                 | mod/quiz:attempt                                                                                             |
| mod_quiz_get_quizzes_by_courses                | Returns a list of quizzes in a provided list of courses, if no list is provided all quizzes that the user can view will be returned. | mod/quiz:view                                                                                              |
| mod_quiz_get_user_attempts                     | Return a list of attempts for the given quiz and user.                                                      | mod/quiz:view                                                                                                |
| mod_quiz_get_user_best_grade                   | Get the best current grade for the given user on a quiz.                                                    | mod/quiz:view                                                                                                |

#### Activation du protocole REST
Celui-ci s'active sur la page dédiée : */admin/settings.php?section=webserviceprotocols*

#### Création du jeton d'authentification
Dans le dernier menu, il faut créer un jeton pour que l'utilisateur du webservice ait accès au service externe "archive" et récupérer ce jeton pour le placer dans la configuration.

## Installation
Le script a été créé sous Linux et n'a pas été testé sous Windows. Si ce ne sont les chemins dans la configuration, il devrait néanmoins fonctionner.\
Un *git clone* du dépôt ou bien une téléchargement des sources suffit.

## Utilisation
Le fichier de configuration doit être créé sous le nom *archive-config.php*. Un fichier exemple est présent et bien documenté.

## Support & Contribution
Tant bien que mal, j'essaierai de lire les Issues mais si nous sommes plusieurs à y contribuer, c'est encore mieux.

## Licence
Comme Moodle actuellement, GPL v3.

## TODO
contrôle d'erreur directement dans l'appel du webservice