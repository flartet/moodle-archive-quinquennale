<?php

/**
 * Wrapper d'appel aux webservices Moodle
 */
class moodle_rest {

    /** @var string $url */
    protected $url;
    /** @var string $token */
    protected $token;
    /** @var resource $curlHandler */
    protected $curlHandler;
    /** @var bool $debug */
    protected $debug;
    
    /**
     * Constructeur de la classe
     * 
     * @param string $URL url du serveur rest de l'instance Moodle
     * @param string $TOKEN token de connexion de l'utilisateur adapté
     * @param bool $debug (optional) active/désactive le mode debug, false par défaut
     */
    public function __construct($URL, $TOKEN, $debug = false) {
        $this->url = $URL;
        $this->token = $TOKEN;
        $this->debug = $debug;
    }

    public function setDebug($debug) {
        $this->debug = $debug;
    }

    /**
     * Appelle le webservice rest Moodle avec la méthode et les arguments donnés
     * 
     * @param string $method le nom du webservice
     * @param array $args les différents paramètres à passer au webservice
     * @return string représentation json du retour de la méthode
     */
    public function fetchJson($method, array $args = []) {
        // create a new cURL resource
        $this->curlHandler = curl_init();

        $args['wsfunction'] = $method;
        $args['wstoken'] = $this->token;
        $args['moodlewsrestformat'] = 'json';
        $args = http_build_query($args);
        
        // set URL and other appropriate options
        if ($this->debug) {
            echo "url (".$this->url.'?'.$args.")";
        }
        curl_setopt($this->curlHandler, CURLOPT_URL, $this->url.'?'.$args);
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandler, CURLOPT_SSL_VERIFYPEER, false);
        $json = curl_exec($this->curlHandler);
        curl_close($this->curlHandler);
        return $json;
    }

    /**
     * Récupère le fichier sur Moodle à l'url donnée
     * 
     * @param string $url l'url du fichier à télécharger
     * @return string le contenu brut du fichier
     */
    public function getFile($url) {
        // create a new cURL resource
        $this->curlHandler = curl_init();

        $args['token'] = $this->token;
        $args = http_build_query($args);
        
        // set URL and other appropriate options
        if ($this->debug) {
            echo "url (".$url.'?'.$args.")";
        }
        $matches = [];
        if (preg_match('/^(.*)\?(.*)/', $url, $matches) > 0) {
            $url = $matches[1];
            $args .= '&'.$matches[2];
        }
        
        curl_setopt($this->curlHandler, CURLOPT_URL, $url.'?'.$args);
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandler, CURLOPT_SSL_VERIFYPEER, false);
        $fileContents = curl_exec($this->curlHandler);
        curl_close($this->curlHandler);
        
        return $fileContents;
    }
}

?>
