<?php
return [
    'wsurl' => 'https://YOURMOODLE/webservice/rest/server.php',
    'wstoken' => 'xxxxxxxxxxxxxxxxxXX9999999999999',
    'archive_path' => "PATH/WHERE/TO/CREATE/ARCHIVE/TREE",
    'excluded_categories' => [889,882],
    'excluded_courses' => [1],
    'allowed_modules' => ['assign', 'quiz', 'folder', 'label', 'resource'],
    'quiz_attempt_filename' => 'devoir.html',
    'description_filename' => 'description.html',
    'limit_positive_grades' => 10,
    'grade_minimum' => 10,
    'grade_filename' => 'note.html',
    'assign_intro_filename' => 'intro.html',
    'assign_submission_filename' => 'devoir'
];

?>