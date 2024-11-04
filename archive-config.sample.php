<?php
return [
    // remplacer YOURMOODLE par l'adresse du Moodle
    'wsurl' => 'https://YOURMOODLE/webservice/rest/server.php',
    // le token récupéré lors du paramétrage du service externe (voir README)
    'wstoken' => 'xxxxxxxxxxxxxxxxxXX9999999999999',
    // le chemin où placer l'arborescence de l'archive
    'archive_path' => "/PATH/WHERE/TO/CREATE/ARCHIVE/TREE",
    // les catégories à exclure du processus; tout leur sous-arbre sera exclut lui aussi
    'excluded_categories' => [889,882],
    // les cours, au cas par cas, à exclure
    'excluded_courses' => [1],
    // les modules à traiter, plutôt fixe tant qu'un nouveau module n'a pas eu droit au développement de son extraction
    'allowed_modules' => ['assign', 'quiz', 'folder', 'label', 'resource'],
    // note minimale à avoir pour qu'un devoir soit considéré comme valide
    'grade_minimum' => 10,
    // nombre de devoirs devant respecter la condition de note pour qu'un d'entre eux soit sélectionné au hasard
    // permet de limiter le temps de calcul, si, sur 500 devoirs déposés pour un cours, 10 ont eu une note > 10 dès le 20e devoir traité, le traitement est terminé pour cet examen
    'limit_positive_grades' => 10,

    // noms des fichiers extraits
    'quiz_attempt_filename' => 'devoir.html',
    'description_filename' => 'description.html',
    'grade_filename' => 'note.html',
    'assign_intro_filename' => 'intro.html',
    // l'extension sera rajoutée d'après le nom initial du fichier, anonymisé avec le libellé ci-dessous.
    // en pratique les devoirs portent souvent le nom de l'étudiant
    'assign_submission_filename' => 'devoir'
];

?>