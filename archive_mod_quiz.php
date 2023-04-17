<?php

require_once('./moodle-rest.php');

class archive_mod_quiz {

    /** @var moodle_rest le wrapper d'appel au WS Moodle */
    protected $moodle_rest;

    public function __construct($moodle_rest) {        
        $this->moodle_rest = $moodle_rest;
    }
    
    public function get_user_best_grade($quizid, $userid) {
        $user_best_grade = $this->moodle_rest->fetchJson('mod_quiz_get_user_best_grade', [
            'quizid' => $quizid,
            'userid' => $userid
        ]);
        return json_decode($user_best_grade, true);
    }

    public function get_user_attempts($quizid, $userid) {
        $user_attempts = $this->moodle_rest->fetchJson('mod_quiz_get_user_attempts', [
            'quizid' => $quizid,
            'userid' => $userid
        ]);
        return json_decode($user_attempts, true);
    }

    public function get_attempt_review($attemptid) {
        $attempt = $this->moodle_rest->fetchJson('mod_quiz_get_attempt_review', [
            'attemptid' => $attemptid
        ]);
        return json_decode($attempt, true);
    }
}

?>