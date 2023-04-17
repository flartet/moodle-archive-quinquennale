<?php

require_once('./moodle-rest.php');

class archive_mod_assign {

    /** @var moodle_rest le wrapper d'appel au WS Moodle */
    protected $moodle_rest;

    public function __construct($moodle_rest) {        
        $this->moodle_rest = $moodle_rest;
    }
    
    public function get_assignments_from_courseid($courseid) {
        return json_decode($this->moodle_rest->fetchJson('mod_assign_get_assignments', [
            'courseids' => [$courseid],
            'includenotenrolledcourses' => true
        ]), true);
    }

    public function get_submissions($assignmentid) {
        return json_decode($this->moodle_rest->fetchJson('mod_assign_get_submissions', [
            'assignmentids' => [$assignmentid],
            'status' => 'submitted'
        ]), true);
    }

    public function get_submission_status($assignmentid, $userid) {
        return json_decode($this->moodle_rest->fetchJson('mod_assign_get_submission_status', [
            'assignid' => $assignmentid,
            'userid' => $userid
        ]), true);
    }


}

?>